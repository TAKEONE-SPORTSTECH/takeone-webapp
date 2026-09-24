<?php

namespace App\Federations\Copilot;

use App\Federations\Models\Federation;
use App\Federations\Services\FederationDirectory;
use App\Federations\Services\FederationProfiles;
use App\Members\Models\User;
use App\Support\Copilot\CopilotSkill;
use App\Support\Copilot\HasOwnConversation;
use App\Support\Countries;
use Illuminate\Support\Facades\Validator;

/**
 * Coach can create a federation — through FederationProfiles, the same rules
 * and the same save as the /admin/federations form. Only the "can open" facts
 * are required; the rest the super-admin completes on the record afterwards.
 * Bank details are taken only if the user states them (and the IBAN still has
 * to pass its checksum); the model is told never to look them up or guess.
 */
class CreateFederationSkill implements CopilotSkill, HasOwnConversation
{
    private const FIELDS = ['name', 'short_name', 'country', 'sports', 'owner_email', 'email', 'phone_code', 'phone_number',
        'address', 'website', 'founded_year', 'languages', 'international_federation', 'color', 'office_hours',
        'registration_number', 'registration_authority', 'bank_account_name', 'bank_name', 'iban', 'swift',
        'timezone', 'currency', 'olympic_recognised'];

    /** The federation's officers, as the form has them; each is a name and an email. */
    private const OFFICERS = ['president', 'secretary_general', 'technical_director', 'head_referees', 'anti_doping', 'safeguarding'];

    /** What the conversation may gather: the form's fields, the owner's name, and every officer. */
    private const GATHERED = [...self::FIELDS, 'owner_name', ...self::OFFICERS,
        'president_email', 'secretary_general_email', 'technical_director_email', 'head_referees_email', 'anti_doping_email', 'safeguarding_email'];

    public function __construct(private FederationProfiles $profiles) {}

    public function key(): string
    {
        return 'create_federation';
    }

    public function label(string $locale): string
    {
        return __('copilot.skill_federation', [], $locale);
    }

    public function allowedFor(User $user): bool
    {
        return $user->isSuperAdmin() && (bool) config('federations.enabled');
    }

    public function pages(): array
    {
        return ['admin.federations.*'];
    }

    public function instructions(string $locale, User $user): string
    {
        $sports = implode(', ', FederationDirectory::SPORTS);
        $me = $user->email;

        return <<<TXT
        A sports federation: a governing body that runs competitions and that
        clubs affiliate with (not a club).
        HOW THIS CONVERSATION WORKS — it is created when the user says "done":
        - GATHER over the conversation. Take whatever the user tells you, in any
          order and in several messages; ask for what is missing one or two
          things at a time, like a person would.
        - End each reply with one short line recapping what you have so far and
          what is still missing (e.g. "So far: BJF · Judo · BH — still need: email,
          phone, address."), so nothing is lost in a long chat.
        - Do NOT call propose_create_federation while the user is still giving
          details. Call it only when they say they are done ("done", "that's all",
          "create it", "خلاص", "تم" …), with user_said_done = true and every fact
          from the whole conversation. That call CREATES the federation.
        - Once every required field is in, say so and invite them to add optional
          details or say "done" to create it. Don't ask "shall I propose?".
        - If they say done but a required field is missing, don't call the tool —
          tell them exactly which ones are still needed.
        Before creating, CALL find_federation
        to catch duplicates, and find_account to confirm the owner has a TakeOne
        account (they must; they sign in at /federation/login).
        Fields for propose_create_federation — REQUIRED (the quick create): name,
        country, sports, owner_email. EVERYTHING else is optional: take it if the
        user gives it, never ask for it before creating, never invent it — the
        record shows what is left to add afterwards.
        - name — the official full name ("Bahrain Karate Federation"); short_name — its acronym (BKF).
        - country — ISO2; infer from the name or city. sports — one or more of: {$sports} (exact spelling).
        - owner_email — who runs its dashboard. The person you are talking to is {$me}. If they say they will run
          it themselves, or name nobody, set owner_email to {$me} WITHOUT asking. Ask only if they name someone else
          without giving that person's email.
        - email / phone_code (+973) / phone_number / address — the federation's OFFICIAL contact and headquarters.
          You may web_search the federation's own official site for its address, phone, website, year founded and
          international federation — only use what a source actually says, and tell the user where it came from.
          If you can't find the contact details, ASK the user; never invent them.
        - website, founded_year, international_federation (e.g. World Karate Federation), languages (en/ar/fr),
          office_hours, color (#rrggbb) — optional.
        - registration_number / registration_authority, bank_account_name / bank_name / iban / swift — ONLY if the
          user tells you. NEVER search for or guess registration or bank details.
        TXT;
    }

    public function parameters(): array
    {
        $s = fn ($d) => ['type' => 'string', 'description' => $d];

        return [
            'name' => $s('Official full name'),
            'short_name' => $s('Acronym / short name, e.g. BKF'),
            'country' => $s('ISO2 country code, e.g. BH'),
            'sports' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => FederationDirectory::SPORTS], 'description' => 'Sports it governs'],
            'owner_email' => $s('Email of the existing TakeOne account that will run the dashboard'),
            'email' => $s('Official contact email'),
            'phone_code' => $s('Calling code, e.g. +973'),
            'phone_number' => $s('Phone number without the calling code'),
            'address' => $s('Headquarters address'),
            'website' => $s('https:// website'),
            'founded_year' => ['type' => 'integer', 'description' => 'Year founded'],
            'languages' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => array_keys(FederationProfiles::LANGUAGES)]],
            'international_federation' => $s('International federation it belongs to'),
            'office_hours' => $s('Office hours'),
            'color' => $s('Brand colour #rrggbb'),
            'registration_number' => $s('Only if the user states it'),
            'registration_authority' => $s('Only if the user states it'),
            'bank_account_name' => $s('Only if the user states it'),
            'bank_name' => $s('Only if the user states it'),
            'iban' => $s('Only if the user states it'),
            'swift' => $s('Only if the user states it'),
        ];
    }

    public function required(): array
    {
        // The quick create's four essentials; the short name is worked out.
        return ['name', 'country', 'sports', 'owner_email'];
    }

    public function lookupTools(): array
    {
        $tool = fn ($name, $desc, $prop, $pdesc) => ['type' => 'function', 'function' => ['name' => $name, 'description' => $desc,
            'parameters' => ['type' => 'object', 'properties' => [$prop => ['type' => 'string', 'description' => $pdesc]], 'required' => [$prop]]]];

        return [
            $tool('find_federation', 'Check whether a federation already exists on the platform by name (partial match).', 'name', 'Name or part of it'),
            $tool('find_account', 'Check whether a TakeOne account exists for an email (the federation owner must have one). Returns their name only.', 'email', 'Email address'),
        ];
    }

    public function lookup(User $user, string $tool, array $args): array
    {
        if ($tool === 'find_account') {
            $email = strtolower(trim((string) ($args['email'] ?? '')));
            $u = $email !== '' ? User::where('email', $email)->first(['id', 'full_name', 'name']) : null;

            return $u ? ['exists' => true, 'name' => $u->full_name ?: $u->name] : ['exists' => false, 'message' => 'No TakeOne account uses that email. They must register first.'];
        }

        $name = trim((string) ($args['name'] ?? ''));
        $m = $name === '' ? collect() : Federation::where('name', 'like', '%'.$name.'%')->orWhere('short_name', $name)->limit(5)->get(['name', 'short_name', 'country']);

        return $m->isEmpty() ? ['found' => false, 'message' => "No federation matching \"{$name}\" exists yet."]
            : ['found' => true, 'matches' => $m->map(fn ($f) => ['name' => $f->name, 'short' => $f->short_name, 'country' => $f->country])->all()];
    }

    public function stage(User $user, array $args, string $locale): array
    {
        $input = $this->normalise($args, $user);
        $v = Validator::make($input, $this->rules());
        if ($v->fails()) {
            return ['ok' => false, 'errors' => $v->errors()->all()];
        }
        $d = $v->validated();
        $t = fn ($k) => __('federations::messages.'.$k, [], $locale);
        $row = fn ($label, $value, $extra = []) => $value !== null && $value !== '' && $value !== [] ? ['label' => $label, 'value' => (string) $value] + $extra : null;

        return ['ok' => true, 'data' => $d, 'title' => $d['name'], 'rows' => array_values(array_filter([
            $row($t('f_name'), $d['name']),
            $row($t('f_short'), ($d['short_name'] ?? '') ?: FederationProfiles::acronym($d['name'])),
            $row($t('f_country'), $d['country']),
            $row($t('f_sports'), implode(', ', $d['sports'])),
            $row($t('sec_owner'), $d['owner_email']),
            $row($t('f_email'), $d['email'] ?? null),
            $row($t('f_phone'), ! empty($d['phone_number']) ? trim(($d['phone_code'] ?? '').' '.$d['phone_number']) : null),
            $row($t('f_address'), $d['address'] ?? null, ['long' => true]),
            $row($t('f_website'), $d['website'] ?? null),
            $row($t('f_founded'), $d['founded_year'] ?? null),
            ...array_map(fn ($r) => $row($t('officer_'.$r), trim(($d['officers'][$r]['name'] ?? '').(! empty($d['officers'][$r]['email']) ? ' · '.$d['officers'][$r]['email'] : ''), ' ·')), self::OFFICERS),
            $row($t('f_intl'), $d['international_federation'] ?? null),
            $row($t('f_iban'), $d['iban'] ?? null, ['mono' => true]),
        ]))];
    }

    public function apply(User $user, array $data): array
    {
        $v = Validator::make($data, $this->rules());
        if ($v->fails()) {
            return ['ok' => false, 'message' => implode(' ', $v->errors()->all())];
        }
        $saved = $this->profiles->save($user, null, $v->validated(), null, [])['federation'];
        // Placeholders the assistant filled in so creation was never blocked — kept
        // on the audit trail and named in the toast, so they are corrected later.
        $placeholders = array_values(array_intersect((array) ($data['_placeholders'] ?? []), self::GATHERED));
        activity('federations')->causedBy($user)->performedOn($saved)
            ->withProperties($placeholders ? ['placeholders' => $placeholders] : [])
            ->log('Federation created by Coach');
        $message = __('copilot.toast_created_federation', ['name' => $saved->name]).' — '.__('copilot.federation_finish_later');
        if ($placeholders) {
            $message .= ' — '.__('copilot.placeholders_note', ['fields' => implode(', ', array_map(fn ($f) => str_replace('_', ' ', $f), $placeholders))]);
        }

        return ['ok' => true, 'message' => $message, 'title' => $saved->name,
            // ?welcome=1: the record opens on the nudge listing what is left to add.
            'url' => route('admin.federations.edit', $saved->uuid).'?welcome=1', 'open_label' => __('copilot.open_federation')];
    }

    public function conversationPrompt(User $user, string $locale): string
    {
        return strtr((string) file_get_contents(__DIR__.'/prompts/create-federation.md'), [
            '{{ME_NAME}}' => (string) ($user->full_name ?: $user->name),
            '{{ME_EMAIL}}' => (string) $user->email,
        ]);
    }

    public function absorb(array $fields, array $extracted, User $user): array
    {
        // A person named anew (a name with no email) replaces whoever held the
        // slot: drop the old email so the new name is looked up, not the old one.
        foreach ($this->slots() as [$nameKey, $emailKey]) {
            // An email given where a name goes is the email.
            if (isset($extracted[$nameKey]) && is_string($extracted[$nameKey]) && filter_var(trim($extracted[$nameKey]), FILTER_VALIDATE_EMAIL)) {
                $extracted[$emailKey] ??= trim($extracted[$nameKey]);
                unset($extracted[$nameKey], $fields[$nameKey]);
            }
            if (isset($extracted[$nameKey]) && ! isset($extracted[$emailKey])) {
                unset($fields[$emailKey]);
            }
        }
        foreach (array_intersect_key($extracted, array_flip(self::GATHERED)) as $key => $value) {
            $value = match ($key) {
                'sports', 'languages' => collect((array) $value)->filter(fn ($v) => is_string($v) && trim($v) !== '')
                    ->map(fn ($v) => mb_substr(trim($v), 0, 60))->unique()->take(12)->values()->all(),
                'olympic_recognised' => is_bool($value) ? $value : null,
                'founded_year' => is_numeric($value) ? (int) $value : null,
                'owner_email', 'email', 'president_email', 'secretary_general_email', 'technical_director_email',
                'head_referees_email', 'anti_doping_email', 'safeguarding_email' => is_scalar($value) ? mb_strtolower(mb_substr(trim((string) $value), 0, 191)) : null,
                default => is_scalar($value) ? mb_substr(trim((string) $value), 0, 300) : null,
            };
            if ($value !== null && $value !== '' && $value !== []) {
                $fields[$key] = $value;
            }
        }

        return $fields;
    }

    public function complete(array $fields, User $user): array
    {
        $placeholders = [];
        $fill = function (string $key, $value, bool $flag = true) use (&$fields, &$placeholders) {
            if (($fields[$key] ?? null) === null || $fields[$key] === '' || $fields[$key] === []) {
                $fields[$key] = $value;
                if ($flag) {
                    $placeholders[] = $key;
                }
            }
        };

        // The quick create: only the four essentials are ever filled in, and
        // only with what is safe to assume. Everything else stays BLANK — the
        // record then nudges for it (partials/nudge) — never a placeholder.
        $fill('country', 'BH', false);
        // A name can be suggested; a sport never is (sports stays as given, and
        // stage() refuses without one, so the assistant asks for it).
        if (! empty($fields['sports'])) {
            $sport = (string) ((array) $fields['sports'])[0];
            $fill('name', trim((Countries::name((string) $fields['country']) ?? 'Bahrain').' '.$sport.' Federation'), false);
        }
        // The owner is the person talking unless they named someone. A named
        // owner not found on TakeOne is NEVER swapped for the person talking.
        if (empty($fields['_owner_unresolved'])) {
            $fill('owner_email', $user->email, false);
            $fill('owner_name', $user->full_name ?: $user->name, false);
        }
        // Settings with a sensible default, not facts about the federation.
        $fill('timezone', 'Asia/Bahrain', false);
        $fill('currency', 'BHD', false);
        $fill('languages', ['en', 'ar'], false);

        return ['fields' => $fields, 'placeholders' => $placeholders];
    }

    /**
     * Look up every person the user named — the owner and each officer — on
     * TakeOne. Given an email: the member who uses it. Given a name: the members
     * called that; one match lends its email, several are put to the user.
     * The owner must be a member; an officer may be anyone, so a name or email
     * with no account behind it is kept as typed.
     */
    public function resolve(array $fields, User $user): array
    {
        $lookups = (array) ($fields['_lookups'] ?? []);
        $seen = (array) ($fields['_resolved'] ?? []);
        $changed = false;

        foreach ($this->slots() as $slot => [$nameKey, $emailKey]) {
            $name = trim((string) ($fields[$nameKey] ?? ''));
            $email = trim((string) ($fields[$emailKey] ?? ''));
            $sig = md5($name.'|'.$email);
            if (($seen[$slot] ?? null) === $sig) {
                continue;
            }
            $changed = true;
            $owner = $slot === 'owner';
            unset($lookups[$slot]);

            if ($name === '' && $email === '') {
                unset($seen[$slot]);
                if ($owner) {
                    unset($fields['_owner_unresolved']);
                }

                continue;
            }

            if ($email !== '') {
                $u = User::where('email', mb_strtolower($email))->first(['id', 'full_name', 'name', 'email']);
                if ($u) {
                    $fields[$nameKey] = $u->full_name ?: $u->name;
                    $lookups[$slot] = ['status' => 'matched', 'name' => $fields[$nameKey], 'email' => $u->email];
                } else {
                    $lookups[$slot] = $owner ? ['status' => 'no_account_with_this_email', 'email' => $email] : ['status' => 'kept_as_typed', 'email' => $email];
                }
            } else {
                $term = str_replace(['%', '_'], '', $name);
                $like = '%'.$term.'%';
                $found = mb_strlen($term) < 2 ? collect() : User::query()
                    ->where(fn ($q) => $q->where('full_name', 'like', $like)->orWhere('name', 'like', $like))
                    ->whereNotNull('email')->where('email', '!=', '')
                    ->orderBy('full_name')->limit(6)->get(['id', 'full_name', 'name', 'email']);
                if ($found->count() === 1) {
                    $u = $found->first();
                    $fields[$nameKey] = $u->full_name ?: $u->name;
                    $fields[$emailKey] = $u->email;
                    $lookups[$slot] = ['status' => 'matched', 'name' => $fields[$nameKey], 'email' => $u->email];
                } elseif ($found->count() > 1) {
                    $lookups[$slot] = ['status' => 'several_members_match', 'searched' => $name,
                        'candidates' => $found->take(5)->map(fn ($u) => ['name' => $u->full_name ?: $u->name, 'email' => $u->email])->all()];
                } else {
                    $lookups[$slot] = $owner ? ['status' => 'no_member_found', 'searched' => $name] : ['status' => 'kept_as_typed', 'name' => $name];
                }
            }

            if ($owner) {
                if (($lookups[$slot]['status'] ?? '') === 'matched') {
                    unset($fields['_owner_unresolved']);
                } else {
                    $fields['_owner_unresolved'] = true;
                    unset($fields['owner_email']);
                }
            }
            $seen[$slot] = md5(trim((string) ($fields[$nameKey] ?? '')).'|'.trim((string) ($fields[$emailKey] ?? '')));
        }

        $fields['_lookups'] = $lookups;
        $fields['_resolved'] = $seen;

        return ['fields' => $fields, 'lookups' => $lookups, 'changed' => $changed];
    }

    /** @return array<string, array{0: string, 1: string}> slot => [name key, email key] */
    private function slots(): array
    {
        $slots = ['owner' => ['owner_name', 'owner_email']];
        foreach (self::OFFICERS as $role) {
            $slots[$role] = [$role, $role.'_email'];
        }

        return $slots;
    }

    /** The form's rules, without the file inputs a conversation cannot carry. */
    private function rules(): array
    {
        return array_diff_key($this->profiles->rules(null), array_flip(['logo', 'documents', 'documents.*']));
    }

    private function normalise(array $args, User $user): array
    {
        $in = array_intersect_key($args, array_flip([...self::FIELDS, ...self::OFFICERS, ...array_map(fn ($r) => $r.'_email', self::OFFICERS)]));
        $in['owner_email'] = strtolower(trim((string) ($in['owner_email'] ?? $user->email)));
        if (isset($in['country'])) {
            $in['country'] = strtoupper(trim((string) $in['country']));
        }
        $canon = collect(FederationDirectory::SPORTS)->keyBy(fn ($s) => mb_strtolower($s));
        $in['sports'] = collect((array) ($in['sports'] ?? []))->map(fn ($s) => $canon[mb_strtolower(trim((string) $s))] ?? $s)->unique()->values()->all();
        foreach (self::OFFICERS as $role) {
            foreach (['name' => $role, 'email' => $role.'_email'] as $part => $key) {
                if (isset($in[$key]) && trim((string) $in[$key]) !== '') {
                    $in['officers'][$role][$part] = $part === 'email' ? strtolower(trim((string) $in[$key])) : trim((string) $in[$key]);
                }
                unset($in[$key]);
            }
        }
        if (isset($in['phone_code'])) {
            $in['phone_code'] = '+'.ltrim(preg_replace('/[^\d+]/', '', (string) $in['phone_code']), '+');
        }

        return $in;
    }
}
