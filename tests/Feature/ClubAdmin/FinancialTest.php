<?php

namespace Tests\Feature\ClubAdmin;

use App\Models\ClubTransaction;
use Tests\TestCase;

class FinancialTest extends TestCase
{
    public function test_owner_can_record_income(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->post("/admin/club/{$club->slug}/financials/income", [
                 'description'      => 'Monthly subscription payment',
                 'amount'           => 150.00,
                 'transaction_date' => '2026-03-01',
                 'category'         => 'subscription',
                 'payment_method'   => 'cash',
             ])
             ->assertRedirect();

        $this->assertDatabaseHas('club_transactions', [
            'tenant_id'   => $club->id,
            'type'        => 'income',
            'amount'      => 150.00,
            'description' => 'Monthly subscription payment',
        ]);
    }

    public function test_owner_can_record_expense(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->post("/admin/club/{$club->slug}/financials/expense", [
                 'description'      => 'Equipment purchase',
                 'amount'           => 500.00,
                 'transaction_date' => '2026-03-05',
                 'category'         => 'equipment',
                 'payment_method'   => 'card',
             ])
             ->assertRedirect();

        $this->assertDatabaseHas('club_transactions', [
            'tenant_id' => $club->id,
            'type'      => 'expense',
            'amount'    => 500.00,
        ]);
    }

    public function test_income_requires_description_amount_and_date(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->post("/admin/club/{$club->slug}/financials/income", [])
             ->assertSessionHasErrors(['description', 'amount', 'transaction_date']);
    }

    public function test_amount_must_be_positive(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->post("/admin/club/{$club->slug}/financials/income", [
                 'description'      => 'Test',
                 'amount'           => -50,
                 'transaction_date' => '2026-03-01',
             ])
             ->assertSessionHasErrors(['amount']);
    }

    public function test_owner_can_update_transaction(): void
    {
        $owner       = $this->createUser();
        $club        = $this->createClub($owner);
        $transaction = ClubTransaction::create([
            'tenant_id'        => $club->id,
            'type'             => 'income',
            'amount'           => 100,
            'description'      => 'Old description',
            'transaction_date' => '2026-03-01',
        ]);

        $this->actingAs($owner)
             ->put("/admin/club/{$club->slug}/financials/{$transaction->id}", [
                 'description'      => 'Updated description',
                 'amount'           => 200,
                 'transaction_date' => '2026-03-10',
                 'type'             => 'income',
             ])
             ->assertRedirect();

        $this->assertDatabaseHas('club_transactions', [
            'id'          => $transaction->id,
            'amount'      => 200,
            'description' => 'Updated description',
        ]);
    }

    public function test_owner_can_delete_transaction(): void
    {
        $owner       = $this->createUser();
        $club        = $this->createClub($owner);
        $transaction = ClubTransaction::create([
            'tenant_id'        => $club->id,
            'type'             => 'expense',
            'amount'           => 75,
            'description'      => 'To delete',
            'transaction_date' => '2026-03-01',
        ]);

        $this->actingAs($owner)
             ->delete("/admin/club/{$club->slug}/financials/{$transaction->id}")
             ->assertRedirect();

        $this->assertDatabaseMissing('club_transactions', ['id' => $transaction->id]);
    }

    public function test_cannot_delete_transaction_of_another_club(): void
    {
        $owner1 = $this->createUser();
        $club1  = $this->createClub($owner1);

        $owner2 = $this->createUser();
        $club2  = $this->createClub($owner2);

        $transaction = ClubTransaction::create([
            'tenant_id'        => $club2->id,
            'type'             => 'income',
            'amount'           => 100,
            'description'      => 'Club 2 income',
            'transaction_date' => '2026-03-01',
        ]);

        $this->actingAs($owner1)
             ->delete("/admin/club/{$club1->slug}/financials/{$transaction->id}")
             ->assertNotFound();

        $this->assertDatabaseHas('club_transactions', ['id' => $transaction->id]);
    }

    public function test_financials_page_loads_for_owner(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)
             ->get("/admin/club/{$club->slug}/financials")
             ->assertOk();
    }
}
