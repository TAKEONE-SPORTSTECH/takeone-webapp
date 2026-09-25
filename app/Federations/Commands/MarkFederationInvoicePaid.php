<?php

namespace App\Federations\Commands;

use App\Federations\Controllers\FederationAppController;
use App\Federations\Models\FederationInvoice;
use App\Federations\Models\FederationStaff;
use App\Federations\Services\FederationBilling;
use App\Members\Models\User;
use App\Members\Models\UserNotification;
use Illuminate\Console\Command;

/**
 * A platform administrator confirms a federation's bank transfer arrived.
 * There is no payment gateway (deferred): this is the ONE way a TakeOne
 * subscription invoice becomes Paid.
 */
class MarkFederationInvoicePaid extends Command
{
    protected $signature = 'federation:invoice-paid
        {number : The invoice number, which is also the bank reference}
        {--by= : Email of the administrator confirming it}';

    protected $description = 'Mark a federation subscription invoice as paid once the transfer has arrived';

    public function handle(FederationBilling $billing): int
    {
        $invoice = FederationInvoice::where('number', (string) $this->argument('number'))->first();
        if (! $invoice) {
            $this->error('No invoice with that number.');

            return self::FAILURE;
        }
        if ($invoice->status !== FederationInvoice::STATUS_PENDING) {
            $this->error("That invoice is {$invoice->status}, not Pending.");

            return self::FAILURE;
        }

        $by = $this->option('by') ? User::where('email', $this->option('by'))->first() : null;
        $billing->markPaid($invoice, $by);

        $federation = $invoice->federation;
        // Managers only — billing is not shown to sport-limited staff.
        $recipients = FederationStaff::where('federation_id', $federation->id)->get()
            ->filter(fn (FederationStaff $s) => empty($s->sports))->pluck('user_id')
            ->push($federation->owner_user_id)->unique();
        foreach ($recipients as $userId) {
            UserNotification::notifyUser((int) $userId, 'federation_invoice_paid', 'Subscription payment received', [
                'body' => $invoice->number.' · BHD '.number_format((float) $invoice->amount, 3),
                'icon' => 'bi-receipt',
                'action_url' => url('/federation/app/'.rawurlencode(FederationAppController::HOME)),
            ]);
        }

        // Open dashboards show the paid invoice without a refresh.
        \App\Federations\Support\FederationPulse::federation($federation);

        $this->info("{$invoice->number} marked paid for {$federation->name}.");

        return self::SUCCESS;
    }
}
