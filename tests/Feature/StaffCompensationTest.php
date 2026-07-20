<?php

namespace Tests\Feature;

use App\Models\ClubInstructor;
use App\Models\ClubRecurringExpense;
use App\Models\ClubTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Staff (instructor + generalized secretary/operator/cleaner/other) compensation:
 * paid+monthly wages auto-post to the ledger via the recurring-expense engine, and
 * terminating a staff member posts a pro-rated final settlement instead of just
 * deleting the record.
 */
class StaffCompensationTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    public function test_creating_paid_monthly_staff_creates_a_recurring_expense(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $staffUser = $this->createUser(['full_name' => 'Sam Secretary']);

        $this->actingAs($owner)
            ->post("/admin/club/{$club->slug}/instructors", [
                'creation_type' => 'existing',
                'selected_member_id' => $staffUser->id,
                'specialty_existing' => 'Front Desk',
                'staff_type' => 'secretary',
                'compensation_type' => 'paid',
                'wage_amount' => 250,
                'wage_period' => 'monthly',
            ])
            ->assertRedirect();

        $instructor = ClubInstructor::where('tenant_id', $club->id)->where('user_id', $staffUser->id)->firstOrFail();
        $this->assertSame('secretary', $instructor->staff_type);

        $this->assertDatabaseHas('club_recurring_expenses', [
            'tenant_id' => $club->id,
            'instructor_id' => $instructor->id,
            'category' => 'salaries',
            'is_active' => true,
        ]);
        $recurring = ClubRecurringExpense::where('instructor_id', $instructor->id)->firstOrFail();
        $this->assertEquals(250, (float) $recurring->amount);

        // Switching to volunteer pauses (does not delete) the recurring rule.
        $this->actingAs($owner)
            ->put("/admin/club/{$club->slug}/instructors/{$instructor->id}", [
                'role' => 'Front Desk',
                'staff_type' => 'secretary',
                'compensation_type' => 'volunteer',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('club_recurring_expenses', [
            'id' => $recurring->id,
            'is_active' => false,
        ]);
    }

    public function test_converting_long_time_volunteer_to_paid_only_settles_since_conversion(): void
    {
        Carbon::setTestNow('2024-01-01');

        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $staffUser = $this->createUser();
        $instructor = ClubInstructor::create([
            'tenant_id' => $club->id,
            'user_id' => $staffUser->id,
            'role' => 'Cleaner',
            'staff_type' => 'cleaner',
            'compensation_type' => ClubInstructor::COMPENSATION_VOLUNTEER,
        ]);

        // Two-and-a-half years as a volunteer, then converted to paid.
        Carbon::setTestNow('2026-06-20');
        $this->actingAs($owner)
            ->put("/admin/club/{$club->slug}/instructors/{$instructor->id}", [
                'role' => 'Cleaner',
                'staff_type' => 'cleaner',
                'compensation_type' => 'paid',
                'wage_amount' => 300, // 300 / 30 days in June = 10/day
                'wage_period' => 'monthly',
            ])
            ->assertRedirect();

        $instructor->refresh();
        $this->assertNotNull($instructor->paid_since);
        $this->assertTrue($instructor->paid_since->isSameDay(Carbon::parse('2026-06-20')));

        // Becoming paid on the 20th posts June's wage pro-rated to the 11 days it
        // covers (20th–30th), NOT backdated to their 2024 hire/volunteer start date.
        $this->assertDatabaseHas('club_transactions', [
            'tenant_id' => $club->id,
            'instructor_id' => $instructor->id,
            'category' => 'salaries',
            'amount' => '110.00',
        ]);

        // Terminated on the 30th: the posted wage already covers every worked day, so
        // there is no extra settlement to pay on top of it.
        Carbon::setTestNow('2026-06-30');

        $this->actingAs($owner)
            ->deleteJson("/admin/club/{$club->slug}/instructors/{$instructor->id}")
            ->assertOk()
            ->assertJson(['success' => true, 'settlement_amount' => 0]);

        $this->assertSame(
            110.0,
            (float) ClubTransaction::where('instructor_id', $instructor->id)->sum('amount')
        );
    }

    public function test_process_recurring_command_posts_transaction_linked_to_staff(): void
    {
        Carbon::setTestNow('2026-05-15');

        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $staffUser = $this->createUser();
        $instructor = ClubInstructor::create([
            'tenant_id' => $club->id,
            'user_id' => $staffUser->id,
            'role' => 'Operator',
            'staff_type' => 'operator',
            'compensation_type' => ClubInstructor::COMPENSATION_PAID,
            'wage_amount' => 400,
            'wage_period' => 'monthly',
        ]);

        ClubRecurringExpense::create([
            'tenant_id' => $club->id,
            'instructor_id' => $instructor->id,
            'description' => 'Operator wage — Test',
            'amount' => 400,
            'category' => 'salaries',
            'payment_method' => 'bank_transfer',
            'day_of_month' => 15,
            'is_active' => true,
        ]);

        Artisan::call('expenses:process-recurring');

        $this->assertDatabaseHas('club_transactions', [
            'tenant_id' => $club->id,
            'instructor_id' => $instructor->id,
            'user_id' => $staffUser->id,
            'category' => 'salaries',
            'type' => 'expense',
        ]);
    }

    public function test_terminating_paid_monthly_staff_posts_prorated_settlement_from_hire_date(): void
    {
        Carbon::setTestNow('2026-03-01');

        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $staffUser = $this->createUser();
        $instructor = ClubInstructor::create([
            'tenant_id' => $club->id,
            'user_id' => $staffUser->id,
            'role' => 'Cleaner',
            'staff_type' => 'cleaner',
            'compensation_type' => ClubInstructor::COMPENSATION_PAID,
            'wage_amount' => 310, // 310 / 31 days in March = 10/day exactly
            'wage_period' => 'monthly',
        ]);

        // 10 days later, never paid yet — settlement covers hire date through today (11 days).
        Carbon::setTestNow('2026-03-11');

        $this->actingAs($owner)
            ->deleteJson("/admin/club/{$club->slug}/instructors/{$instructor->id}")
            ->assertOk()
            ->assertJson(['success' => true, 'settlement_amount' => 110.0]);

        $this->assertDatabaseHas('club_transactions', [
            'tenant_id' => $club->id,
            'instructor_id' => $instructor->id,
            'category' => 'salaries',
            'type' => 'expense',
            'amount' => 110.00,
        ]);
        $this->assertDatabaseHas('club_instructors', ['id' => $instructor->id, 'is_active' => false]);
    }

    public function test_terminating_paid_monthly_staff_prorates_from_last_payment_not_hire_date(): void
    {
        Carbon::setTestNow('2026-04-01');

        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $staffUser = $this->createUser();
        $instructor = ClubInstructor::create([
            'tenant_id' => $club->id,
            'user_id' => $staffUser->id,
            'role' => 'Secretary',
            'staff_type' => 'secretary',
            'compensation_type' => ClubInstructor::COMPENSATION_PAID,
            'wage_amount' => 280, // 280 / 30 days in April = 9.3333/day
            'wage_period' => 'monthly',
        ]);

        $recurring = ClubRecurringExpense::create([
            'tenant_id' => $club->id,
            'instructor_id' => $instructor->id,
            'description' => 'Secretary wage — Test',
            'amount' => 280,
            'category' => 'salaries',
            'payment_method' => 'bank_transfer',
            'day_of_month' => 1,
            'is_active' => true,
            'last_run_at' => '2026-04-01', // already paid through April 1st
        ]);

        // 10 days later: unpaid stretch is Apr 2 .. Apr 11 = 10 days.
        Carbon::setTestNow('2026-04-11');

        $expectedAmount = round((280 / 30) * 10, 2);

        $this->actingAs($owner)
            ->deleteJson("/admin/club/{$club->slug}/instructors/{$instructor->id}")
            ->assertOk()
            ->assertJson(['success' => true, 'settlement_amount' => $expectedAmount]);

        $this->assertDatabaseHas('club_transactions', [
            'tenant_id' => $club->id,
            'instructor_id' => $instructor->id,
            'category' => 'salaries',
            'amount' => number_format($expectedAmount, 2, '.', ''),
        ]);
        $this->assertDatabaseHas('club_recurring_expenses', ['id' => $recurring->id, 'is_active' => false]);
    }

    /**
     * A wage is an expense of the month it covers: hiring on the 3rd for a wage due
     * on the 25th must weigh on this month's net immediately, not from payday.
     */
    public function test_hiring_paid_monthly_staff_posts_the_wage_for_the_current_month(): void
    {
        Carbon::setTestNow('2026-05-03');

        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $staffUser = $this->createUser();

        $this->actingAs($owner)
            ->post("/admin/club/{$club->slug}/instructors", [
                'creation_type' => 'existing',
                'selected_member_id' => $staffUser->id,
                'specialty_existing' => 'Front Desk',
                'staff_type' => 'secretary',
                'compensation_type' => 'paid',
                'wage_amount' => 500,
                'wage_period' => 'monthly',
            ])
            ->assertRedirect();

        $instructor = ClubInstructor::where('tenant_id', $club->id)->where('user_id', $staffUser->id)->firstOrFail();

        $this->assertDatabaseHas('club_transactions', [
            'tenant_id' => $club->id,
            'instructor_id' => $instructor->id,
            'type' => 'expense',
            'category' => 'salaries',
            'amount' => 467.74, // 500 pro-rated over the 29 days of May it covers (3rd–31st)
        ]);

        $summary = app(\App\Services\FinancialService::class)->getSummary(
            $club->id,
            ClubTransaction::where('tenant_id', $club->id)->get(),
            (bool) $club->is_test_mode
        );
        $this->assertSame(-467.74, (float) $summary['net_profit']);
    }

    public function test_pausing_a_wage_prorates_the_month_already_posted(): void
    {
        Carbon::setTestNow('2026-05-01');

        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $staffUser = $this->createUser();

        $this->actingAs($owner)->post("/admin/club/{$club->slug}/instructors", [
            'creation_type' => 'existing',
            'selected_member_id' => $staffUser->id,
            'specialty_existing' => 'Front Desk',
            'staff_type' => 'secretary',
            'compensation_type' => 'paid',
            'wage_amount' => 310, // 310 / 31 days in May = 10/day
            'wage_period' => 'monthly',
        ]);

        $instructor = ClubInstructor::where('tenant_id', $club->id)->where('user_id', $staffUser->id)->firstOrFail();
        $recurring = ClubRecurringExpense::where('instructor_id', $instructor->id)->firstOrFail();

        // Paused on the 10th: the club should carry 10 days of wage, not the full month.
        Carbon::setTestNow('2026-05-10');
        $this->actingAs($owner)
            ->patchJson("/admin/club/{$club->slug}/financials/recurring/{$recurring->id}/toggle")
            ->assertOk();

        $this->assertDatabaseHas('club_transactions', [
            'recurring_expense_id' => $recurring->id,
            'amount' => '100.00',
        ]);
    }

    public function test_terminating_volunteer_staff_creates_no_settlement(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $staffUser = $this->createUser();
        $instructor = ClubInstructor::create([
            'tenant_id' => $club->id,
            'user_id' => $staffUser->id,
            'role' => 'Volunteer Coach',
            'staff_type' => 'instructor',
            'compensation_type' => ClubInstructor::COMPENSATION_VOLUNTEER,
        ]);

        $transactionsBefore = ClubTransaction::count();

        $this->actingAs($owner)
            ->deleteJson("/admin/club/{$club->slug}/instructors/{$instructor->id}")
            ->assertOk()
            ->assertJson(['success' => true, 'settlement_amount' => 0.0]);

        $this->assertSame($transactionsBefore, ClubTransaction::count());
        $this->assertDatabaseHas('club_instructors', ['id' => $instructor->id, 'is_active' => false]);
    }
}
