<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTransactionRequest;
use App\Http\Requests\Admin\UpdateTransactionRequest;
use App\Models\ClubRecurringExpense;
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
        $monthlyData       = $financials->getMonthlyData($transactions, $club->id);
        $expenseCategories = $financials->getExpenseBreakdown($transactions);
        $recurringExpenses = ClubRecurringExpense::where('tenant_id', $club->id)->orderBy('day_of_month')->get();

        return view('admin.club.financials.index', compact('club', 'transactions', 'summary', 'monthlyData', 'expenseCategories', 'recurringExpenses'));
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

    public function storeRecurringExpense(Tenant $club)
    {
        $this->authorizeClub($club);

        $data = request()->validate([
            'description'    => 'required|string|max:255',
            'amount'         => 'required|numeric|min:0',
            'category'       => 'nullable|string|max:255',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,online,other',
            'recurring_date' => 'required|date',
            'notes'          => 'nullable|string|max:255',
        ]);

        ClubRecurringExpense::create([
            'tenant_id'      => $club->id,
            'description'    => $data['description'],
            'amount'         => $data['amount'],
            'category'       => $data['category'] ?? null,
            'payment_method' => $data['payment_method'] ?? 'bank_transfer',
            'day_of_month'   => \Carbon\Carbon::parse($data['recurring_date'])->day,
            'notes'          => $data['notes'] ?? null,
            'is_active'      => true,
        ]);

        return back()->with('success', 'Recurring expense added successfully.');
    }

    public function updateRecurringExpense(Tenant $club, ClubRecurringExpense $recurringExpense)
    {
        $this->authorizeClub($club);
        abort_if($recurringExpense->tenant_id !== $club->id, 403);

        $data = request()->validate([
            'description'    => 'required|string|max:255',
            'amount'         => 'required|numeric|min:0',
            'category'       => 'nullable|string|max:255',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,online,other',
            'recurring_date' => 'required|date',
            'notes'          => 'nullable|string|max:255',
        ]);

        $recurringExpense->update([
            'description'    => $data['description'],
            'amount'         => $data['amount'],
            'category'       => $data['category'] ?? null,
            'payment_method' => $data['payment_method'] ?? 'bank_transfer',
            'day_of_month'   => \Carbon\Carbon::parse($data['recurring_date'])->day,
            'notes'          => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Recurring expense updated successfully.');
    }

    public function destroyRecurringExpense(Tenant $club, ClubRecurringExpense $recurringExpense)
    {
        $this->authorizeClub($club);
        abort_if($recurringExpense->tenant_id !== $club->id, 403);

        $recurringExpense->delete();

        return back()->with('success', 'Recurring expense removed.');
    }

    public function toggleRecurringExpense(Tenant $club, ClubRecurringExpense $recurringExpense)
    {
        $this->authorizeClub($club);
        abort_if($recurringExpense->tenant_id !== $club->id, 403);

        $recurringExpense->update(['is_active' => ! $recurringExpense->is_active]);

        return back()->with('success', 'Recurring expense ' . ($recurringExpense->is_active ? 'activated' : 'paused') . '.');
    }
}
