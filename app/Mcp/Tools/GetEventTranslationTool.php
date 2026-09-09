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
 * An event's own words, in whatever language the reader wants.
 *
 * The point of exposing this to integrations: a bot messaging a Brazilian squad
 * on WhatsApp, or an email to a Japanese federation, should send the event in
 * THEIR language — not English with an apology. This hands over exactly the text
 * the public page would show, so the message and the page agree.
 *
 * ⚠️ READ ONLY, and deliberately: it never starts a translation. A caller
 * looping over sixty locales must not be able to spend sixty generations, and a
 * read tool that silently costs money is a trap. `translate_event` is the
 * explicit, organiser-only door for that.
 */
#[Title('Read an event in another language')]
#[Description('Read an event\'s title, description, location, prize, requirements, divisions and fee lines in a given language, as the public page would show them. Falls back to the language the organiser wrote in for anything not translated yet, and says which fields those are. Never starts a translation — use translate_event for that. Returns "not found" for an event the acting user cannot see.')]
class GetEventTranslationTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()
                ->description('The event uuid (from list_events).'),
            'locale' => $schema->string()
                ->description('Language code, e.g. "pt", "ar", "ja", "zh-TW". Omit to list which languages this event already has without reading one.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $uuid = trim((string) $request->get('event', ''));
        $event = $uuid !== '' ? ClubEvent::where('uuid', $uuid)->first() : null;

        // One answer for "no such event" and "not yours" — never confirm a uuid
        // is real to somebody who may not see it.
        if (! $event || ! app(EventAccess::class)->visible($event, $user)) {
            return Response::error('Event not found.');
        }

        $locales = Translations::locales();

        $source = $event->sourceLocale();
        $document = Translations::document($event);

        // Which languages this event has been read in already. Cheap, and it is
        // what a caller needs to decide whether to ask for one.
        $existing = collect(array_keys(Translations::stored($event)))
            ->filter(fn ($locale) => $locales->has($locale))
            ->map(fn ($locale) => [
                'locale' => $locale,
                'name' => $locales->name($locale),
                'native' => $locales->native($locale),
                'status' => Translations::status($event, $locale),
            ])->values();

        $requested = $request->get('locale');

        if ($requested === null || trim((string) $requested) === '') {
            return Response::json([
                'event' => ['uuid' => $event->uuid, 'title' => $event->title],
                'source_locale' => $source,
                'source_language' => $locales->name($source),
                'available' => $existing,
                'note' => 'Pass `locale` to read the event in one of these, or any code from the platform\'s content languages.',
            ]);
        }

        $locale = $locales->normalise((string) $requested);

        if ($locale === null) {
            return Response::error('That is not a language this platform serves. Omit `locale` to see which ones this event already has.');
        }

        $translated = Translations::of($event, $locale);

        // Field by field, with the source beside it and an honest flag for
        // anything still untranslated — a caller composing a message needs to
        // know it is about to send one English line in a Japanese paragraph.
        $fields = [];
        $missing = [];

        foreach ($document as $field => $sourceText) {
            $value = $translated->get($field, $sourceText);
            $isTranslated = $locale === $source || $translated->get($field) !== null;

            $fields[$field] = $value;

            if (! $isTranslated) {
                $missing[] = $field;
            }
        }

        return Response::json([
            'event' => ['uuid' => $event->uuid],
            'locale' => $locale,
            'language' => $locales->name($locale),
            'native' => $locales->native($locale),
            'dir' => $locales->dir($locale),
            'source_locale' => $source,
            'status' => Translations::status($event, $locale),
            // True when the interface itself speaks this language too, not just
            // the organiser's words.
            'interface_language' => $locales->isInterfaceLocale($locale),
            'fields' => $fields,
            // Named rather than counted, so a caller can decide per field
            // whether an untranslated line is acceptable in its message.
            'untranslated_fields' => $missing,
            'available' => $existing,
        ]);
    }
}
