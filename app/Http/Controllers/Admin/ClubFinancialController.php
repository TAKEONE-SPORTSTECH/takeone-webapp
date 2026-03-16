<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTransactionRequest;
use App\Http\Requests\Admin\UpdateTransactionRequest;
use App\Models\ClubTransaction;
use App\Models\Tenant;
use App\Services\FinancialService;
use App\Traits\HandlesClubAuthorization;

class ClubFinancialController extends Controller
{
    use HandlesClubAuthorization;

    public function financials(Tenant $club, FinancialService $financials)
    {
        $this->authorizeClub($club);

        $transactions      = ClubTransaction::where('tenant_id', $club->id)->with(['subscription.user'])->latest('transaction_date')->get();
        $summary           = $financials->getSummary($club->id, $transactions);
        $monthlyData       = $financials->getMonthlyData($transactions);
        $expenseCategories = $financials->getExpenseBreakdown($transactions);

        return view('admin.club.financials.index', compact('club', 'transactions', 'summary', 'monthlyData', 'expenseCategories'));
    }

    public function storeIncome(StoreTransactionRequest $request, Tenant $club, FinancialService $financials)
    {
        $this->authorizeClub($club);

        $financials->recordTransaction($club, [
            'description'      => $request->description,
            'amount'           => $request->amount,
            'type'             => 'income',
            'category'         => $request->category,
            'payment_method'   => $request->payment_method ?? 'cash',
            'reference_number' => $request->reference_number,
            'transaction_date' => $request->transaction_date,
        ]);

        return back()->with('success', 'Income recorded successfully.');
    }

    public function storeExpense(StoreTransactionRequest $request, Tenant $club, FinancialService $financials)
    {
        $this->authorizeClub($club);

        $financials->recordTransaction($club, [
            'description'      => $request->description,
            'amount'           => $request->amount,
            'type'             => 'expense',
            'category'         => $request->category,
            'payment_method'   => $request->payment_method ?? 'cash',
            'reference_number' => $request->reference_number,
            'transaction_date' => $request->transaction_date,
        ]);

        return back()->with('success', 'Expense recorded successfully.');
    }

    public function updateTransaction(UpdateTransactionRequest $request, Tenant $club, $transactionId)
    {
        $this->authorizeClub($club);
        $transaction = ClubTransaction::where('tenant_id', $club->id)->findOrFail($transactionId);

        $transaction->update($request->only([
            'description', 'amount', 'transaction_date', 'type',
            'category', 'payment_method', 'reference_number',
        ]));

        return back()->with('success', 'Transaction updated successfully.');
    }

    public function destroyTransaction(Tenant $club, $transactionId)
    {
        $this->authorizeClub($club);
        $transaction = ClubTransaction::where('tenant_id', $club->id)->findOrFail($transactionId);
        $transaction->delete();

        return back()->with('success', 'Transaction deleted successfully.');
    }
}
