<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Support\CategorySystems;
use App\Members\Models\User;
use App\Sports\Combat\SportRegistry;
use Tests\TestCase;

/**
 * The rule book an event runs under (App\Events\Support\CategorySystems).
 *
 * What this protects: an event that never chose is classified exactly as it
 * always was; a chosen system really decides the category; changing it
 * re-sorts everybody entered — but never once a draw exists; and only the
 * people who run a host may save systems for it or change an event's.
 */
class CategorySystemsTest extends TestCase
{
    private function club(User $owner): Tenant
    {
        return $this->createClub($owner, ['country' => 'BH']);
    }

    private function karateEvent(Tenant $club, User $owner, array $attrs = []): ClubEvent
    {
        // The rule book is never mass-assignable — it is set on purpose.
        $rulebook = array_intersect_key($attrs, array_flip(['category_system', 'belt_system']));
        $attrs = array_diff_key($attrs, $rulebook);

        return tap(ClubEvent::create(array_merge([
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Karate Open', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(3)->toDateString(), 'start_time' => '09:00',
        ], $attrs)), fn ($e) => $rulebook ? $e->forceFill($rulebook)->save() : null);
    }

    private function athlete(int $age, string $gender = 'Male'): User
    {
        return $this->createUser(['gender' => $gender, 'birthdate' => now()->subYears($age)->subMonths(2)->toDateString()]);
    }

    public function test_an_event_that_chose_nothing_is_classified_as_before(): void
    {
        $owner = $this->createUser();
        $event = $this->karateEvent($this->club($owner), $owner);
        $karate = app(SportRegistry::class)->get('karate');

        $this->assertSame($karate, app(CategorySystems::class)->sportFor($karate, $event));
        $this->assertSame('Cadet', $karate->classify('male', 13, 42)['age_group']);
    }

    public function test_the_chosen_system_decides_the_category(): void
    {
        $owner = $this->createUser();
        $event = $this->karateEvent($this->club($owner), $owner, ['category_system' => 'shotokan']);
        $sport = app(CategorySystems::class)->sportFor(app(SportRegistry::class)->get('karate'), $event);

        $class = $sport->classify('male', 13, 42);
        $this->assertSame('Minimes', $class['age_group']);
        $this->assertSame('-45', $class['category']);
    }

    public function test_changing_it_re_sorts_everyone_entered(): void
    {
        $owner = $this->createUser();
        $event = $this->karateEvent($this->club($owner), $owner);
        $cadet = EventCategory::create(['event_id' => $event->id, 'name' => 'Cadet Men -52 kg', 'gender' => 'male', 'min_weight' => 0, 'max_weight' => 52, 'sort_order' => 1]);
        $a = $this->athlete(13);
        $reg = ClubEventRegistration::create(['event_id' => $event->id, 'user_id' => $a->id, 'role' => 'participant', 'status' => 'joined',
            'weight' => 42, 'category_id' => $cadet->id, 'category_source' => ClubEventRegistration::PLACED_AUTO]);

        $r = app(CategorySystems::class)->change($event, 'shotokan', null);

        $this->assertTrue($r['ok']);
        $this->assertSame('shotokan', $event->fresh()->category_system);
        $placed = $reg->fresh()->category;
        $this->assertNotNull($placed);
        $this->assertStringContainsString('Minimes', $placed->name);
        $this->assertStringContainsString('-45', $placed->name);
    }

    public function test_a_person_s_own_placement_is_kept(): void
    {
        $owner = $this->createUser();
        $event = $this->karateEvent($this->club($owner), $owner);
        $chosen = EventCategory::create(['event_id' => $event->id, 'name' => 'Open Men', 'sort_order' => 1]);
        $reg = ClubEventRegistration::create(['event_id' => $event->id, 'user_id' => $this->athlete(13)->id, 'role' => 'participant', 'status' => 'joined',
            'weight' => 42, 'category_id' => $chosen->id, 'category_source' => ClubEventRegistration::PLACED_MANUAL]);

        app(CategorySystems::class)->change($event, 'shotokan', null);

        $this->assertSame($chosen->id, $reg->fresh()->category_id);
    }

    public function test_it_cannot_change_once_a_draw_exists(): void
    {
        $owner = $this->createUser();
        $event = $this->karateEvent($this->club($owner), $owner);
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Cadet Men -52 kg', 'sort_order' => 1]);
        \App\Events\Models\EventMatch::forceCreate(['event_id' => $event->id, 'category_id' => $cat->id, 'round' => 1, 'match_no' => 1]);

        $r = app(CategorySystems::class)->change($event, 'shotokan', null);

        $this->assertFalse($r['ok']);
        $this->assertNull($event->fresh()->category_system);
    }

    public function test_only_the_host_s_people_save_systems_or_change_an_event_s(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->karateEvent($club, $owner);
        $stranger = $this->createUser();
        $payload = ['sport' => 'karate', 'kind' => 'weights', 'label' => 'Kyokushin', 'host' => 'club:'.$club->id,
            'data' => ['age_groups' => [['key' => 'Open', 'min' => 18, 'max' => null]], 'classes' => ['Open' => ['male' => [['label' => '-70', 'min' => 0, 'max' => 70]]]]]];

        $this->actingAs($stranger)->postJson(route('me.category-systems.store'), $payload)->assertForbidden();
        $this->actingAs($stranger)->putJson(route('me.events.rulebook', $event->uuid), ['category_system' => 'shotokan'])->assertForbidden();
        $this->assertNull($event->fresh()->category_system);

        $saved = $this->actingAs($owner->fresh())->postJson(route('me.category-systems.store'), $payload)->assertOk()->json('system');
        $this->assertTrue($saved['custom']);

        // The owner may now run their event under it; a key from nowhere is refused.
        $this->actingAs($owner->fresh())->putJson(route('me.events.rulebook', $event->uuid), ['category_system' => $saved['key']])->assertOk();
        $this->assertSame($saved['key'], $event->fresh()->category_system);
        $this->actingAs($owner->fresh())->putJson(route('me.events.rulebook', $event->uuid), ['category_system' => 'custom:not-real'])->assertStatus(422);

        // Another club cannot use the first club's custom system.
        $other = $this->karateEvent($this->club($stranger), $stranger);
        $this->assertFalse(app(CategorySystems::class)->validWeights($other, $saved['key']));
    }

    public function test_mcp_mirrors_the_same_rules(): void
    {
        $owner = $this->createUser();
        $event = $this->karateEvent($this->club($owner), $owner);
        $call = function (User $as, array $args) {
            $this->actingAs($as);
            $text = (string) (new \App\Mcp\Tools\SetEventRulebookTool)->handle(new \Laravel\Mcp\Request($args))->content();

            return json_decode($text, true) ?? ['error' => $text];
        };

        $this->assertArrayHasKey('error', $call($this->createUser(), ['event' => $event->uuid, 'category_system' => 'shotokan']));
        $this->assertNull($event->fresh()->category_system);

        $list = $call($owner->fresh(), ['event' => $event->uuid]);
        $this->assertContains('shotokan', array_column($list['category_systems'], 'key'));

        $done = $call($owner->fresh(), ['event' => $event->uuid, 'category_system' => 'shotokan']);
        $this->assertSame('shotokan', $done['category_system']);
    }
}
