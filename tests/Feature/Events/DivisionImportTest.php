<?php

namespace Tests\Feature\Events;

use App\Events\Models\EventCategory;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * Divisions from a spreadsheet (App\Events\Support\DivisionImport): the
 * template, the preview that writes nothing, and the import. Organiser only.
 */
class DivisionImportTest extends ContractTestCase
{
    private function csv(string $body): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'div').'.csv';
        file_put_contents($path, $body);

        return new UploadedFile($path, 'divisions.csv', 'text/csv', null, true);
    }

    public function test_the_template_downloads_as_a_spreadsheet(): void
    {
        $organiser = $this->createUser();
        $event = $this->championship($this->clubFor($organiser), $organiser);

        $res = $this->actingAs($organiser)->get(route('me.events.divisions.import.template', $event->uuid))->assertOk();
        $this->assertStringStartsWith('PK', $res->getContent(), 'an .xlsx is a zip');
    }

    public function test_preview_judges_rows_and_writes_nothing(): void
    {
        $organiser = $this->createUser();
        $event = $this->championship($this->clubFor($organiser), $organiser);
        $before = EventCategory::where('event_id', $event->id)->count();

        $file = $this->csv("Gender,Min Age,Max Age,Min Weight,Max Weight,People Count\n"
            ."male,5,6,17,23,2\nmix,8,9,34.8,39.8,4\npurple,8,9,20,30,1\nfemale,13,16,48.3,45.4,2\n");

        $res = $this->actingAs($organiser)->post(route('me.events.divisions.import.preview', $event->uuid), ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('ready', 2)->assertJsonPath('ignored', ['People Count']);

        $this->assertSame(['ready', 'ready', 'error', 'error'], array_column($res->json('rows'), 'status'));
        $this->assertSame('Male', $res->json('rows.0.gender'));
        $this->assertNull($res->json('rows.1.gender'), 'mix is a mixed group');
        $this->assertSame($before, EventCategory::where('event_id', $event->id)->count());
    }

    public function test_import_names_by_group_letter_only_and_skips_what_exists(): void
    {
        $organiser = $this->createUser();
        $event = $this->championship($this->clubFor($organiser), $organiser);
        EventCategory::where('event_id', $event->id)->delete();

        $rows = [
            ['line' => 2, 'name' => '', 'gender' => 'Male', 'min_age' => 5, 'max_age' => 6, 'min_weight' => 17, 'max_weight' => 23],
            ['line' => 3, 'name' => 'My Own', 'gender' => null, 'min_age' => 8, 'max_age' => 9, 'min_weight' => 34.8, 'max_weight' => 39.8],
        ];
        $this->actingAs($organiser)->postJson(route('me.events.divisions.import', $event->uuid), ['rows' => $rows])
            ->assertOk()->assertJsonCount(2, 'divisions');

        $made = EventCategory::where('event_id', $event->id)->orderBy('sort_order')->get();
        $this->assertSame(['Group A', 'My Own'], $made->pluck('name')->all(), 'no age word, no weight in a generated name');
        $this->assertSame([5, 6, 17.0, 23.0], [$made[0]->min_age, $made[0]->max_age, (float) $made[0]->min_weight, (float) $made[0]->max_weight]);
        $this->assertNull($made[1]->gender);

        // The same rows again: both already exist, nothing is added.
        $this->actingAs($organiser)->postJson(route('me.events.divisions.import', $event->uuid), ['rows' => $rows])
            ->assertOk()->assertJsonCount(0, 'divisions');
        $this->assertSame(2, EventCategory::where('event_id', $event->id)->count());
    }

    public function test_one_bad_row_creates_nothing(): void
    {
        $organiser = $this->createUser();
        $event = $this->championship($this->clubFor($organiser), $organiser);
        $before = EventCategory::where('event_id', $event->id)->count();

        $this->actingAs($organiser)->postJson(route('me.events.divisions.import', $event->uuid), ['rows' => [
            ['name' => '', 'gender' => 'Male', 'min_age' => 5, 'max_age' => 6, 'min_weight' => 60, 'max_weight' => 61],
            ['name' => '', 'gender' => 'Male', 'min_age' => 9, 'max_age' => 4],
        ]])->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame($before, EventCategory::where('event_id', $event->id)->count());
    }

    public function test_nobody_else_may_use_it(): void
    {
        $organiser = $this->createUser();
        $event = $this->championship($this->clubFor($organiser), $organiser);
        $stranger = $this->createUser();
        $before = EventCategory::where('event_id', $event->id)->count();

        $this->actingAs($stranger)->getJson(route('me.events.divisions.import.template', $event->uuid))->assertForbidden();
        $this->actingAs($stranger)->post(route('me.events.divisions.import.preview', $event->uuid),
            ['file' => $this->csv("Gender,Min Age\nmale,5\n")], ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($stranger)->postJson(route('me.events.divisions.import', $event->uuid), ['rows' => [
            ['name' => 'X', 'gender' => 'Male', 'min_age' => 5, 'max_age' => 6],
        ]])->assertForbidden();

        $this->assertSame($before, EventCategory::where('event_id', $event->id)->count());
    }
}
