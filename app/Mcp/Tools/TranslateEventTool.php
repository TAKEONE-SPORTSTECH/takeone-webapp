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
 * Write an event into one or more languages, ahead of anybody asking.
 *
 * The real use: an organiser expecting a Brazilian squad has the Portuguese
 * written the night before, so the first of them to open the link waits for
 * nothing. Without this the first visitor in each language pays the wait.
 *
 * ⚠️ THE EXPENSIVE TOOL. Every locale accepted here can start a generation at a
 * paid provider, so it is guarded harder than any other write tool on this
 * server:
 *
 *   · organiser only — `EventAccess::canManage`, not merely "can see it";
 *   · a hard cap on how many languages one call may start;
 *   · it QUEUES, never blocks, so a caller cannot hold a connection open
 *     through sixty generations;
 *   · a language that is already done is skipped, and says so, rather than
 *     being redone.
 *
 * `force` exists for one honest case — the provider or model was changed and
 * the old output should be replaced. It re-does MACHINE rows only; an
 * organiser's own corrections survive it, exactly as they survive everything
 * else in this module.
 */
#[Title('Translate an event')]
#[Description('Write an event\'s own words into one or more languages, so readers get it in theirs. Organiser only. Queues the work and returns immediately — poll get_event_translation for the result. Languages already done are skipped unless force is set. Costs money at the configured AI provider, so it is capped per call and never triggered by a read.')]
class TranslateEventTool extends BaseTool
{
    protected bool $isWrite = true;

    /**
     * At most this many languages per call. Not a technical limit — a brake. An
     * integration in a retry loop asking for "every language" would otherwise
     * turn one bug into a bill, and nobody needs twelve at once badly enough to
     * justify removing it.
     */
    private const MAX_PER_CALL = 12;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()
                ->description('The event uuid.'),
            'locales' => $schema->array()->required()
                ->description('Language codes to write it into, e.g. ["pt","ja"]. Max '.self::MAX_PER_CALL.' per call.'),
            'force' => $schema->boolean()
                ->description('Redo languages that are already finished. Replaces machine translations only — corrections made by a person are never overwritten. Use after changing the AI provider or model.'),
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
            'locales' => 'required|array|min:1|max:'.self::MAX_PER_CALL,
            'locales.*' => 'required|string|max:12',
            'force' => 'nullable|boolean',
        ]);

        $event = ClubEvent::where('uuid', $validated['event'])->first();

        // Same answer for "no such event" and "not yours to manage" — a
        // stranger must not learn a uuid is real by being told they lack
        // permission on it.
        if (! $event || ! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Event not found.');
        }

        $locales = Translations::locales();
        $force = (bool) ($validated['force'] ?? false);

        $queued = [];
        $skipped = [];

        foreach ($validated['locales'] as $raw) {
            $locale = $locales->normalise((string) $raw);

            if ($locale === null) {
                $skipped[] = ['locale' => (string) $raw, 'reason' => 'not a language this platform serves'];

                continue;
            }

            if ($locale === $event->sourceLocale()) {
                $skipped[] = ['locale' => $locale, 'reason' => 'the language the event was written in'];

                continue;
            }

            if ($force) {
                /*
                 * The machine's words only. The same promise the whole module
                 * makes: a sentence a person fixed stays fixed, whatever
                 * anybody asks for afterwards.
                 */
                Translations::dropMachine($event, $locale);
            }

            $status = Translations::ensure($event, $locale);

            if ($status === 'ready') {
                $skipped[] = ['locale' => $locale, 'reason' => 'already done'];

                continue;
            }

            $queued[] = [
                'locale' => $locale,
                'language' => $locales->name($locale),
                'status' => $status,
            ];
        }

        return Response::json([
            'event' => ['uuid' => $event->uuid, 'title' => $event->title],
            'source_locale' => $event->sourceLocale(),
            'queued' => $queued,
            'skipped' => $skipped,
            'note' => $queued === []
                ? 'Nothing to do.'
                : 'Queued. Each language takes roughly 10–40 seconds; read it back with get_event_translation.',
        ]);
    }
}
