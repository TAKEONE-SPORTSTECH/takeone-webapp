<?php

/*
|--------------------------------------------------------------------------
| Content translation — which model writes it
|--------------------------------------------------------------------------
|
| ⚠️ THE PLATFORM IS NOT TIED TO ANY ONE AI PROVIDER, and this file is where
| that is true rather than merely claimed.
|
| Translation runs through a CHAIN, not a provider: an ordered list of text
| providers, tried in turn until one answers usefully. Claude down, a key
| expired, a rate limit, a local server rebooting — the next one takes it, and
| the reader never learns any of that happened.
|
| The chain builds itself from what you have configured under
| Admin → AI Providers (modality: text): the default first, then every other
| enabled provider, then the built-in Ollama server from config/copilot.php as
| a last resort. So "make it use my local model instead" is: add the provider,
| mark it default. Nothing here needs editing, and no code changes.
|
| WHAT COUNTS AS A PROVIDER — four drivers ship, and the second covers most of
| the world:
|
|   ollama      any Ollama server, local or on your own network.
|   openai      OpenAI, AND every OpenAI-COMPATIBLE server, which is nearly all
|               of them: LM Studio, vLLM, llama.cpp's server, text-generation-
|               webui, Groq, Together, OpenRouter, DeepSeek, Mistral, Fireworks.
|               Set `driver: openai` and point `base_url` at it. A local server
|               that wants no key takes any non-empty string.
|   anthropic   Claude.
|   gemini      Google.
|
| So running entirely on your own hardware is a configuration, not a rewrite.
*/

return [

    /*
    |--------------------------------------------------------------------------
    | The chain
    |--------------------------------------------------------------------------
    |
    | Leave null to build it automatically from the enabled text providers, in
    | the order the admin screen shows them (default first). That is what almost
    | everybody wants.
    |
    | Set it to an explicit ordered list of provider IDs to pin the order — for
    | instance to translate on a cheap local model and keep a paid one only as
    | the fallback for when the local one is off:
    |
    |     TRANSLATION_PROVIDERS=3,1
    |
    | An id that no longer exists is skipped rather than fatal, so deleting a
    | provider can never take translation down with it.
    */
    'providers' => array_values(array_filter(array_map(
        'intval',
        array_filter(explode(',', (string) env('TRANSLATION_PROVIDERS', '')))
    ))) ?: null,

    /*
    |--------------------------------------------------------------------------
    | Always keep the built-in local model as the last resort
    |--------------------------------------------------------------------------
    |
    | The Ollama server in config/copilot.php, appended to the end of the chain.
    | It costs nothing, it is on your own infrastructure, and it is the reason a
    | dead API key degrades the WORDING rather than the FEATURE.
    |
    | Turn it off only if that server is gone.
    */
    'fallback_to_local' => (bool) env('TRANSLATION_FALLBACK_LOCAL', true),

    /*
    |--------------------------------------------------------------------------
    | Translate content when a model's attribute is READ
    |--------------------------------------------------------------------------
    |
    | The switch behind App\Traits\TranslatesAttributes. With it on — the
    | default, and the point of the whole thing — `$event->title` and
    | `$feeOption->label` come back in the reader's language on every surface,
    | so no view, payload or serialiser can leak the source language by
    | forgetting to ask.
    |
    | ⚠️ It is a KILL SWITCH, not a feature toggle. Turning it off returns the
    | platform to resolving translations in the two places that used to do it
    | (the public poster payload and one member action) and leaves every other
    | surface reading the organiser's own words — which is a half-translated
    | product, but a working one. It exists because this trait sits on the read
    | path of the most-hit pages on the platform, and RULE #1 says a new thing
    | there has to have an off switch that does not need a deploy.
    |
    |     TRANSLATE_ATTRIBUTES=false
    |
    | Edit forms are unaffected either way: they are pinned to source text by
    | App\Http\Middleware\ReadsSourceContent.
    */
    'translate_attributes' => (bool) env('TRANSLATE_ATTRIBUTES', true),

    /*
    |--------------------------------------------------------------------------
    | Mark strings that fell back to English
    |--------------------------------------------------------------------------
    |
    | Wraps every unresolved interface string in ⟦…⟧ so a whole screen can be
    | audited at a glance — the untranslated words are the bracketed ones.
    |
    | On automatically on a local machine. Turn it on elsewhere only for a
    | deliberate audit, and turn it off again: it is legible to a developer and
    | alarming to a visitor. Counting happens either way and costs nothing;
    | `Translations::untranslated()` is where the numbers come out.
    */
    'mark_untranslated' => (bool) env('MARK_UNTRANSLATED', false),

    /*
    |--------------------------------------------------------------------------
    | Read interface strings from the database
    |--------------------------------------------------------------------------
    |
    | The switch behind the translation module's database-backed loader. On
    | by default and safe on by default: it is an OVERLAY, so an empty table
    | renders exactly what `lang/<code>/*.php` renders.
    |
    | Turning it off returns the platform to reading only the files — which is
    | a working product, just one whose generated languages a deploy can delete
    | and whose strings nobody can correct without one. It exists because this
    | sits on the read path of every page (RULE #1).
    |
    |     DATABASE_STRINGS=false
    */
    'database_strings' => (bool) env('DATABASE_STRINGS', true),

    /*
    |--------------------------------------------------------------------------
    | Models that must never translate
    |--------------------------------------------------------------------------
    |
    | Matched against a provider's model name. A match is dropped from the chain
    | for TRANSLATION work — the run then fails loudly rather than quietly
    | producing text from the wrong kind of model.
    |
    | ⚠️ Why this list exists. On 2026-09-09 the Anthropic account ran out of
    | credit; every Claude call returned HTTP 400, the chain fell through as
    | designed, and `qwen3-coder:30b` translated 19,666 interface strings into
    | sixty-seven languages without a single error being raised. In Albanian it
    | rendered the GOLD medal as "Medalja e zezë" — the black medal — and SILVER
    | as "the white medal". A native speaker reading the site is how it was
    | found.
    |
    | A code model is not a translator. It will answer anyway, fluently and
    | wrongly, which is the worst possible failure mode for text nobody on the
    | team can proofread.
    |
    | This is a FLOOR, not a quality bar: passing it only means a model is not
    | obviously the wrong tool. Whether a model writes GOOD Albanian is a
    | question only an Albanian speaker can answer — which is why every stored
    | string now records the model that wrote it.
    */
    /*
    |--------------------------------------------------------------------------
    | Proofread every batch after translating it
    |--------------------------------------------------------------------------
    |
    | A second call that hands the batch back to the model with the English
    | beside it and one instruction: correct the language, change nothing else.
    |
    | ⚠️ Why this is on by default. A Japanese speaker read the generated
    | interface and said it was "precise but wrong grammar"; an Albanian speaker
    | said the same in different words. That is what a bulk pass produces, and
    | it is not a sign of a bad model — sixty disconnected UI labels arrive with
    | no sentence around them, so the model spends its attention choosing the
    | right WORD for each and what suffers is everything that lives between
    | words: Japanese particles and politeness, Albanian definiteness and case,
    | German gender, Arabic construct state.
    |
    | It doubles the calls, which is the honest cost of the quality bar. Every
    | correction is validated before it is kept, and a failed edit pass leaves
    | the first translation exactly as it was — see InterfaceAgent::refine().
    */
    /*
    | Strings per request. Throughput varies enormously between models and an
    | over-large batch does not degrade gracefully — it times out at the proxy
    | (HTTP 524) and stops the run. Sixty suits a frontier model; a small
    | self-hosted one needs far fewer.
    */
    'interface_batch' => (int) env('TRANSLATION_BATCH', 60),

    'refine' => (bool) env('TRANSLATION_REFINE', true),

    // Editing is not a creative task; a warm model rewrites instead of
    // correcting, which is the one way this pass can make things worse.
    'refine_temperature' => (float) env('TRANSLATION_REFINE_TEMPERATURE', 0.1),

    'unfit_models' => [
        '/coder/i',
        '/(^|[-_\/])code(-|_|$)/i',
        '/starcoder|codellama|codegemma|codestral|deepseek-coder|qwen[\w.]*-coder/i',
        '/embed(ding)?/i',
        '/whisper|tts|stable-diffusion|flux/i',
    ],

    /*
    |--------------------------------------------------------------------------
    | Refusals a person has looked at and accepted
    |--------------------------------------------------------------------------
    |
    | `translate:interface --check` fails on any string that is missing or that
    | the validator rejects, and that strictness is the point: a language must
    | not be served while a reader would meet an English word in it.
    |
    | A few strings can never satisfy the validator, and are still correct. The
    | guard that refuses a translation for changing a NUMBER exists to stop a
    | model quietly altering a price or a count — but a date EXAMPLE
    | ("e.g. Jul 3") is rendered in Chinese as "7月3日", which introduces a 7.
    | The guard is right to fire and the translation is right to keep.
    |
    | So the escape hatch is explicit, per string, with a reason, and visible in
    | the check's output as `accepted` rather than passing in silence. Keyed
    | "<locale>:<file id>.<key>".
    |
    | ⚠️ This is NOT a place to make a failing check go quiet. Every line here
    | says a person read the translation and decided it was right. Adding one
    | without doing that puts back exactly the silence this whole layer removed.
    */
    'accepted_refusals' => [
        'zh:personal.personal_event_create_fixture_date_ph' =>
            'A date example. "Jul 3" is "7月3日" in Chinese, which the number guard reads as an invented 7.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ask the model to reply in JSON mode where the driver supports it
    |--------------------------------------------------------------------------
    |
    | Ollama gets `format: json`; OpenAI-compatible servers get
    | `response_format: {type: json_object}`. Both constrain decoding so the
    | reply IS JSON rather than JSON wrapped in an apology.
    |
    | This matters far more for a self-hosted 7B–30B model than for a frontier
    | one, and it is exactly the case this setting exists to serve: without it a
    | small local model is unreliable at structured output and the whole idea of
    | running translation on your own hardware falls down.
    |
    | Anthropic and Gemini are not sent a flag — they are asked in the prompt,
    | which they honour — and the parser tolerates a code fence from anybody.
    */
    'json_mode' => (bool) env('TRANSLATION_JSON_MODE', true),

    /*
    |--------------------------------------------------------------------------
    | How long one language may take, and how much it may cost
    |--------------------------------------------------------------------------
    |
    | Per PROVIDER attempt, so a chain of three cannot hold a queue worker for
    | three times the job's own timeout.
    */
    'timeout' => (int) env('TRANSLATION_TIMEOUT', 90),

    'max_tokens' => (int) env('TRANSLATION_MAX_TOKENS', 8000),

    /*
    |--------------------------------------------------------------------------
    | How hard the model should think about it
    |--------------------------------------------------------------------------
    |
    | ⚠️ The single biggest thing standing between a visitor and their language.
    |
    | Current Claude models think by DEFAULT, at `high` effort, unless told
    | otherwise. Measured on a real event: the prompt is ~1,200 tokens and the
    | answer ~800, yet 2,000-3,000 tokens were being generated — the difference
    | was deliberation, and it was most of the twenty-five seconds somebody
    | stood watching a spinner for.
    |
    | Translation is not a reasoning problem. The document arrives whole, every
    | key is given, and the job is to say the same facts in another language —
    | which is what `low` is for. Raise it to `medium` if a language reads more
    | flatly than it should; measure with `translate:compare` rather than
    | guessing.
    |
    | Only the Anthropic driver reads this; the others ignore it.
    */
    'effort' => env('TRANSLATION_EFFORT', 'low'),

    /*
    |--------------------------------------------------------------------------
    | Work out each field's own language, instead of trusting one column
    |--------------------------------------------------------------------------
    |
    | An event has ONE `source_locale`, and real events are not written in one
    | language. Event 65 on stage has an English title, an Arabic description
    | and Arabic fee labels, and the column says English — so an English reader
    | was shown the Arabic paragraph raw and the platform was sure it was right.
    |
    | With this on, every field is judged on its own — by script, and by the
    | event's declared source language where the script cannot tell (Latin
    | text) — and:
    |
    |   · a field NOT in the reader's language is translated, even when the
    |     event claims to already be in that language;
    |   · a field ALREADY in the reader's language is left exactly as the
    |     organiser wrote it — not sent, not paid for, not "improved".
    |
    | On event 65 that is 4 fields for an English reader (the Arabic ones) and
    | 13 for an Arabic reader (skipping the 4 that are already Arabic) instead
    | of 0 and 17 — a bug fixed and a third of the output tokens saved on the
    | same page.
    |
    | Turn it off to fall back to the old whole-event assumption.
    */
    'detect_source' => (bool) env('TRANSLATION_DETECT_SOURCE', true),

    'temperature' => (float) env('TRANSLATION_TEMPERATURE', 0.3),

];
