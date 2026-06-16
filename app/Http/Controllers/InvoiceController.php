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
    public function index(Request $request)
    {
        $user = Auth::user();

        // Pull every invoice once: the summary band is always all-time, while the
        // list below reacts to the status / date filters.
        $all = Invoice::where('payer_user_id', $user->id)
            ->with(['student', 'tenant'])
            ->orderBy('due_date', 'desc')
            ->get();

        $today = now()->startOfDay();

        $summary = [
            'outstanding'   => (float) $all->where('status', 'pending')->sum('amount'),
            'paid_total'    => (float) $all->where('status', 'paid')->sum('amount'),
            'pending_count' => $all->where('status', 'pending')->count(),
            'paid_count'    => $all->where('status', 'paid')->count(),
            'overdue_count' => $all->where('status', 'pending')
                ->filter(fn ($i) => $i->due_date && $i->due_date->lt($today))->count(),
            'next_due'      => $all->where('status', 'pending')
                ->filter(fn ($i) => $i->due_date)
                ->sortBy('due_date')->first(),
        ];

        // The currency follows the user's club(s); fall back to BHD.
        $currency = $all->first()?->tenant?->currency ?? 'BHD';

        // Apply the active filters in-memory to produce the visible list.
        $status = in_array($request->status, ['pending', 'paid']) ? $request->status : null;
        $list = $all
            ->when($status, fn ($c) => $c->where('status', $status))
            ->when($request->start_date, fn ($c) => $c->filter(fn ($i) => $i->due_date && $i->due_date->gte($request->start_date)))
            ->when($request->end_date, fn ($c) => $c->filter(fn ($i) => $i->due_date && $i->due_date->lte($request->end_date)))
            ->values();

        // Group the list by month for the statement-style timeline.
        $grouped = $list->groupBy(fn ($i) => $i->due_date?->format('F Y') ?? 'Undated');

        $viewData = compact('summary', 'currency', 'list', 'grouped', 'status', 'today');

        if ($request->ajax()) {
            return view('components-templates.invoices._list', $viewData);
        }

        return view('components-templates.invoices.index', $viewData);
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
            return response()->json(['html' => view('components-templates.invoices._show_modal', compact('invoice'))->render()]);
        }

        return view('components-templates.invoices.show', compact('invoice'));
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
            $html = view('components-templates.invoices.receipt', compact('invoice'))->render();
            return response($html)
                ->header('Content-Type', 'text/html')
                ->header('Content-Disposition', 'attachment; filename="receipt_' . $invoice->id . '.html"');
        }

        return view('components-templates.invoices.receipt', compact('invoice'));
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
