<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Copilot ("Coach") — Ollama-backed, page-aware AI assistant
    |--------------------------------------------------------------------------
    |
    | Thin-slice pilot: a super-admin can create a club conversationally from
    | /admin/clubs. The Ollama server is called SERVER-SIDE ONLY — the browser
    | never talks to it directly. Every write is staged as a draft proposal and
    | committed only after an explicit human "Create" (draft-then-confirm).
    |
    */

    // Master switch. When false, the Copilot endpoints return a friendly
    // "unavailable" reply and the UI degrades gracefully.
    'enabled' => (bool) env('COPILOT_ENABLED', true),

    // Ollama server. Reached only from the Laravel backend.
    'base_url' => rtrim(env('COPILOT_OLLAMA_URL', 'https://ollama.innovator.bh'), '/'),

    // Primary "doer" model. qwen3-coder:30b is the strongest tool-caller on the
    // server (MoE, ~3B active params so it's fast) — best for the create-club
    // tool loop. Swap per-capability once more pages are wired.
    'model' => env('COPILOT_MODEL', 'qwen3-coder:30b'),

    // Low temperature → deterministic tool-calling.
    'temperature' => (float) env('COPILOT_TEMPERATURE', 0.2),

    // Seconds to wait on the model (a 30B response can take a while).
    'timeout' => (int) env('COPILOT_TIMEOUT', 120),

    // Abuse guards on the conversation payload the client sends up.
    'max_messages' => (int) env('COPILOT_MAX_MESSAGES', 16),
    'max_message_length' => (int) env('COPILOT_MAX_MESSAGE_LENGTH', 4000),

    /*
    |--------------------------------------------------------------------------
    | Web access (search + fetch) — gives the offline Ollama model the internet
    |--------------------------------------------------------------------------
    |
    | Coach gets `web_search` (via a self-hosted SearXNG instance) and
    | `web_fetch` (an SSRF-guarded reader). Both run server-side; the model only
    | ever receives cleaned text + source URLs.
    |
    */
    'web' => [
        'enabled' => (bool) env('COPILOT_WEB_ENABLED', true),

        // Self-hosted SearXNG base URL (must have the JSON format enabled).
        'searxng_url' => rtrim(env('COPILOT_SEARXNG_URL', 'http://127.0.0.1:8888'), '/'),
        'search_timeout' => (int) env('COPILOT_SEARCH_TIMEOUT', 15),
        'max_results' => (int) env('COPILOT_WEB_MAX_RESULTS', 6),

        // Fetch guards.
        'fetch_timeout' => (int) env('COPILOT_FETCH_TIMEOUT', 15),
        'max_bytes' => (int) env('COPILOT_FETCH_MAX_BYTES', 3_000_000), // 3 MB cap
        'max_chars' => (int) env('COPILOT_FETCH_MAX_CHARS', 12000),     // text handed to model
        'max_redirects' => (int) env('COPILOT_FETCH_MAX_REDIRECTS', 3),

        // Extra host blocklist (private/loopback/reserved IPs are always blocked).
        'blocked_hosts' => array_filter(explode(',', (string) env('COPILOT_BLOCKED_HOSTS', ''))),

        // SearXNG runs on a private/loopback address, so allow the search call to
        // reach it even though web_fetch refuses private hosts.
    ],

];
