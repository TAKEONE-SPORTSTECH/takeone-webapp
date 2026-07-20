<?php

namespace Tests\Feature;

use Tests\TestCase;

class ClubAdminMobileTest extends TestCase
{
    private function mobile()
    {
        return $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Mobile Safari/537.36',
        ]);
    }

    /**
     * The mobile club dashboard renders end to end, including the sections that
     * read live financial/member data (pending payments, recent activity, mix).
     */
    public function test_mobile_club_dashboard_renders_with_data(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        \App\Models\ClubTransaction::create([
            'tenant_id' => $club->id,
            'type' => 'income',
            'amount' => 120,
            'category' => 'subscription',
            'payment_method' => 'cash',
            'description' => 'Monthly subscription',
            'transaction_date' => now()->toDateString(),
        ]);

        $res = $this->actingAs($owner)->mobile()->get('/admin/club/'.$club->slug.'/dashboard');
        $res->assertOk();

        $res->assertSee(__('admin.dash_this_month'), false);
        $res->assertSee(__('admin.fin_pending_payments'), false);
        $res->assertSee(__('admin.dash_recent'), false);
        $res->assertSee('Monthly subscription', false);
    }

    /**
     * A package card's activity links to that discipline's public directory page
     * when the catalog knows it, and stays plain text when it doesn't.
     */
    public function test_package_card_links_activity_to_the_directory(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        $packageId = \Illuminate\Support\Facades\DB::table('club_packages')->insertGetId([
            'tenant_id' => $club->id, 'name' => 'Karate Plan', 'type' => 'single',
            'gender' => 'mixed', 'price' => 25, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $activityId = \Illuminate\Support\Facades\DB::table('club_activities')->insertGetId([
            'tenant_id' => $club->id, 'name' => 'Karate (Kyokushin)', 'created_at' => now(), 'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('club_package_activities')->insert([
            'package_id' => $packageId, 'activity_id' => $activityId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // No catalog entry yet → plain label, no link.
        $this->actingAs($owner)->mobile()->get('/admin/club/'.$club->slug.'/packages')
            ->assertOk()
            ->assertDontSee('/activity/', false);

        $entry = \App\Models\ActivityCatalog::contribute(['name' => 'Karate (Kyokushin)']);

        foreach ([true, false] as $isMobile) {
            $req = $isMobile ? $this->actingAs($owner)->mobile() : $this->actingAs($owner);
            $req->get('/admin/club/'.$club->slug.'/packages')
                ->assertOk()
                ->assertSee(route('activity.show', $entry->uuid), false);
        }
    }

    /**
     * The coach shown on a package slot opens their trainer profile — bound by
     * USER id, which is what `trainer.show` expects (not the ClubInstructor id).
     */
    public function test_package_card_links_the_coach_to_their_trainer_page(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $coach = $this->createUser(['full_name' => 'Coach Nedhal']);

        $instructor = \App\Models\ClubInstructor::create([
            'tenant_id' => $club->id, 'user_id' => $coach->id, 'role' => 'Head coach',
        ]);
        $packageId = \Illuminate\Support\Facades\DB::table('club_packages')->insertGetId([
            'tenant_id' => $club->id, 'name' => 'Karate Plan', 'type' => 'single',
            'gender' => 'mixed', 'price' => 25, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $activityId = \Illuminate\Support\Facades\DB::table('club_activities')->insertGetId([
            'tenant_id' => $club->id, 'name' => 'Karate', 'created_at' => now(), 'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('club_package_activities')->insert([
            'package_id' => $packageId, 'activity_id' => $activityId, 'instructor_id' => $instructor->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($owner)->mobile()->get('/admin/club/'.$club->slug.'/packages')
            ->assertOk()
            ->assertSee(route('trainer.show', $coach->id), false);

        // Same two links on the PUBLIC club page (mobile + desktop), which renders
        // the package cards from its own component.
        $entry = \App\Models\ActivityCatalog::contribute(['name' => 'Karate']);
        $url = '/'.strtolower($club->country ?: 'bh').'/clubs/'.$club->slug;

        foreach ([true, false] as $isMobile) {
            $req = $isMobile ? $this->actingAs($owner)->mobile() : $this->actingAs($owner);
            $req->get($url)
                ->assertOk()
                ->assertSee(route('activity.show', $entry->uuid), false)
                ->assertSee(route('trainer.show', $coach->id), false);
        }
    }

    public function test_mobile_club_details_renders_every_editable_section(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $res = $this->actingAs($owner)->mobile()->get('/admin/club/'.$club->slug.'/details');

        if ($res->status() !== 200) {
            dump($res->status(), substr(strip_tags($res->getContent()), 0, 3000));
        }
        $res->assertOk();

        foreach ([
            'clubStudioForm',
            'name="club_name"', 'name="slogan"', 'name="description"',
            'name="translations[club_name][ar]"',
            'name="registration_fee"', 'name="enrollment_fee"', 'name="vat_percentage"',
            'name="commercial_reg_number"', 'name="vat_reg_number"',
            'name="email"', 'name="phone_code"', 'name="phone_number"',
            'name="country"', 'name="currency"', 'name="timezone"', 'name="slug"',
            'name="address"', 'name="gps_lat"', 'name="gps_long"', 'name="maps_url"',
            'name="logo"', 'name="favicon"', 'name="cover_image"',
            'name="registration_splash_image"',
            'name="registration_requirements"', 'name="registration_terms"',
            'name="settings[member_code_prefix]"', 'name="settings[block_explore]"',
            'csOwner', 'csWhatsApp',
        ] as $needle) {
            $res->assertSee($needle, false);
        }
    }

    public function test_mobile_form_payload_saves(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $this->actingAs($owner)->mobile()
             ->put('/admin/club/'.$club->slug, [
                 'club_name'    => 'Studio Club',
                 'slogan'       => 'Move better',
                 'description'  => 'A test club.',
                 'email'        => 'hello@example.com',
                 'phone_code'   => '+973',
                 'phone_number' => '39990000',
                 'gps_lat'      => 26.2285,
                 'gps_long'     => 50.5860,
                 'address'      => 'Manama',
                 'settings'     => ['member_code_prefix' => 'STU', 'block_explore' => '1'],
                 'social_links' => [['platform' => 'instagram', 'url' => 'https://instagram.com/x']],
                 'translations' => ['club_name' => ['ar' => 'نادي']],
             ])
             ->assertRedirect();

        $club->refresh();
        $this->assertSame('Studio Club', $club->club_name);
        $this->assertSame('39990000', $club->phone['number']);
        $this->assertSame('STU', $club->settings['member_code_prefix']);
        $this->assertSame(1, $club->socialLinks()->count());
    }

    public function test_mobile_roles_access_sheet_renders_inline_role_cards(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $res = $this->actingAs($owner)->mobile()->get('/admin/club/'.$club->slug.'/roles');
        $res->assertOk();
        // Inline option cards replaced the clipped pop-over dropdown.
        $res->assertSee('roleIcon(r.slug)', false);
        $res->assertSee('toggleGroupOpen(g.label)', false);
        $res->assertSee('selectAll()', false);
        $res->assertDontSee('roleOpen', false);
    }

    public function test_mobile_roles_page_uses_hub_and_drilldown_panels(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $res = $this->actingAs($owner)->mobile()->get('/admin/club/'.$club->slug.'/roles');
        $res->assertOk();

        // Hub + panels
        $res->assertSee('rolesHub()', false);
        $res->assertSee("open('team')", false);
        $res->assertSee("open('types')", false);
        $res->assertSee("panel === 'team'", false);
        $res->assertSee("panel === 'types'", false);
        // Team filtering + live-update contract preserved
        $res->assertSee('applyTeamFilter()', false);
        $res->assertSee('teamRowHtml', false);
        $res->assertSee('roleRowHtml', false);
        $res->assertSee('syncRoleStats', false);
    }

    public function test_mobile_financials_page_uses_hub_and_exposes_desktop_sections(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $res = $this->actingAs($owner)->mobile()->get('/admin/club/'.$club->slug.'/financials');
        $res->assertOk();

        $res->assertSee('financialsHub()', false);
        foreach (['ledger', 'collect', 'trends', 'expenses', 'recurring', 'reports'] as $panel) {
            $res->assertSee("open('{$panel}')", false);
            $res->assertSee("panel === '{$panel}'", false);
        }
        // Live KPI patching replaced the old full-page reloads.
        $res->assertSee('applyFinancials', false);
        // ...and the view itself no longer reloads after a write.
        $this->assertStringNotContainsString(
            'window.location.reload',
            file_get_contents(resource_path('views/admin/club/financials/mobile.blade.php'))
        );
        // Recurring management is reachable from mobile now.
        $res->assertSee('toggleRecurring(', false);
        $res->assertSee('deleteRecurring(', false);
    }

    /**
     * The record-transaction sheet is amount-first and fully custom: no native
     * <select> and no native date input anywhere in it (Design Rule #4).
     */
    public function test_mobile_transaction_sheet_uses_custom_controls(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        $markup = file_get_contents(resource_path('views/admin/club/financials/mobile.blade.php'));
        $this->assertStringNotContainsString('<select', $markup);
        $this->assertStringNotContainsString('type="date"', $markup);
        $this->assertStringContainsString('<x-date-picker', $markup);

        $res = $this->actingAs($owner)->mobile()->get('/admin/club/'.$club->slug.'/financials');
        $res->assertOk();
        // Calendar trigger + the category chips that replaced the dropdown.
        $res->assertSee('bi-calendar3', false);
        $res->assertSee("tx.category = (tx.category === c.v ? '' : c.v)", false);
        // The amount lives inside the form, not linked to it by a duplicated id.
        $res->assertDontSee('id="tx-form"', false);
    }

    public function test_recurring_expense_toggle_answers_json_for_ajax(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        $expense = \App\Models\ClubRecurringExpense::create([
            'tenant_id' => $club->id, 'description' => 'Rent', 'amount' => 100,
            'category' => 'rent', 'payment_method' => 'cash', 'day_of_month' => 1, 'is_active' => true,
        ]);

        $this->actingAs($owner)
             ->patchJson('/admin/club/'.$club->slug.'/financials/recurring/'.$expense->id.'/toggle')
             ->assertOk()
             ->assertJson(['success' => true, 'is_active' => false]);

        $this->actingAs($owner)
             ->deleteJson('/admin/club/'.$club->slug.'/financials/recurring/'.$expense->id)
             ->assertOk()
             ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('club_recurring_expenses', ['id' => $expense->id]);
    }

    public function test_unposted_recurring_wage_is_surfaced_instead_of_silently_missing(): void
    {
        $owner = $this->createUser();
        $club  = $this->createClub($owner);

        // A staff wage lives as a RULE until the daily job posts it — so it is not in
        // the expense totals, but it must not be invisible either.
        \App\Models\ClubRecurringExpense::create([
            'tenant_id' => $club->id, 'description' => 'Instructor wage — Sam', 'amount' => 250,
            'category' => 'salaries', 'payment_method' => 'bank_transfer',
            'day_of_month' => 28, 'is_active' => true, 'last_run_at' => null,
        ]);

        $res = $this->actingAs($owner)->mobile()->get('/admin/club/'.$club->slug.'/financials');
        $res->assertOk();

        $res->assertSee(__('admin.fin_committed_monthly'));
        $res->assertSee(__('admin.fin_recurring_not_counted'));
        $res->assertSee(__('admin.fin_posts_on', ['day' => 28]));
        // The committed amount is shown even though posted expenses are still zero.
        $res->assertSee('250');
    }
}
