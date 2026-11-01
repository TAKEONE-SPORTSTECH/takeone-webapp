<?php

namespace Tests\Feature;

use App\Clubs\Models\Invoice;
use App\Clubs\Models\Tenant;
use Tests\TestCase;

/**
 * A member can never mark their own bill paid. `bills.pay` / `bills.pay-all`
 * used to set status=paid with no money changing hands; payment is confirmed by
 * the club, so these doors now only explain that and change nothing.
 */
class InvoicePayTest extends TestCase
{
    private function invoiceFor($payer, string $status = 'pending'): Invoice
    {
        $owner = $this->createUser();
        $club = Tenant::create(['owner_user_id' => $owner->id, 'club_name' => 'Bill Club', 'slug' => 'bill-'.uniqid(), 'status' => 'active']);

        return Invoice::create([
            'tenant_id' => $club->id, 'amount' => 25, 'status' => $status,
            'due_date' => now()->addWeek()->toDateString(),
            'student_user_id' => $payer->id, 'payer_user_id' => $payer->id,
        ]);
    }

    public function test_pay_does_not_mark_the_invoice_paid(): void
    {
        $member = $this->createUser();
        $invoice = $this->invoiceFor($member);

        $this->actingAs($member)->get(route('bills.pay', $invoice->id))
            ->assertRedirect(route('bills.show', $invoice->id))
            ->assertSessionHas('info');

        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_pay_all_does_not_mark_any_invoice_paid(): void
    {
        $member = $this->createUser();
        $a = $this->invoiceFor($member);
        $b = $this->invoiceFor($member);

        $this->actingAs($member)->get(route('bills.pay-all'))
            ->assertRedirect(route('bills.index'))
            ->assertSessionHas('info');

        $this->assertSame('pending', $a->fresh()->status);
        $this->assertSame('pending', $b->fresh()->status);
    }

    public function test_another_members_invoice_is_not_found(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();
        $invoice = $this->invoiceFor($owner);

        // A web not-found bounces with an error toast (bootstrap/app.php), never a 200.
        $this->actingAs($stranger)->get(route('bills.pay', $invoice->id))
            ->assertRedirect()->assertSessionHas('error')->assertSessionMissing('info');
        $this->assertSame('pending', $invoice->fresh()->status);
    }
}
