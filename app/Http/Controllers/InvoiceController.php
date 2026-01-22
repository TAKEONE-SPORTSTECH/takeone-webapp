<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    public function __construct()
    {
        // Auth middleware will be applied in routes
    }

    /**
     * Display a listing of the invoices.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = Auth::user();
        $invoices = Invoice::where('payer_user_id', $user->id)
            ->with(['student', 'tenant'])
            ->get();

        return view('invoices.index', compact('invoices'));
    }

    /**
     * Display the specified invoice.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function show(Request $request, $id)
    {
        $user = Auth::user();
        $invoice = Invoice::where('id', $id)
            ->where('payer_user_id', $user->id)
            ->with(['student', 'tenant'])
            ->firstOrFail();

        if ($request->ajax()) {
            return response()->json(['html' => view('invoices._show_modal', compact('invoice'))->render()]);
        }

        return view('invoices.show', compact('invoice'));
    }

    /**
     * Display the receipt for the specified invoice.
     *
     * @param  int  $id
     * @return \Illuminate\View\View|\Illuminate\Http\Response
     */
    public function receipt(Request $request, $id)
    {
        $user = Auth::user();
        $invoice = Invoice::where('id', $id)
            ->where('payer_user_id', $user->id)
            ->with(['student', 'tenant'])
            ->firstOrFail();

        if ($request->has('download')) {
            $html = view('invoices.receipt', compact('invoice'))->render();
            return response($html)
                ->header('Content-Type', 'text/html')
                ->header('Content-Disposition', 'attachment; filename="receipt_' . $invoice->id . '.html"');
        }

        return view('invoices.receipt', compact('invoice'));
    }

    /**
     * Process payment for the specified invoice.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function pay($id)
    {
        $user = Auth::user();
        $invoice = Invoice::where('id', $id)
            ->where('payer_user_id', $user->id)
            ->firstOrFail();

        // In a real application, this would integrate with a payment gateway
        $invoice->update([
            'status' => 'paid'
        ]);

        return redirect()->route('bills.show', $invoice->id)
            ->with('success', 'Payment processed successfully.');
    }

    /**
     * Process payment for all unpaid invoices.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function payAll()
    {
        $user = Auth::user();
        $invoices = Invoice::where('payer_user_id', $user->id)
            ->where('status', '!=', 'paid')
            ->get();

        // In a real application, this would integrate with a payment gateway
        foreach ($invoices as $invoice) {
            $invoice->update([
                'status' => 'paid'
            ]);
        }

        return redirect()->route('bills.index')
            ->with('success', 'All payments processed successfully.');
    }
}
