<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTransactionRequest;
use App\Http\Requests\Admin\UpdateTransactionRequest;
use App\Models\ClubMemberSubscription;
use App\Models\ClubRecurringExpense;
use App\Models\ClubTransaction;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\FinancialService;
use App\Services\RecurringExpenseService;
use App\Support\ClubCache;
use App\Traits\HandlesClubAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClubFinancialController extends Controller
{
    use HandlesClubAuthorization;

    public function financials(Tenant $club, FinancialService $financials)
    {
        $this->authorizeClub($club);

        $isTestMode = (bool) $club->is_test_mode;

        $transactions = ClubTransaction::where('tenant_id', $club->id)->where('is_test', $isTestMode)->with(['subscription.user'])->latest('transaction_date')->get();
        $summary = $financials->getSummary($club->id, $transactions, $isTestMode);
        $monthlyData = $financials->getMonthlyData($transactions, $club->id, $isTestMode);
        $pendingSubscriptions = $financials->getCashToCollect($club->id, $isTestMode);
        $expenseCategories = $financials->getExpenseBreakdown($transactions);
        $recurringExpenses = ClubRecurringExpense::where('tenant_id', $club->id)->orderBy('day_of_month')->get();

        return view(\App\Support\ClubView::pick('financials'), compact('club', 'transactions', 'summary', 'monthlyData', 'pendingSubscriptions', 'expenseCategories', 'recurringExpenses', 'isTestMode'));
    }

    /**
     * Every test-tagged financial row for this club, for the "switch to Live"
     * review screen. The admin picks which rows to keep (real) vs. let go
     * (test) before the mode switch is finalized.
     */
    public function testData(Tenant $club)
    {
        $this->authorizeClub($club);

        $transactions = ClubTransaction::where('tenant_id', $club->id)
            ->where('is_test', true)
            ->orderByDesc('transaction_date')
            ->get(['id', 'type', 'description', 'category', 'amount', 'transaction_date'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'description' => $t->description,
                'category' => $t->category,
                'amount' => (float) $t->amount,
                'date' => optional($t->transaction_date)->format('d M Y'),
            ]);

        $subscriptions = ClubMemberSubscription::where('tenant_id', $club->id)
            ->where('is_test', true)
            ->with('user:id,full_name,name', 'package:id,name')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->user->full_name ?? $s->user->name ?? __('admin.fin_member'),
                'package' => $s->package->name ?? null,
                'amount' => (float) ($s->amount_paid ?: $s->amount_due),
                'status' => $s->payment_status,
                'date' => optional($s->start_date)->format('d M Y'),
            ]);

        $orders = Order::where('tenant_id', $club->id)
            ->where('is_test', true)
            ->orderByDesc('created_at')
            ->get(['id', 'reference', 'total', 'currency', 'status', 'created_at'])
            ->map(fn ($o) => [
                'id' => $o->id,
                'reference' => $o->reference,
                'total' => (float) $o->total,
                'currency' => $o->currency,
                'status' => $o->status,
                'date' => optional($o->created_at)->format('d M Y'),
            ]);

        return response()->json([
            'success' => true,
            'transactions' => $transactions,
            'subscriptions' => $subscriptions,
            'orders' => $orders,
            'total' => $transactions->count() + $subscriptions->count() + $orders->count(),
        ]);
    }

    /**
     * Switch a club between Test and Live mode.
     *
     * Test → Live optionally carries keep_* id lists (rows the admin marked
     * "this one was real" in the review screen): those graduate to is_test =
     * false instead of being deleted. Everything else tagged is_test = true
     * is permanently removed. Live → Test never touches existing data.
     */
    public function switchMode(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $data = $request->validate([
            'mode' => 'required|in:test,live',
            'keep_transaction_ids' => 'array',
            'keep_transaction_ids.*' => 'integer',
            'keep_subscription_ids' => 'array',
            'keep_subscription_ids.*' => 'integer',
            'keep_order_ids' => 'array',
            'keep_order_ids.*' => 'integer',
        ]);

        if ($data['mode'] === 'test') {
            $club->is_test_mode = true;
            $club->save();
            Tenant::forgetTestModeCache($club->id);

            return response()->json(['success' => true, 'message' => 'Club switched to Test Mode.']);
        }

        $keepTransactionIds = $data['keep_transaction_ids'] ?? [];
        $keepSubscriptionIds = $data['keep_subscription_ids'] ?? [];
        $keepOrderIds = $data['keep_order_ids'] ?? [];

        DB::transaction(function () use ($club, $keepTransactionIds, $keepSubscriptionIds, $keepOrderIds) {
            ClubTransaction::where('tenant_id', $club->id)->where('is_test', true)
                ->whereIn('id', $keepTransactionIds)->update(['is_test' => false]);
            ClubTransaction::where('tenant_id', $club->id)->where('is_test', true)
                ->whereNotIn('id', $keepTransactionIds)->get()->each(fn ($t) => $t->delete());

            ClubMemberSubscription::where('tenant_id', $club->id)->where('is_test', true)
                ->whereIn('id', $keepSubscriptionIds)->update(['is_test' => false]);
            ClubMemberSubscription::where('tenant_id', $club->id)->where('is_test', true)
                ->whereNotIn('id', $keepSubscriptionIds)->get()->each(fn ($s) => $s->delete());

            Order::where('tenant_id', $club->id)->where('is_test', true)
                ->whereIn('id', $keepOrderIds)->update(['is_test' => false]);
            // Force-delete (not soft-delete) so DeletesUploadedFiles purges payment_proof_path.
            Order::where('tenant_id', $club->id)->where('is_test', true)
                ->whereNotIn('id', $keepOrderIds)->get()->each(fn ($o) => $o->forceDelete());

            $club->is_test_mode = false;
            $club->save();
        });

        Tenant::forgetTestModeCache($club->id);
        ClubCache::flushAll($club->id);

        $this->notifyClubAdmins($club);

        return response()->json(['success' => true, 'message' => 'Club switched to Live Mode.']);
    }

    /**
     * Best-effort MQTT fan-out so any other open admin session for this club
     * refreshes its financials view after a mode switch.
     */
    private function notifyClubAdmins(Tenant $club): void
    {
        $adminIds = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.tenant_id', $club->id)
            ->where('roles.slug', 'club-admin')
            ->pluck('user_roles.user_id')
            ->push($club->owner_user_id)
            ->filter()
            ->unique();

        if ($adminIds->isEmpty()) {
            return;
        }

        rescue(fn () => Realtime()->publishMany(
            $adminIds->map(fn ($id) => [
                'topic' => Realtime()->userTopic((int) $id, 'financials'),
                'payload' => ['action' => 'refresh'],
            ])->all()
        ), null, false);
    }

    public function storeIncome(StoreTransactionRequest $request, Tenant $club, FinancialService $financials)
    {
        $this->authorizeClub($club);

        $financials->recordTransaction($club, [
            'description' => $request->description,
            'amount' => $request->amount,
            'type' => 'income',
            'category' => $request->category,
            'payment_method' => $request->payment_method ?? 'cash',
            'reference_number' => $request->reference_number,
            'transaction_date' => $request->transaction_date,
        ]);

        return back()->with('success', 'Income recorded successfully.');
    }

    public function storeExpense(StoreTransactionRequest $request, Tenant $club, FinancialService $financials)
    {
        $this->authorizeClub($club);

        $financials->recordTransaction($club, [
            'description' => $request->description,
            'amount' => $request->amount,
            'type' => 'expense',
            'category' => $request->category,
            'payment_method' => $request->payment_method ?? 'cash',
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
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'category' => 'nullable|string|max:255',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,online,other',
            'recurring_date' => 'required|date',
            'notes' => 'nullable|string|max:255',
        ]);

        $rule = ClubRecurringExpense::create([
            'tenant_id' => $club->id,
            'description' => $data['description'],
            'amount' => $data['amount'],
            'category' => $data['category'] ?? null,
            'payment_method' => $data['payment_method'] ?? 'bank_transfer',
            'day_of_month' => \Carbon\Carbon::parse($data['recurring_date'])->day,
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
        ]);

        // Post it for the current month right away so the net reflects the commitment
        // immediately instead of only after the due day (see RecurringExpenseService).
        app(RecurringExpenseService::class)->postForCurrentMonth($rule);

        return back()->with('success', 'Recurring expense added successfully.');
    }

    public function updateRecurringExpense(Tenant $club, ClubRecurringExpense $recurringExpense)
    {
        $this->authorizeClub($club);
        abort_if($recurringExpense->tenant_id !== $club->id, 403);

        $data = request()->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'category' => 'nullable|string|max:255',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,online,other',
            'recurring_date' => 'required|date',
            'notes' => 'nullable|string|max:255',
        ]);

        $recurringExpense->update([
            'description' => $data['description'],
            'amount' => $data['amount'],
            'category' => $data['category'] ?? null,
            'payment_method' => $data['payment_method'] ?? 'bank_transfer',
            'day_of_month' => \Carbon\Carbon::parse($data['recurring_date'])->day,
            'notes' => $data['notes'] ?? null,
        ]);

        // Correct this month's posted row (or post it, if the rule had not run yet).
        app(RecurringExpenseService::class)->postForCurrentMonth($recurringExpense);

        return back()->with('success', 'Recurring expense updated successfully.');
    }

    public function destroyRecurringExpense(Tenant $club, ClubRecurringExpense $recurringExpense)
    {
        $this->authorizeClub($club);
        abort_if($recurringExpense->tenant_id !== $club->id, 403);

        // Prorate this month's posted row before the rule (and its link) disappears.
        app(RecurringExpenseService::class)->stopForCurrentMonth($recurringExpense);

        $recurringExpense->delete();

        // The mobile view patches its list in place, so answer AJAX with JSON
        // instead of a redirect it would have to follow.
        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Recurring expense removed.']);
        }

        return back()->with('success', 'Recurring expense removed.');
    }

    public function toggleRecurringExpense(Tenant $club, ClubRecurringExpense $recurringExpense)
    {
        $this->authorizeClub($club);
        abort_if($recurringExpense->tenant_id !== $club->id, 403);

        $recurringExpense->update(['is_active' => ! $recurringExpense->is_active]);

        $recurringExpenses = app(RecurringExpenseService::class);

        $recurringExpense->is_active
            ? $recurringExpenses->postForCurrentMonth($recurringExpense)
            : $recurringExpenses->stopForCurrentMonth($recurringExpense);

        $message = 'Recurring expense '.($recurringExpense->is_active ? 'activated' : 'paused').'.';

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'is_active' => (bool) $recurringExpense->is_active,
            ]);
        }

        return back()->with('success', $message);
    }
}
