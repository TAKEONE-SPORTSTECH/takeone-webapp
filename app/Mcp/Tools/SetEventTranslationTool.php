<?php

namespace App\Mcp\Tools;

use App\Events\Support\EventAccess;
use App\Models\ClubEvent;
use App\Translation\Translations;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * Correct one translated sentence, permanently.
 *
 * The same act as typing into the Languages sheet in the event console, and it
 * has the same consequence: the field is marked as written by a PERSON, and no
 * machine run will ever overwrite it — not a re-translate, not an edit to the
 * source text, not a change of provider.
 *
 * Why an integration needs it: a federation with an official name for a
 * division, or a club with house wording for its fees, wants that wording
 * loaded once and left alone. Doing it through this tool means it survives
 * every subsequent translation, which is exactly what a glossary is for.
 *
 * ⚠️ The field must be one the event actually publishes. Without that check
 * this would be a way to write arbitrary rows into the translation store.
 */
#[Title('Correct an event translation')]
#[Description('Set the wording of one field of an event in one language — the same as editing it in the console. The correction is marked as human-written and is NEVER overwritten by a later automatic translation. Organiser only. Send an empty value to drop the correction and let the automatic translation stand again.')]
class SetEventTranslationTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()
                ->description('The event uuid.'),
            'locale' => $schema->string()->required()
                ->description('Language code, e.g. "pt".'),
            'field' => $schema->string()->required()
                ->description('Which field — "title", "about", "location", "level", "prize", "cta_text", "ribbon_label", or a list entry like "requirements.0", "divisions.418", "fees.2". Read get_event_translation first for the exact keys this event has.'),
            'value' => $schema->string()
                ->description('The wording to use. Empty or omitted removes your correction and lets the automatic translation stand again.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $validated = $request->validate([
            'event' => 'required|string',
            'locale' => 'required|string|max:12',
            'field' => 'required|string|max:120',
            'value' => 'nullable|string|max:6000',
        ]);

        $event = ClubEvent::where('uuid', $validated['event'])->first();

        if (! $event || ! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Event not found.');
        }

        $locales = Translations::locales();
        $locale = $locales->normalise($validated['locale']);

        if ($locale === null) {
            return Response::error('That is not a language this platform serves.');
        }

        if ($locale === $event->sourceLocale()) {
            return Response::error(
                'That is the language the event was written in. Edit the event itself rather than its translation.'
            );
        }

        $document = Translations::document($event);

        // The field has to be one this event publishes — otherwise this is an
        // arbitrary-write endpoint wearing a translation tool's name.
        if (! isset($document[$validated['field']])) {
            return Response::error(
                'This event has no field "'.$validated['field'].'". Call get_event_translation to see the exact keys.'
            );
        }

        $value = trim((string) ($validated['value'] ?? ''));

        /*
         * The module owns the write.
         *
         * `Translations::correct()` is the same call the console's own review
         * screen makes, so the two cannot drift: one place decides that an
         * empty value DELETES the correction rather than blanking it (a blank
         * human row would out-rank the machine's and leave the field empty
         * forever), and one place stamps the source hash.
         */
        Translations::correct($event, $locale, $validated['field'], $value);

        if ($value === '') {
            return Response::json([
                'event' => ['uuid' => $event->uuid],
                'locale' => $locale,
                'field' => $validated['field'],
                'removed' => true,
                'note' => 'Your correction was removed. The automatic translation stands again, and this field will be rewritten the next time the event is translated.',
            ]);
        }

        return Response::json([
            'event' => ['uuid' => $event->uuid],
            'locale' => $locale,
            'language' => $locales->name($locale),
            'field' => $validated['field'],
            'source' => $document[$validated['field']],
            'value' => $value,
            'origin' => 'human',
            'note' => 'Saved as a human translation. No automatic run will overwrite it.',
        ]);
    }
}
