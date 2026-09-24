<?php

namespace App\Clubs\Copilot;

use App\Clubs\Models\Tenant;
use App\Clubs\Support\ClubCreationService;
use App\Members\Models\User;
use App\Support\Copilot\CopilotSkill;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Coach can create a club — the original club-only assistant
 * (App\Services\Copilot\CopilotService + Admin\CopilotController@apply) as a
 * skill, with the same fields, rules, generated content and save path: owner
 * forced to the acting super-admin, ClubCreationService::create(), registration
 * HTML sanitised by the Tenant mutators.
 */
class CreateClubSkill implements CopilotSkill
{
    private const RULES = [
        'club_name' => 'required|string|max:255',
        'country' => 'nullable|string|max:2',
        'currency' => 'nullable|string|max:3',
        'description' => 'nullable|string|max:1000',
        'slogan' => 'nullable|string|max:100',
        'email' => 'nullable|email|max:255',
        'registration_requirements' => 'nullable|string|max:20000',
        'registration_terms' => 'nullable|string|max:20000',
    ];

    public function key(): string
    {
        return 'create_club';
    }

    public function label(string $locale): string
    {
        return __('copilot.skill_club', [], $locale);
    }

    public function allowedFor(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function pages(): array
    {
        return ['admin.platform.clubs*', 'admin.platform.index'];
    }

    public function instructions(string $locale, User $user): string
    {
        $examples = $this->termsExamples();
        $block = $examples !== '' ? "\nSTYLE REFERENCE — existing clubs' terms (adapt tone and structure, never copy names or specifics):\n{$examples}" : '';

        return <<<TXT
        A sports club (a tenant with its own admin panel). Before proposing, CALL
        find_club to catch duplicate names; if taken, say so and suggest a tweak.
        Fields for propose_create_club:
        - club_name (REQUIRED) — the proper name only ("a boxing club called Iron Fist" → "Iron Fist").
        - country / currency — infer from any city/country ("in Dubai" → AE/AED, "Riyadh" → SA/SAR); default BH/BHD.
        - description — if the user hasn't described the club, ask ONE easy question (sport, who it's for, the vibe), then WRITE a polished 1–2 sentence description. Never echo their words.
        - slogan — GENERATE a short, catchy tagline.
        - registration_requirements — GENERATE what a new member needs to sign up (ID/CPR, photo, minimum age, medical clearance for combat sports, proof of payment…) as a short HTML <ul><li>…</li></ul>.
        - registration_terms — GENERATE professional joining terms SPECIALISED to this club (sport, name, country) as simple HTML (<h3>, <p>, <ul><li>): membership, payment/refunds, conduct, liability/injury risk, cancellation. Reasonable, not a wall of text.
        - email — only if they give one.
        The slug is generated automatically. For optional fields they didn't give, GENERATE a good value rather than leaving it empty.{$block}
        TXT;
    }

    public function parameters(): array
    {
        return [
            'club_name' => ['type' => 'string', 'description' => "The club's display name"],
            'country' => ['type' => 'string', 'description' => 'ISO2 country code, e.g. BH, SA, AE. Default BH.'],
            'currency' => ['type' => 'string', 'description' => 'ISO currency code, e.g. BHD, SAR, AED. Default BHD.'],
            'description' => ['type' => 'string', 'description' => 'Short description of the club'],
            'slogan' => ['type' => 'string', 'description' => 'A short catchy tagline you generate to fit the club'],
            'email' => ['type' => 'string', 'description' => "The club's contact email, if the user gave one"],
            'registration_requirements' => ['type' => 'string', 'description' => 'What a new member must provide, as a short HTML unordered list.'],
            'registration_terms' => ['type' => 'string', 'description' => "The club's joining terms & conditions as simple HTML (<h3>, <p>, <ul><li>)."],
        ];
    }

    public function required(): array
    {
        return ['club_name'];
    }

    public function lookupTools(): array
    {
        return [['type' => 'function', 'function' => [
            'name' => 'find_club',
            'description' => 'Check whether a club already exists on the platform by name (partial match). Use it before proposing a club, or when asked if one exists.',
            'parameters' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string', 'description' => 'The club name (or part of it)']], 'required' => ['name']],
        ]]];
    }

    public function lookup(User $user, string $tool, array $args): array
    {
        $name = trim((string) ($args['name'] ?? ''));
        if ($name === '') {
            return ['found' => false, 'message' => 'No name given to look up.'];
        }
        $matches = Tenant::query()->where('club_name', 'like', '%'.$name.'%')->limit(5)->get(['club_name', 'slug', 'country']);

        return $matches->isEmpty()
            ? ['found' => false, 'query' => $name, 'message' => "No club matching \"{$name}\" exists yet."]
            : ['found' => true, 'query' => $name, 'matches' => $matches->map(fn ($c) => ['name' => $c->club_name, 'slug' => $c->slug, 'country' => $c->country])->all()];
    }

    public function stage(User $user, array $args, string $locale): array
    {
        $v = Validator::make($args, self::RULES);
        if ($v->fails()) {
            return ['ok' => false, 'errors' => $v->errors()->all()];
        }
        $d = $v->validated();
        $data = [
            'club_name' => $d['club_name'],
            'slug' => $this->uniqueSlug($d['club_name']),
            'country' => strtoupper($d['country'] ?? 'BH'),
            'currency' => strtoupper($d['currency'] ?? 'BHD'),
            'description' => $d['description'] ?? null,
            'slogan' => $d['slogan'] ?? null,
            'email' => $d['email'] ?? null,
            'registration_requirements' => $d['registration_requirements'] ?? null,
            'registration_terms' => $d['registration_terms'] ?? null,
        ];
        $t = fn ($k) => __('copilot.'.$k, [], $locale);
        $rows = array_values(array_filter([
            ['label' => $t('f_name'), 'value' => $data['club_name']],
            ['label' => $t('f_handle'), 'value' => $data['slug'], 'mono' => true],
            ['label' => $t('f_country'), 'value' => $data['country']],
            ['label' => $t('f_currency'), 'value' => $data['currency']],
            $data['slogan'] ? ['label' => $t('f_slogan'), 'value' => $data['slogan']] : null,
            $data['email'] ? ['label' => $t('f_email'), 'value' => $data['email']] : null,
            $data['description'] ? ['label' => $t('f_description'), 'value' => $data['description'], 'long' => true] : null,
            $data['registration_requirements'] ? ['label' => $t('f_requirements'), 'value' => $t('generated'), 'flag' => true] : null,
            $data['registration_terms'] ? ['label' => $t('f_terms'), 'value' => $t('generated'), 'flag' => true] : null,
        ]));

        return ['ok' => true, 'data' => $data, 'rows' => $rows, 'title' => $data['club_name']];
    }

    public function apply(User $user, array $data): array
    {
        $v = Validator::make($data, self::RULES);
        if ($v->fails()) {
            return ['ok' => false, 'message' => __('copilot.error_create_any')];
        }
        $d = $v->validated();
        $reviewed = is_string($data['slug'] ?? null) ? $data['slug'] : null;
        $d['slug'] = ($reviewed && ! Tenant::where('slug', $reviewed)->exists()) ? $reviewed : $this->uniqueSlug($d['club_name']);
        $d['country'] = strtoupper($d['country'] ?? 'BH');
        $d['currency'] = strtoupper($d['currency'] ?? 'BHD');
        $d['status'] = 'active';
        $d['public_profile_enabled'] = true;
        $d['owner_user_id'] = $user->id;   // never the model's or the client's choice

        $club = app(ClubCreationService::class)->create($d);

        return ['ok' => true, 'message' => __('copilot.toast_created', ['name' => $club->club_name]), 'title' => $club->club_name,
            'url' => route('admin.club.dashboard', $club->slug), 'open_label' => __('copilot.open_club')];
    }

    private function termsExamples(): string
    {
        $out = [];
        foreach (Tenant::query()->whereNotNull('registration_terms')->where('registration_terms', '!=', '')->limit(2)->get(['club_name', 'registration_terms']) as $club) {
            $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $club->registration_terms)));
            if ($text !== '') {
                $out[] = 'From "'.$club->club_name.'": '.mb_substr($text, 0, 600);
            }
        }

        return implode("\n\n", $out);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'club-'.Str::lower(Str::random(6));
        $slug = $base;
        for ($i = 2; Tenant::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
