<?php

namespace Tests\Feature;

use App\Models\ClubEvent;
use App\Models\EventDocument;
use App\Clubs\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Documents attached to an event.
 *
 * Upload/delete are organiser-only; download is anyone the event's scope
 * reaches. The stored file is named by the server, verified by its real bytes,
 * and removed from disk before its row.
 */
class EventDocumentTest extends TestCase
{
    private function clubFor(User $user, array $attrs = []): Tenant
    {
        $club = $this->createClub($user, array_merge(['country' => 'BH'], $attrs));
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $club;
    }

    private function event(Tenant $club, User $organiser, array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $club->id,
            'created_by' => $organiser->id,
            'title' => 'Championship',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'status' => 'active',
            'is_archived' => false,
            'date' => now()->addWeek()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ], $attrs));
    }

    /** A real, minimal PDF — magic bytes matter, this is validated by content. */
    private function pdf(string $name = 'rules.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'doc').'.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    public function test_an_organiser_can_attach_a_document(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $response = $this->actingAs($organiser->fresh())->postJson(
            "/me/events/{$event->uuid}/documents",
            ['document' => $this->pdf(), 'title' => 'Rulebook 2026']
        )->assertOk();

        $this->assertTrue($response->json('success'));
        $this->assertSame('Rulebook 2026', $response->json('document.title'));
        $this->assertSame('pdf', $response->json('document.extension'));

        $doc = EventDocument::firstOrFail();
        $this->assertSame($event->id, $doc->event_id);
        $this->assertTrue(Storage::disk('local')->exists($doc->path));
    }

    public function test_the_stored_filename_is_generated_not_the_uploaded_one(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $this->actingAs($organiser->fresh())->postJson(
            "/me/events/{$event->uuid}/documents",
            ['document' => $this->pdf('../../evil name.pdf'), 'title' => 'Rulebook']
        )->assertOk();

        $doc = EventDocument::firstOrFail();

        $this->assertStringNotContainsString('evil', $doc->path);
        $this->assertStringNotContainsString('..', $doc->path);
        $this->assertStringStartsWith("events/{$event->uuid}/documents/", $doc->path);
        $this->assertMatchesRegularExpression('/\/[0-9A-Z]{26}\.pdf$/', $doc->path);
    }

    public function test_it_rejects_a_disguised_executable(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        // PHP source claiming to be a PDF by name and Content-Type.
        $path = tempnam(sys_get_temp_dir(), 'evil').'.pdf';
        file_put_contents($path, "<?php echo shell_exec(\$_GET['c']); ?>");
        $evil = new UploadedFile($path, 'rules.pdf', 'application/pdf', null, true);

        $this->actingAs($organiser->fresh())->postJson(
            "/me/events/{$event->uuid}/documents",
            ['document' => $evil, 'title' => 'Rulebook']
        )->assertStatus(422);

        $this->assertSame(0, EventDocument::count());
    }

    public function test_a_non_organiser_cannot_attach_a_document(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        // A member of the same club: may SEE the event, may not manage it.
        $member = $this->createUser();
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $this->actingAs($member->fresh())->postJson(
            "/me/events/{$event->uuid}/documents",
            ['document' => $this->pdf(), 'title' => 'Sneaky']
        )->assertForbidden();

        $this->assertSame(0, EventDocument::count());
    }

    public function test_anyone_who_can_see_the_event_can_download(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $this->actingAs($organiser->fresh())->postJson(
            "/me/events/{$event->uuid}/documents",
            ['document' => $this->pdf(), 'title' => 'Rulebook']
        )->assertOk();

        $doc = EventDocument::firstOrFail();

        $member = $this->createUser();
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $this->actingAs($member->fresh())
            ->get("/me/events/{$event->uuid}/documents/{$doc->uuid}")
            ->assertOk()
            ->assertDownload('Rulebook.pdf');
    }

    public function test_someone_who_cannot_see_the_event_cannot_download(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser, ['scope' => 'internal']);

        $this->actingAs($organiser->fresh())->postJson(
            "/me/events/{$event->uuid}/documents",
            ['document' => $this->pdf(), 'title' => 'Rulebook']
        )->assertOk();

        $doc = EventDocument::firstOrFail();

        // An outsider with a valid uuid still gets nothing.
        $outsider = $this->createUser();
        $this->clubFor($outsider);

        $this->actingAs($outsider->fresh())
            ->getJson("/me/events/{$event->uuid}/documents/{$doc->uuid}")
            ->assertForbidden();
    }

    public function test_a_document_cannot_be_fetched_through_another_event(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $eventA = $this->event($club, $organiser, ['title' => 'A']);
        $eventB = $this->event($club, $organiser, ['title' => 'B']);

        $this->actingAs($organiser->fresh())->postJson(
            "/me/events/{$eventA->uuid}/documents",
            ['document' => $this->pdf(), 'title' => 'Rulebook']
        )->assertOk();

        $doc = EventDocument::firstOrFail();

        $this->actingAs($organiser->fresh())
            ->getJson("/me/events/{$eventB->uuid}/documents/{$doc->uuid}")
            ->assertNotFound();
    }

    public function test_deleting_a_document_removes_the_file_first(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $this->actingAs($organiser->fresh())->postJson(
            "/me/events/{$event->uuid}/documents",
            ['document' => $this->pdf(), 'title' => 'Rulebook']
        )->assertOk();

        $doc = EventDocument::firstOrFail();
        $path = $doc->path;
        $this->assertTrue(Storage::disk('local')->exists($path));

        $this->actingAs($organiser->fresh())
            ->deleteJson("/me/events/{$event->uuid}/documents/{$doc->uuid}")
            ->assertOk();

        $this->assertSame(0, EventDocument::count());
        $this->assertFalse(Storage::disk('local')->exists($path), 'the file must not outlive its row');
    }

    public function test_a_non_organiser_cannot_delete_a_document(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $this->actingAs($organiser->fresh())->postJson(
            "/me/events/{$event->uuid}/documents",
            ['document' => $this->pdf(), 'title' => 'Rulebook']
        )->assertOk();

        $doc = EventDocument::firstOrFail();

        $member = $this->createUser();
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $this->actingAs($member->fresh())
            ->deleteJson("/me/events/{$event->uuid}/documents/{$doc->uuid}")
            ->assertForbidden();

        $this->assertSame(1, EventDocument::count());
        $this->assertTrue(Storage::disk('local')->exists($doc->fresh()->path));
    }

    public function test_it_rejects_a_file_over_the_size_limit(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        // 51 MB — past the 50 MB ceiling. Laravel's `max:` rule catches it
        // before the bytes are ever inspected or written.
        $big = UploadedFile::fake()->create('huge.pdf', 51 * 1024, 'application/pdf');

        $this->actingAs($organiser->fresh())->postJson(
            "/me/events/{$event->uuid}/documents",
            ['document' => $big, 'title' => 'Too big']
        )->assertStatus(422);

        $this->assertSame(0, EventDocument::count());
    }

    public function test_a_file_under_the_limit_is_accepted(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        // 30 MB of real PDF — comfortably inside the new 50 MB ceiling and
        // above the old 10 MB one, so this would have failed before.
        $path = tempnam(sys_get_temp_dir(), 'big').'.pdf';
        file_put_contents($path, "%PDF-1.4\n".str_repeat('0', 30 * 1024 * 1024)."\n%%EOF\n");
        $file = new UploadedFile($path, 'pack.pdf', 'application/pdf', null, true);

        $this->actingAs($organiser->fresh())->postJson(
            "/me/events/{$event->uuid}/documents",
            ['document' => $file, 'title' => 'Entry pack']
        )->assertOk();

        $this->assertSame(1, EventDocument::count());
        @unlink($path);
    }

    public function test_the_document_list_renders_on_the_event_page(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $this->actingAs($organiser->fresh())->postJson(
            "/me/events/{$event->uuid}/documents",
            ['document' => $this->pdf(), 'title' => 'Rulebook 2026']
        )->assertOk();

        $this->actingAs($organiser->fresh())
            ->get("/me/events/{$event->uuid}")
            ->assertOk()
            ->assertSee('Rulebook 2026');
    }
}
