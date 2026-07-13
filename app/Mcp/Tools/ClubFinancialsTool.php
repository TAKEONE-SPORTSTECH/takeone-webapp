<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Club financial summary')]
#[Description('Summarise a club\'s finances: total income, total expenses, net, and cash still to collect from unpaid subscriptions. Requires admin access to the club (owner / club-admin / super-admin).')]
class ClubFinancialsTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'club' => $schema->string()->required()->description('Club numeric id or slug.'),
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
            return Response::error('Financials are only available to club admins, owners, or super-admins.');
        }

        $income = (float) $club->transactions()->where('type', 'income')->sum('amount');
        $expenses = (float) $club->transactions()->where('type', 'expense')->sum('amount');

        $cashToCollect = (float) $club->subscriptions()
            ->whereIn('payment_status', ['unpaid', 'partial', 'pending'])
            ->sum('amount_due');

        return Response::json([
            'club' => ['id' => $club->id, 'name' => $club->club_name, 'slug' => $club->slug],
            'currency' => $club->currency,
            'total_income' => round($income, 2),
            'total_expenses' => round($expenses, 2),
            'net' => round($income - $expenses, 2),
            'cash_to_collect' => round($cashToCollect, 2),
            'transactions_count' => $club->transactions()->count(),
            'active_subscriptions' => $club->subscriptions()->where('status', 'active')->count(),
        ]);
    }
}
