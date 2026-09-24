<?php

namespace App\Ai\Copilot;

use App\Ai\AiManager;
use App\Members\Models\User;
use App\Services\Copilot\WebFetch;
use App\Services\Copilot\WebSearch;
use App\Support\Copilot\CopilotSkill;
use App\Support\Copilot\CreatesWhenDone;
use App\Support\Copilot\HasOwnConversation;
use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\Surfaces\ContributesCopilotSkills;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Coach, the general assistant: one conversation, every skill the user may use.
 *
 * Each module teaches it to create its own records (ContributesCopilotSkills).
 * The model sees one `propose_{skill}` tool per skill the user is allowed, the
 * skills' read tools, and the web; it decides from the conversation what is
 * being created. The page it was opened on is a hint, never a restriction.
 *
 * Draft-then-confirm, exactly as the club-only assistant was: a proposal is
 * validated by its skill, signed and encrypted with the acting user's id and an
 * expiry, and only an explicit Create sends it to apply(), which checks the
 * signer, the permission and the data again.
 *
 * Runs beside App\Services\Copilot\CopilotService (the club-only original) until
 * config('copilot.skills') is switched on and verified — RULE #1.
 */
class SkillCopilot
{
    private const TOKEN_TTL = 3600;

    public function __construct(
        private AiManager $ai,
        private WebSearch $webSearch,
        private WebFetch $webFetch,
    ) {}

    /** @return Collection<int, CopilotSkill> */
    public function skillsFor(User $user): Collection
    {
        return app(ModuleRegistry::class)->contributingTo(ContributesCopilotSkills::class)
            ->flatMap(fn (ContributesCopilotSkills $m) => $m->copilotSkills())
            ->filter(fn (CopilotSkill $s) => $s->allowedFor($user))
            ->values();
    }

    public function skill(User $user, string $key): ?CopilotSkill
    {
        return $this->skillsFor($user)->first(fn (CopilotSkill $s) => $s->key() === $key);
    }

    /**
     * One assistant turn.
     *
     * @return array{reply: string, skill?: string, title?: string, rows?: array, token?: string}
     */
    public function reply(User $user, ?string $page, array $history, ?string $state = null): array
    {
        $locale = $user->locale ?? app()->getLocale();
        $skills = $this->skillsFor($user);
        if ($skills->isEmpty()) {
            return ['reply' => __('copilot.nothing_to_create', [], $locale)];
        }

        // An assistant with a prompt of its own: carry on the conversation it
        // started (signed state), or start it on the page that is about it.
        $carried = $this->readState($user, $state);
        $own = $carried ? $skills->first(fn ($s) => $s->key() === $carried['skill']) : $this->hinted($skills, $page);
        if ($own instanceof HasOwnConversation) {
            return $this->converse($user, $own, $carried['fields'] ?? [], $history, $locale);
        }

        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt($user, $skills, $page, $locale)]],
            $this->sanitizeHistory($history),
        );
        $driver = $this->ai->text();

        for ($round = 0; $round < 6; $round++) {
            $message = $driver->chat($messages, $this->tools($skills));
            $calls = $message['tool_calls'] ?? [];
            if ($calls === []) {
                $text = trim((string) ($message['content'] ?? ''));

                return ['reply' => $text !== '' ? $text : __('copilot.ask_what', [], $locale)];
            }
            $messages[] = $message;

            foreach ($calls as $call) {
                $name = (string) ($call['function']['name'] ?? '');
                $args = $call['function']['arguments'] ?? [];
                if (is_string($args)) {
                    $args = json_decode($args, true) ?: [];
                }

                if (str_starts_with($name, 'propose_')) {
                    $skill = $skills->first(fn ($s) => 'propose_'.$s->key() === $name);
                    if ($skill) {
                        return $this->stage($user, $skill, (array) $args, $locale, $this->lastUserMessage($history));
                    }
                    $result = ['error' => 'unknown tool'];
                } else {
                    $result = match ($name) {
                        'web_search' => $this->webEnabled() ? $this->webSearch->search((string) ($args['query'] ?? '')) : ['ok' => false, 'error' => 'Web access is disabled.'],
                        'web_fetch' => $this->webEnabled() ? $this->webFetch->fetch((string) ($args['url'] ?? '')) : ['ok' => false, 'error' => 'Web access is disabled.'],
                        default => $this->lookup($user, $skills, $name, (array) $args),
                    };
                }

                $messages[] = ['role' => 'tool', 'tool_name' => $name, 'content' => json_encode($result, JSON_UNESCAPED_UNICODE)];
            }
        }

        return ['reply' => __('copilot.ask_what', [], $locale)];
    }

    /** Commit a confirmed proposal. */
    public function apply(User $user, string $token): array
    {
        try {
            $p = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable) {
            return ['ok' => false, 'message' => __('copilot.proposal_expired')];
        }
        if (! is_array($p) || ($p['uid'] ?? null) !== $user->id || ($p['exp'] ?? 0) < time()) {
            return ['ok' => false, 'message' => __('copilot.proposal_expired')];
        }
        $skill = $this->skill($user, (string) ($p['skill'] ?? ''));
        if (! $skill) {
            return ['ok' => false, 'message' => __('copilot.proposal_expired')];
        }
        // One draft, one record: a double-click or a replayed request must not
        // create it twice. Claimed before applying; released if the apply fails.
        $claim = 'copilot:applied:'.hash('sha256', $token);
        if (! Cache::add($claim, 1, self::TOKEN_TTL)) {
            return ['ok' => false, 'message' => __('copilot.proposal_used')];
        }
        $out = $skill->apply($user, (array) ($p['data'] ?? []));
        if (! ($out['ok'] ?? false)) {
            Cache::forget($claim);
        }

        return $out;
    }

    /**
     * One turn of a skill's own conversation: its prompt, JSON back, the fields
     * kept by the engine, and — once the user is done — straight to creation
     * through the skill's stage() and the same signed apply().
     */
    private function converse(User $user, HasOwnConversation $skill, array $fields, array $history, string $locale): array
    {
        $clean = $this->sanitizeHistory($history);
        $last = $this->lastUserMessage($clean);
        $before = array_slice($clean, 0, max(0, count($clean) - 1));
        $json = fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ask = function (array $fields) use ($skill, $user, $locale, $json, $before, $last) {
            $prompt = strtr($skill->conversationPrompt($user, $locale), [
                '{{CURRENT_FIELDS_JSON}}' => $json((object) array_filter($fields, fn ($k) => ! str_starts_with((string) $k, '_'), ARRAY_FILTER_USE_KEY)),
                '{{LOOKUP_RESULTS_JSON}}' => $json((object) ($fields['_lookups'] ?? [])),
                '{{CONVERSATION_HISTORY_JSON}}' => $json($before),
                '{{USER_MESSAGE}}' => trim($json($last), '"'),
            ]);
            $message = $this->ai->text()->chat([['role' => 'user', 'content' => $prompt]], [], ['json' => true]);

            return $this->decodeJson((string) ($message['content'] ?? ''));
        };

        $out = $ask($fields);
        if ($out === null) {
            return ['reply' => __('copilot.ask_what', [], $locale), 'state' => $this->writeState($user, $skill, $fields)];
        }
        $fields = $skill->absorb($fields, is_array($out['extracted_fields'] ?? null) ? $out['extracted_fields'] : [], $user);

        // The platform checks what it can (people named by email or name). When it
        // learned something, ask again with the answer in hand so the reply uses it;
        // the second answer's extraction is ignored — this message was read once.
        $resolved = $skill->resolve($fields, $user);
        $fields = $resolved['fields'];
        if ($resolved['changed']) {
            $out = $ask($fields) ?? $out;
        }
        $reply = trim((string) ($out['response'] ?? '')) ?: __('copilot.ask_what', [], $locale);
        $state = $this->writeState($user, $skill, $fields);

        // The model says ready, AND the user's own latest words agree.
        if (($out['ready_to_create'] ?? false) !== true || ! ($this->saysDone($last) || $this->agrees($last))) {
            return ['reply' => $reply, 'state' => $state];
        }

        $done = $skill->complete($fields, $user);
        $staged = $skill->stage($user, $done['fields'], $locale);
        if (! ($staged['ok'] ?? false)) {
            return ['reply' => __('copilot.need_more', ['errors' => implode(' ', $staged['errors'] ?? [])], $locale), 'state' => $state];
        }

        return [
            // It is being created now: a reply still asking "ready?" would be wrong.
            'reply' => str_ends_with($reply, '?') ? __('copilot.creating_now', ['what' => $skill->label($locale)], $locale) : $reply,
            'skill' => $skill->key(),
            'commit' => true,
            'title' => $staged['title'] ?? $skill->label($locale),
            'rows' => $staged['rows'] ?? [],
            'state' => $state,
            'token' => Crypt::encryptString(json_encode([
                'skill' => $skill->key(), 'uid' => $user->id, 'exp' => time() + self::TOKEN_TTL,
                'data' => $staged['data'] + ['_placeholders' => $done['placeholders']],
            ])),
        ];
    }

    private function hinted(Collection $skills, ?string $page): ?CopilotSkill
    {
        return $page ? $skills->first(fn ($s) => collect($s->pages())->contains(fn ($p) => $p === $page || (str_ends_with($p, '*') && str_starts_with($page, rtrim($p, '*'))))) : null;
    }

    /** The gathered fields, signed to this user and skill, round-tripped by the widget. */
    private function writeState(User $user, CopilotSkill $skill, array $fields): string
    {
        return Crypt::encryptString(json_encode(['skill' => $skill->key(), 'uid' => $user->id, 'exp' => time() + self::TOKEN_TTL, 'fields' => $fields], JSON_UNESCAPED_UNICODE));
    }

    private function readState(User $user, ?string $state): ?array
    {
        if (! $state) {
            return null;
        }
        try {
            $s = json_decode(Crypt::decryptString($state), true);
        } catch (\Throwable) {
            return null;
        }

        return is_array($s) && ($s['uid'] ?? null) === $user->id && ($s['exp'] ?? 0) >= time() && is_string($s['skill'] ?? null)
            ? ['skill' => $s['skill'], 'fields' => is_array($s['fields'] ?? null) ? $s['fields'] : []]
            : null;
    }

    /** The model's JSON, tolerating a code fence or a stray sentence around it. */
    private function decodeJson(string $text): ?array
    {
        $a = strpos($text, '{');
        $b = strrpos($text, '}');
        if ($a === false || $b === false || $b < $a) {
            return null;
        }
        $out = json_decode(substr($text, $a, $b - $a + 1), true);

        return is_array($out) ? $out : null;
    }

    /** A short yes to "shall I fill the rest in?" ("yes", "sure", "نعم", "تمام"…). */
    private function agrees(string $message): bool
    {
        $m = mb_strtolower(trim($message));

        return $m !== '' && mb_strlen($m) <= 80
            && (bool) preg_match('/\b(yes|yeah|yep|yup|sure|ok|okay|go|let\'?s go|do it|fill it|fill them|please|whatever|you decide|oui)\b|نعم|ايوه|أيوه|اوكي|تمام|يلا|موافق|اكيد|أكيد/u', $m);
    }

    private function stage(User $user, CopilotSkill $skill, array $args, string $locale, string $lastUser = ''): array
    {
        $staged = $skill->stage($user, $args, $locale);
        if (! ($staged['ok'] ?? false)) {
            return ['reply' => __('copilot.need_more', ['errors' => implode(' ', $staged['errors'] ?? [])], $locale)];
        }

        // A gather-then-done skill: the user's "done" is the confirmation, so the
        // widget commits at once through the same signed, single-use apply().
        // The model's flag alone is not enough: the user's own latest words must
        // say "done", or it falls back to the draft card and its Create button.
        $commit = $skill instanceof CreatesWhenDone && ($args['user_said_done'] ?? false) === true && $this->saysDone($lastUser);

        return [
            'reply' => __($commit ? 'copilot.creating_now' : 'copilot.proposal_intro_any', ['what' => $skill->label($locale)], $locale),
            'skill' => $skill->key(),
            'commit' => $commit,
            'title' => $staged['title'] ?? $skill->label($locale),
            'rows' => $staged['rows'] ?? [],
            'token' => Crypt::encryptString(json_encode([
                'skill' => $skill->key(), 'uid' => $user->id, 'exp' => time() + self::TOKEN_TTL, 'data' => $staged['data'],
            ])),
        ];
    }

    private function lastUserMessage(array $history): string
    {
        foreach (array_reverse($history) as $entry) {
            if (is_array($entry) && ($entry['role'] ?? null) === 'user') {
                return (string) ($entry['content'] ?? '');
            }
        }

        return '';
    }

    /** A short message that says the user has finished ("done", "خلاص", "create it"…). */
    private function saysDone(string $message): bool
    {
        $m = mb_strtolower(trim($message));
        if ($m === '' || mb_strlen($m) > 80) {
            return false;
        }

        return (bool) preg_match('/\b(done|finished|finish|that\'?s (all|it)|that is (all|it)|all set|go ahead|create it|create now|save it|terminé|fini)\b|خلاص|تم|انتهيت|انتهينا|أنشئ|انشئ|اعتمد/u', $m);
    }

    private function lookup(User $user, Collection $skills, string $tool, array $args): array
    {
        foreach ($skills as $s) {
            foreach ($s->lookupTools() as $def) {
                if (($def['function']['name'] ?? null) === $tool) {
                    return $s->lookup($user, $tool, $args);
                }
            }
        }

        return ['error' => 'unknown tool'];
    }

    private function tools(Collection $skills): array
    {
        $tools = [];
        if ($this->webEnabled()) {
            $tools[] = ['type' => 'function', 'function' => ['name' => 'web_search',
                'description' => 'Search the live web for current or external information. Returns results (title, url, snippet). Follow up with web_fetch to read the most relevant ones before answering.',
                'parameters' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string', 'description' => 'The search query']], 'required' => ['query']]]];
            $tools[] = ['type' => 'function', 'function' => ['name' => 'web_fetch',
                'description' => 'Fetch and read the main text of a web page by URL (usually one returned by web_search).',
                'parameters' => ['type' => 'object', 'properties' => ['url' => ['type' => 'string', 'description' => 'The absolute http(s) URL to read']], 'required' => ['url']]]];
        }
        foreach ($skills as $s) {
            foreach ($s->lookupTools() as $def) {
                $tools[] = $def;
            }
            if ($s instanceof CreatesWhenDone) {
                $tools[] = ['type' => 'function', 'function' => [
                    'name' => 'propose_'.$s->key(),
                    'description' => 'Create: '.$s->label('en').'. Call ONLY after the user says they are done (done, finish, that\'s all, create it — in any language), with every fact gathered in the conversation. Set user_said_done true. If a required field is still missing, ask for it instead of calling.',
                    'parameters' => ['type' => 'object', 'properties' => $s->parameters() + [
                        'user_said_done' => ['type' => 'boolean', 'description' => 'True only when the user\'s latest message says they are done and want it created now.'],
                    ], 'required' => array_merge($s->required(), ['user_said_done'])],
                ]];

                continue;
            }
            $tools[] = ['type' => 'function', 'function' => [
                'name' => 'propose_'.$s->key(),
                'description' => 'Stage a draft to '.$s->label('en').'. Call once you have the required fields. The human reviews and confirms before anything is created.',
                'parameters' => ['type' => 'object', 'properties' => $s->parameters(), 'required' => $s->required()],
            ]];
        }

        return $tools;
    }

    private function systemPrompt(User $user, Collection $skills, ?string $page, string $locale): string
    {
        $language = ['en' => 'English', 'ar' => 'Arabic', 'fr' => 'French'][$locale] ?? 'English';
        $menu = $skills->map(fn ($s) => '- '.$s->label('en').' → tool propose_'.$s->key())->implode("\n");
        $hint = $page ? $skills->first(fn ($s) => collect($s->pages())->contains(fn ($p) => $p === $page || (str_ends_with($p, '*') && str_starts_with($page, rtrim($p, '*'))))) : null;
        $hintLine = $hint ? "\nThe user opened you on a page about this, so it is probably what they want: ".$hint->label('en').". Still follow what they actually ask." : '';
        $sections = $skills->map(fn ($s) => '### '.strtoupper($s->label('en'))."\n".trim($s->instructions($locale, $user)))->implode("\n\n");

        return <<<PROMPT
        You are "Coach", the friendly TAKEONE assistant. You help a platform
        administrator create records on the platform by conversation. Talk like a
        helpful human — warm, brief, leading the chat — never like a form, and
        never dump a checklist of questions.

        You can create these kinds of records (one tool each):
        {$menu}
        Work out from the conversation which one the user means. If it is unclear,
        ask one short question. If they ask for something not on this list, say
        plainly that you can't create that yet — never pretend, and never use a
        different tool to approximate it.{$hintLine}

        LANGUAGE: The interface language is {$language}. Reply in it by default,
        but mirror the language the user writes in. Keep tool VALUES
        machine-friendly regardless: country ISO2 (BH), currency ISO (BHD).

        LISTEN and pull the facts out yourself; don't paste their words into fields.
        Use the look-up tools to check the platform before proposing (duplicates,
        existing accounts) — never say you "can't check".
        You can use the live internet (web_search, then web_fetch the best 2–3
        pages) for public facts you're unsure of — an organisation's address,
        website, international body. Answer only from what you read, cite URLs,
        never invent facts, URLs, phone numbers or bank details.

        Unless a section below says it is created when the user says "done", the
        propose tool only drafts a proposal the human reviews and confirms. Never
        claim something is already created. You may propose again to fix anything.

        {$sections}
        PROMPT;
    }

    private function sanitizeHistory(array $history): array
    {
        $max = (int) config('copilot.max_messages', 16);
        $maxLen = (int) config('copilot.max_message_length', 4000);
        $clean = [];
        foreach ($history as $entry) {
            $role = is_array($entry) ? ($entry['role'] ?? null) : null;
            $content = mb_substr((string) (is_array($entry) ? ($entry['content'] ?? '') : ''), 0, $maxLen);
            if (in_array($role, ['user', 'assistant'], true) && $content !== '') {
                $clean[] = ['role' => $role, 'content' => $content];
            }
        }

        return array_slice($clean, -$max);
    }

    private function webEnabled(): bool
    {
        return (bool) config('copilot.web.enabled', true);
    }
}
