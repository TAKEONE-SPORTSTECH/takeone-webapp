<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventMatch;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * Which flag an athlete flies at an event (owner, 2026-10-16 —
 * App\Events\Support\FlagSource): the club's country by default, their own
 * nationality when the organiser chooses it, and the club's again for anyone
 * with no nationality on file.
 */
class FlagSourceTest extends ContractTestCase
{
    public function test_the_organiser_switches_flags_between_club_country_and_nationality(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $club->forceFill(['country' => 'BH'])->save();
        $event = $this->championship($club, $organiser);
        $this->assertSame('nationality', $event->flag_source, 'a NEW event flies nationalities by default');
        // Model an event made before the setting existed: NULL = the club's country.
        $event->forceFill(['flag_source' => null])->save();
        \Illuminate\Support\Facades\Cache::flush();
        [$division] = $this->drawnDivision($event, $club);

        $regs = ClubEventRegistration::where('category_id', $division->id)->orderBy('id')->get();
        $regs->each(fn ($r) => $r->forceFill(['representing_tenant_id' => $club->id])->save());
        $saudi = $regs[0];
        $saudi->user->forceFill(['nationality' => 'SA'])->save();
        $noNationality = $regs[1];
        $noNationality->user->forceFill(['nationality' => null])->save();
        $before = EventMatch::where('event_id', $event->id)->orderBy('id')->get(['id', 'winner', 'match_no', 'a_competitor_id', 'b_competitor_id'])->toArray();

        // The default is the club's country, exactly as before the setting.
        $this->assertSame('BH', $saudi->fresh()->countryCode());

        $this->actingAs($organiser)->putJson(route('me.events.flag-source', $event->uuid), ['source' => 'nationality'])
            ->assertOk()->assertJson(['success' => true, 'flag_source' => 'nationality']);

        $this->assertSame('nationality', $event->fresh()->flag_source);
        $this->assertSame('SA', $saudi->fresh()->countryCode(), 'their own nationality');
        $this->assertSame('BH', $noNationality->fresh()->countryCode(), 'no nationality on file → the club');

        // The stored copy on the bout follows, and nothing else on the bout moves.
        $bout = EventMatch::where('event_id', $event->id)
            ->where(fn ($q) => $q->where('a_competitor_id', $saudi->id)->orWhere('b_competitor_id', $saudi->id))->first();
        $side = $bout->a_competitor_id === $saudi->id ? 'a' : 'b';
        $this->assertSame('SA', $bout->{$side.'_country'});
        $this->assertSame($before, EventMatch::where('event_id', $event->id)->orderBy('id')->get(['id', 'winner', 'match_no', 'a_competitor_id', 'b_competitor_id'])->toArray());

        // And back.
        $this->actingAs($organiser)->putJson(route('me.events.flag-source', $event->uuid), ['source' => 'club'])->assertOk();
        $this->assertSame('BH', $saudi->fresh()->countryCode());
        $this->assertSame('BH', $bout->fresh()->{$side.'_country'});
    }

    public function test_only_the_organiser_may_switch_and_only_to_a_known_value(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);

        $this->actingAs($this->createUser())->putJson(route('me.events.flag-source', $event->uuid), ['source' => 'nationality'])
            ->assertForbidden();
        $this->actingAs($organiser)->putJson(route('me.events.flag-source', $event->uuid), ['source' => 'passport'])
            ->assertStatus(422);
        $this->assertSame('nationality', $event->fresh()->flag_source, 'unchanged by the refused requests');
    }
}
