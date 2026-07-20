<?php

namespace App\Services\Copilot;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * The Copilot ("Coach") agent loop.
 *
 * Thin slice: a single capability — `create_club` — with one tool (`propose_club`).
 * The model gathers club details in conversation; when it has enough it calls
 * `propose_club`, which VALIDATES the fields server-side and returns a signed,
 * tamper-evident draft the human must confirm. Nothing is written here — the
 * commit happens in CopilotController@apply after an explicit "Create".
 *
 * The model's output is NEVER trusted for authorization or persistence: it can
 * only shape the proposal fields, which are re-validated, and the club owner is
 * forced to the acting user server-side at apply time.
 */
class CopilotService
{
    public function __construct(
        private \App\Services\Ai\AiManager $ai,
        private WebSearch $webSearch,
        private WebFetch $webFetch,
    ) {}

    /**
     * Produce one assistant turn.
     *
     * @param  array<int,array{role?:string,content?:string}>  $history
     * @return array{reply:string,proposal?:array<string,mixed>,token?:string}
     */
    public function reply(User $user, string $context, array $history): array
    {
        // Only the create-club capability is wired in this slice.
        $locale = $user->locale ?? app()->getLocale();
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt($locale, $this->termsExamples())]],
            $this->sanitizeHistory($history),
        );

        // Resolve the configured text provider once (default → built-in Ollama).
        $driver = $this->ai->text();

        // Agentic loop: run read tools (find_club / web_search / web_fetch) and
        // feed results back so the model reasons on real data; propose_club is
        // terminal. Extra rounds allow search → fetch → fetch → answer.
        for ($round = 0; $round < 6; $round++) {
            $message = $driver->chat($messages, $this->tools());
            $calls = $message['tool_calls'] ?? [];

            if ($calls === []) {
                $text = trim((string) ($message['content'] ?? ''));

                return ['reply' => $text !== '' ? $text : __('copilot.ask_name', [], $locale)];
            }

            // Echo the assistant tool-call turn back before answering it.
            $messages[] = $message;

            foreach ($calls as $call) {
                $name = $call['function']['name'] ?? null;
                $args = $call['function']['arguments'] ?? [];
                if (is_string($args)) {
                    $args = json_decode($args, true) ?: [];
                }

                if ($name === 'propose_club') {
                    return $this->stageProposal($args, $locale);
                }

                $result = match ($name) {
                    'find_club' => $this->findClub($user, $args),
                    'web_search' => $this->webEnabled()
                        ? $this->webSearch->search((string) ($args['query'] ?? ''))
                        : ['ok' => false, 'error' => 'Web access is disabled.'],
                    'web_fetch' => $this->webEnabled()
                        ? $this->webFetch->fetch((string) ($args['url'] ?? ''))
                        : ['ok' => false, 'error' => 'Web access is disabled.'],
                    default => ['error' => 'unknown tool'],
                };

                $messages[] = [
                    'role' => 'tool',
                    'tool_name' => (string) $name,
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        // Safety net if it kept calling tools without answering.
        return ['reply' => __('copilot.ask_name', [], $locale)];
    }

    /**
     * Read tool: does a club with this (partial) name already exist? Scoped to
     * what the acting super-admin may see (this capability is super-admin only).
     *
     * @param  array<string,mixed>  $args
     * @return array<string,mixed>
     */
    private function findClub(User $user, array $args): array
    {
        $name = trim((string) ($args['name'] ?? ''));
        if ($name === '') {
            return ['found' => false, 'message' => 'No name given to look up.'];
        }

        $matches = Tenant::query()
            ->where('club_name', 'like', '%'.$name.'%')
            ->limit(5)
            ->get(['club_name', 'slug', 'country']);

        if ($matches->isEmpty()) {
            return ['found' => false, 'query' => $name, 'message' => "No club matching \"{$name}\" exists yet."];
        }

        return [
            'found' => true,
            'query' => $name,
            'matches' => $matches->map(fn ($c) => [
                'name' => $c->club_name,
                'slug' => $c->slug,
                'country' => $c->country,
            ])->all(),
        ];
    }

    /**
     * Validate the tool arguments and return a signed draft proposal.
     *
     * @param  array<string,mixed>|string  $args
     * @return array{reply:string,proposal?:array<string,mixed>,token?:string}
     */
    private function stageProposal(array|string $args, string $locale = 'en'): array
    {
        if (is_string($args)) {
            $args = json_decode($args, true) ?: [];
        }

        $validator = Validator::make($args, [
            'club_name' => 'required|string|max:255',
            'country' => 'nullable|string|max:2',
            'currency' => 'nullable|string|max:3',
            'description' => 'nullable|string|max:1000',
            'slogan' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'registration_requirements' => 'nullable|string|max:20000',
            'registration_terms' => 'nullable|string|max:20000',
        ]);

        if ($validator->fails()) {
            return ['reply' => __('copilot.need_more', ['errors' => implode(' ', $validator->errors()->all())], $locale)];
        }

        $data = $validator->validated();

        // Full payload (persisted on confirm). registration_* HTML is sanitized
        // again by the Tenant mutators (HtmlSanitizer::clean) on save.
        $full = [
            'club_name' => $data['club_name'],
            'slug' => $this->uniqueSlug($data['club_name']),
            'country' => strtoupper($data['country'] ?? 'BH'),
            'currency' => strtoupper($data['currency'] ?? 'BHD'),
            'description' => $data['description'] ?? null,
            'slogan' => $data['slogan'] ?? null,
            'email' => $data['email'] ?? null,
            'registration_requirements' => $data['registration_requirements'] ?? null,
            'registration_terms' => $data['registration_terms'] ?? null,
        ];

        // Card display: the long HTML fields become simple "generated ✓" flags.
        $display = $full;
        $display['has_requirements'] = ! empty($full['registration_requirements']);
        $display['has_terms'] = ! empty($full['registration_terms']);
        unset($display['registration_requirements'], $display['registration_terms']);

        return [
            'reply' => __('copilot.proposal_intro', [], $locale),
            'proposal' => $display,
            // Signed + encrypted so the client can't tamper the fields between
            // propose and confirm; re-validated again on apply.
            'token' => Crypt::encryptString(json_encode($full)),
        ];
    }

    /**
     * A couple of existing clubs' terms, as plain-text style references the
     * model can adapt from (never copy). Empty string when none exist yet.
     */
    private function termsExamples(): string
    {
        $clubs = Tenant::query()
            ->whereNotNull('registration_terms')
            ->where('registration_terms', '!=', '')
            ->limit(2)
            ->get(['club_name', 'registration_terms']);

        $out = [];
        foreach ($clubs as $club) {
            $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $club->registration_terms)));
            if ($text === '') {
                continue;
            }
            $out[] = 'From "'.$club->club_name.'": '.mb_substr($text, 0, 600);
        }

        return implode("\n\n", $out);
    }

    /** Generate a slug that is unique among tenants (never derived-and-collided). */
    private function uniqueSlug(string $name): string
    {
        // Str::slug() returns '' for Arabic/other non-Latin names — fall back to a
        // short random handle so every club gets a distinct, sane slug.
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'club-'.Str::lower(Str::random(6));
        }
        $slug = $base;
        $i = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * Trust nothing from the client transcript: keep only user/assistant turns,
     * cast content to string, cap count and length.
     *
     * @param  array<int,mixed>  $history
     * @return array<int,array{role:string,content:string}>
     */
    private function sanitizeHistory(array $history): array
    {
        $max = (int) config('copilot.max_messages', 16);
        $maxLen = (int) config('copilot.max_message_length', 4000);

        $clean = [];
        foreach ($history as $entry) {
            $role = is_array($entry) ? ($entry['role'] ?? null) : null;
            if (! in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $content = mb_substr((string) ($entry['content'] ?? ''), 0, $maxLen);
            if ($content === '') {
                continue;
            }
            $clean[] = ['role' => $role, 'content' => $content];
        }

        return array_slice($clean, -$max);
    }

    private function systemPrompt(string $locale = 'en', string $termsExamples = ''): string
    {
        $languages = ['en' => 'English', 'ar' => 'Arabic'];
        $language = $languages[$locale] ?? 'English';

        $examplesBlock = $termsExamples !== ''
            ? "\n\nSTYLE REFERENCE — existing clubs' terms (adapt the tone and structure, never copy their names or specifics):\n{$termsExamples}"
            : '';

        return <<<PROMPT
        You are "Coach", the friendly TAKEONE assistant helping a super-admin set
        up a new sports club. Talk like a helpful human having a casual, natural
        conversation — NOT like a form. React to what they say, keep it warm and
        brief, and lead the chat. Don't dump a checklist of questions.

        LANGUAGE: The user's interface language is {$language}. Reply in {$language}
        by default, but ALWAYS mirror the language the user writes in, and switch
        on request. Keep tool VALUES machine-friendly regardless of chat language:
        country as ISO2 (e.g. BH), currency as ISO (e.g. BHD). club_name may be in
        any language or script; the generated description/slogan/terms should be in
        the user's language.

        LISTEN and analyse what the user says, and pull the useful facts out
        yourself — don't take text literally or paste it into a field.

        You have real access to the platform's data through tools. When the user
        asks whether a club exists, or before you propose a name, CALL the
        find_club tool and answer from its result — never say you "can't check".
        If find_club shows the name is taken, tell the user and suggest a tweak.

        You can also access the live internet. For any question about current
        events, external facts, prices, rules, or anything you're not certain of,
        use web_search to find sources, then web_fetch to READ the most relevant
        2–3 pages, cross-check them, and answer from what you actually read —
        citing the source URLs. Never fabricate facts or URLs; if the sources
        don't say, say so. Don't use the web for things you already know or for
        the club's own creative content (name/description/slogan/terms).

        Fields you set with the propose_club tool:
        - club_name (REQUIRED) — the proper name only, filler stripped
          ("a boxing club called Iron Fist" → "Iron Fist").
        - country / currency — infer from any city/country mentioned
          ("in Dubai" → AE/AED, "Riyadh" → SA/SAR); default BH/BHD.
        - description — if the user hasn't really described the club, ask ONE easy
          general question (the sport, who it's for, the vibe) and then WRITE a
          polished 1–2 sentence description yourself. Never echo their words back.
        - slogan — GENERATE a short, catchy tagline that fits the club. Don't ask
          for it unless they want to choose one.
        - registration_requirements — GENERATE sensible things a new member needs
          to sign up for this kind of club (valid ID/CPR, recent photo, minimum
          age, medical clearance for combat sports, proof of payment…), as a short
          HTML <ul><li>…</li></ul>. Ask only if they want to customise.
        - registration_terms — GENERATE professional joining terms & conditions
          SPECIALISED to this club (its sport, name, country) as simple HTML
          (<h3>, <p>, <ul><li>). Cover membership, payment/refunds, conduct,
          liability/injury risk, and cancellation. Keep it reasonable, not a wall
          of text.

        (The slug is generated automatically — ignore it.)

        Behaviour:
        - Gather naturally from the conversation; infer and interpret.
        - For optional fields the user didn't give, GENERATE a good value rather
          than leaving it empty — especially description, slogan, requirements and
          terms. Only leave email empty unless they give one.
        - It's fine to ask a quick clarifying question, but once you have the name
          and a feel for the club, call propose_club with your best, fully-generated
          values. You can call it again to fix anything that came out wrong.
        - You do NOT create anything — propose_club drafts a proposal the human
          reviews and confirms. Never claim the club is already created.{$examplesBlock}
        PROMPT;
    }

    private function webEnabled(): bool
    {
        return (bool) config('copilot.web.enabled', true);
    }

    /** @return array<int,array<string,mixed>> */
    private function tools(): array
    {
        $tools = [];

        if ($this->webEnabled()) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'web_search',
                    'description' => 'Search the live web for current or external information. Returns a list of results (title, url, snippet). Follow up with web_fetch to read the most relevant ones before answering.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'The search query'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ];
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'web_fetch',
                    'description' => 'Fetch and read the main text of a web page by URL (usually one returned by web_search). Use it to get accurate facts before answering.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'url' => ['type' => 'string', 'description' => 'The absolute http(s) URL to read'],
                        ],
                        'required' => ['url'],
                    ],
                ],
            ];
        }

        $tools[] = [
            'type' => 'function',
            'function' => [
                'name' => 'find_club',
                'description' => 'Check whether a club already exists on the platform by name (partial match). Use it whenever the user asks if a club exists, or before proposing a club, to catch duplicate names.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'The club name (or part of it) to look up'],
                    ],
                    'required' => ['name'],
                ],
            ],
        ];

        $tools[] = [
            'type' => 'function',
            'function' => [
                'name' => 'propose_club',
                'description' => 'Stage a draft proposal to create a new club. Call this once you have at least the club name. The human reviews and confirms before anything is created.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'club_name' => ['type' => 'string', 'description' => "The club's display name"],
                        'country' => ['type' => 'string', 'description' => 'ISO2 country code, e.g. BH, SA, AE. Default BH.'],
                        'currency' => ['type' => 'string', 'description' => 'ISO currency code, e.g. BHD, SAR, AED. Default BHD.'],
                        'description' => ['type' => 'string', 'description' => 'Short description of the club'],
                        'slogan' => ['type' => 'string', 'description' => 'A short catchy tagline you generate to fit the club'],
                        'email' => ['type' => 'string', 'description' => "The club's contact email, if the user gave one"],
                        'registration_requirements' => ['type' => 'string', 'description' => 'What a new member must provide to register, as a short HTML unordered list (<ul><li>…</li></ul>). Generate sensible defaults if unspecified.'],
                        'registration_terms' => ['type' => 'string', 'description' => "The club's joining terms & conditions as simple HTML (<h3>, <p>, <ul><li>). Generate professional T&C specialised to this club."],
                    ],
                    'required' => ['club_name'],
                ],
            ],
        ];

        return $tools;
    }
}
