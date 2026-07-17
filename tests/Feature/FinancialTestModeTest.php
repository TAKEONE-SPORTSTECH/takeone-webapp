<?php

namespace Tests\Feature;

use App\Models\ClubTransaction;
use App\Models\Tenant;
use Tests\TestCase;

class FinancialTestModeTest extends TestCase
{
    public function test_new_transaction_inherits_the_clubs_current_mode(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        $this->assertTrue($club->fresh()->is_test_mode);

        $this->actingAs($owner)
            ->post("/admin/club/{$club->slug}/financials/income", [
                'description' => 'Test income',
                'amount' => 50,
                'category' => 'other',
                'transaction_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('club_transactions', [
            'tenant_id' => $club->id,
            'description' => 'Test income',
            'is_test' => 1,
        ]);
    }

    public function test_switching_to_live_deletes_unkept_test_rows_and_graduates_kept_ones(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        $keep = ClubTransaction::create([
            'tenant_id' => $club->id,
            'type' => 'income',
            'category' => 'subscription',
            'amount' => 100,
            'description' => 'Actually real',
            'transaction_date' => now(),
        ]);
        $discard = ClubTransaction::create([
            'tenant_id' => $club->id,
            'type' => 'income',
            'category' => 'subscription',
            'amount' => 20,
            'description' => 'Just testing',
            'transaction_date' => now(),
        ]);

        $this->assertTrue($keep->is_test);
        $this->assertTrue($discard->is_test);

        $this->actingAs($owner)
            ->postJson("/admin/club/{$club->slug}/financials/mode", [
                'mode' => 'live',
                'keep_transaction_ids' => [$keep->id],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('club_transactions', ['id' => $keep->id, 'is_test' => 0]);
        $this->assertDatabaseMissing('club_transactions', ['id' => $discard->id]);
        $this->assertFalse($club->fresh()->is_test_mode);
    }

    public function test_switching_to_test_mode_does_not_touch_existing_data(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $club->is_test_mode = false;
        $club->save();
        Tenant::forgetTestModeCache($club->id);
        $this->makeClubAdmin($owner, $club);

        $transaction = ClubTransaction::create([
            'tenant_id' => $club->id,
            'type' => 'income',
            'category' => 'subscription',
            'amount' => 30,
            'description' => 'Live income',
            'transaction_date' => now(),
        ]);
        $this->assertFalse($transaction->is_test);

        $this->actingAs($owner)
            ->postJson("/admin/club/{$club->slug}/financials/mode", ['mode' => 'test'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertTrue($club->fresh()->is_test_mode);
        $this->assertDatabaseHas('club_transactions', ['id' => $transaction->id, 'is_test' => 0]);
    }

    public function test_test_data_endpoint_lists_only_test_tagged_rows(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        ClubTransaction::create([
            'tenant_id' => $club->id,
            'type' => 'income',
            'category' => 'subscription',
            'amount' => 15,
            'description' => 'Test row',
            'transaction_date' => now(),
        ]);

        $this->actingAs($owner)
            ->getJson("/admin/club/{$club->slug}/financials/test-data")
            ->assertOk()
            ->assertJsonCount(1, 'transactions')
            ->assertJson(['total' => 1]);
    }
}
