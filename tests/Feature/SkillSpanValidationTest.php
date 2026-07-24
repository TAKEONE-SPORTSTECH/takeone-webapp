<?php

namespace Tests\Feature;

use App\Models\ClubAffiliation;
use App\Models\SkillAcquisition;
use App\Models\User;
use Tests\TestCase;

/**
 * A skill belongs to an affiliation, so it cannot predate it or outlast it, and it
 * must carry a span — expressed EITHER as an end date OR as a number of months.
 * The modal mirrors these bounds as affordances; this is the enforcement.
 */
class SkillSpanValidationTest extends TestCase
{
    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $member = $this->createUser();
        $affiliation = ClubAffiliation::create([
            'member_id' => $member->id,
            'tenant_id' => null,
            'club_name' => 'Test Club',
            'start_date' => '2026-01-15',
        ]);
        $this->ctx = ['member' => $member, 'affiliation' => $affiliation];
    }

    private function submit(array $overrides = [])
    {
        $member = $this->ctx['member'];
        $affiliation = $this->ctx['affiliation'];

        return $this->actingAs($member)->postJson(
            "/member/{$member->id}/affiliations/{$affiliation->id}/skills",
            array_merge([
                'skill_name' => 'Boxing',
                'activity_name' => 'Boxing',
                'proficiency_level' => 'intermediate',
                'duration_months' => 6,
            ], $overrides)
        );
    }

    public function test_a_start_date_before_the_affiliation_is_rejected(): void
    {
        $this->submit(['start_date' => '2025-12-31'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    public function test_a_start_date_on_the_affiliation_start_is_accepted(): void
    {
        $this->submit(['start_date' => '2026-01-15'])->assertOk();
        $this->assertDatabaseHas('skill_acquisitions', ['skill_name' => 'Boxing']);
    }

    public function test_a_future_start_date_is_rejected(): void
    {
        $this->submit(['start_date' => now()->addDay()->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    public function test_a_skill_needs_either_an_end_date_or_a_duration(): void
    {
        $this->submit(['duration_months' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['duration_months', 'end_date']);
    }

    public function test_an_end_date_alone_is_enough(): void
    {
        $this->submit([
            'duration_months' => null,
            'start_date' => '2026-01-15',
            'end_date' => '2026-07-15',
        ])->assertOk();

        // formatted_duration reads duration_months, so it is derived from the span.
        $skill = SkillAcquisition::where('skill_name', 'Boxing')->firstOrFail();
        $this->assertSame('2026-07-15', $skill->end_date->toDateString());
        $this->assertSame(6, $skill->duration_months);
    }

    public function test_an_end_date_requires_a_start_date(): void
    {
        $this->submit(['duration_months' => null, 'end_date' => '2026-07-15'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    public function test_an_end_date_before_the_start_is_rejected(): void
    {
        $this->submit([
            'duration_months' => null,
            'start_date' => '2026-06-01',
            'end_date' => '2026-03-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('end_date');
    }

    public function test_a_skill_cannot_outlast_a_closed_affiliation(): void
    {
        $this->ctx['affiliation']->update(['end_date' => '2026-06-30']);

        $this->submit([
            'duration_months' => null,
            'start_date' => '2026-02-01',
            'end_date' => '2026-09-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('end_date');
    }

    public function test_a_skill_cannot_start_after_a_closed_affiliation_ended(): void
    {
        $this->ctx['affiliation']->update(['end_date' => '2026-06-30']);

        $this->submit(['start_date' => '2026-07-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    /**
     * club_affiliations.start_date is NOT NULL, so the lower bound always applies —
     * there is no "affiliation without a start date" case to fall back on. Pinned
     * because the controller keeps a null-guard for it; if the column ever becomes
     * nullable, this test is where that decision gets revisited.
     */
    public function test_every_affiliation_has_a_start_date_so_the_bound_always_applies(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->ctx['affiliation']->update(['start_date' => null]);
    }

    public function test_the_span_is_omitted_from_the_error_when_one_of_the_two_is_given(): void
    {
        // Supplying a duration must not also demand an end date, and vice versa.
        $this->submit(['duration_months' => 3])->assertOk();
    }
}
