<?php

namespace Tests\Feature;

use App\Models\Business;
use Tests\TestCase;

/**
 * Regression coverage for ChainDashboardService::build() — every branch must
 * return the same array shape the controller/view expect (previously the
 * empty-clubs early return omitted 'revenue_chart', causing an "Undefined
 * array key" 500 for any approved business with zero linked clubs).
 */
class BusinessDashboardTest extends TestCase
{
    private function createApprovedBusiness($owner): Business
    {
        return Business::create([
            'owner_user_id' => $owner->id,
            'name' => 'Test Chain',
            'slug' => 'test-chain-'.uniqid(),
            'status' => Business::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function test_dashboard_renders_for_a_business_with_no_clubs(): void
    {
        $owner = $this->createUser();
        $this->createApprovedBusiness($owner);

        $this->actingAs($owner)
            ->get('/business/dashboard')
            ->assertOk()
            ->assertSee('Create Club');
    }

    public function test_mobile_dashboard_renders_for_a_business_with_no_clubs(): void
    {
        $owner = $this->createUser();
        $this->createApprovedBusiness($owner);

        $this->actingAs($owner)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36'])
            ->get('/business/dashboard')
            ->assertOk()
            ->assertSee('Create Club');
    }

    /**
     * The "Create Club" button dispatches `open-club-modal`; the only listener
     * lives on <x-club-modal>, which both dashboards teleport to <body>. Alpine
     * only initializes trees rooted at [x-data]/[x-init], so a bare
     * <template x-teleport> is never walked — the modal never renders and the
     * click is silently a no-op. Assert the teleport stays inside an x-data scope.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dashboardVariants')]
    public function test_create_club_modal_teleport_is_inside_an_alpine_scope(array $headers): void
    {
        $owner = $this->createUser();
        $this->createApprovedBusiness($owner);

        $html = $this->actingAs($owner)
            ->withHeaders($headers)
            ->get('/business/dashboard')
            ->assertOk()
            ->getContent();

        // The modal itself must be present...
        $this->assertStringContainsString('open-club-modal', $html);
        $this->assertStringContainsString('id="clubModal"', $html);

        // ...and the template teleporting it must have an x-data ancestor.
        $modalPos = strpos($html, 'id="clubModal"');
        $templatePos = strrpos(substr($html, 0, $modalPos), '<template');
        $this->assertNotFalse($templatePos, 'Expected the club modal to be wrapped in a teleport template.');

        $before = rtrim(substr($html, 0, $templatePos));
        $openingTag = substr($before, strrpos($before, '<'));

        $this->assertStringContainsString(
            'x-data',
            $openingTag,
            'The club-modal x-teleport template is not wrapped in an x-data scope — Alpine never walks it, '
            ."so the modal never renders and the Create Club button is a silent no-op. Enclosing tag was: {$openingTag}"
        );
    }

    public static function dashboardVariants(): array
    {
        return [
            'desktop' => [[]],
            'mobile' => [['User-Agent' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36']],
        ];
    }

    public function test_dashboard_renders_for_a_business_with_clubs(): void
    {
        $owner = $this->createUser();
        $business = $this->createApprovedBusiness($owner);
        $club = $this->createClub($owner, ['business_id' => $business->id]);

        $this->actingAs($owner)
            ->get('/business/dashboard')
            ->assertOk()
            ->assertSee($club->club_name);
    }
}
