<?php

namespace App\Mcp\Tools;

use App\Models\ClubTransaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Record club transaction')]
#[Description('Record a manual income or expense transaction for a club. Requires admin access (owner / club-admin / super-admin). Returns the created transaction.')]
class RecordTransactionTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'club' => $schema->string()->required()->description('Club numeric id or slug.'),
            'type' => $schema->string()->enum(['income', 'expense'])->required()
                ->description('Transaction type.'),
            'amount' => $schema->number()->required()
                ->description('Amount in the club\'s currency (positive number).'),
            'category' => $schema->string()->description('Optional category label (e.g. "membership", "equipment").'),
            'description' => $schema->string()->description('Optional free-text note.'),
            'payment_method' => $schema->string()->enum(['cash', 'card', 'bank_transfer', 'online', 'other'])
                ->description('Optional payment method. Defaults to "cash".'),
            'transaction_date' => $schema->string()->description('Optional date (YYYY-MM-DD). Defaults to today.'),
            'reference_number' => $schema->string()->description('Optional external reference number.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $club = $this->resolveAccessibleClub($user, (string) $request->get('club'));

        if (! $club) {
            return Response::error('Club not found or you do not have access to it.');
        }

        if (! $this->canAdminClub($user, $club)) {
            return Response::error('Only club admins, owners, or super-admins can record transactions.');
        }

        $validated = $request->validate([
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0.01',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,online,other',
            'transaction_date' => 'nullable|date',
            'reference_number' => 'nullable|string|max:100',
        ]);

        $transaction = ClubTransaction::create([
            'tenant_id' => $club->id,
            'user_id' => $user->id,
            'type' => $validated['type'],
            'category' => $validated['category'] ?? null,
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'] ?? 'cash',
            'description' => $validated['description'] ?? null,
            'transaction_date' => $validated['transaction_date'] ?? now()->toDateString(),
            'reference_number' => $validated['reference_number'] ?? null,
        ]);

        return Response::json([
            'success' => true,
            'message' => 'Transaction recorded.',
            'transaction' => [
                'id' => $transaction->id,
                'club_id' => $transaction->tenant_id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'category' => $transaction->category,
                'description' => $transaction->description,
                'transaction_date' => optional($transaction->transaction_date)->toDateString(),
                'recorded_by' => $user->full_name ?? $user->name,
                'is_test' => $transaction->is_test,
            ],
        ]);
    }
}
