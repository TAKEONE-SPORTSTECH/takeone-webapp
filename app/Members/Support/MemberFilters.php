<?php

namespace App\Members\Support;

use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Support\Countries;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The advanced filter behind /admin/members — one place that decides what may be
 * filtered on, and turns that into SQL.
 *
 * Everything here is a WHITELIST. A request may only ask for a value this class
 * already knows about: an unknown `missing` key, a gender that is not
 * Male/Female, a club id the platform does not have, a nationality nobody
 * stores — each is dropped rather than passed to the query. Nothing the client
 * sends reaches SQL except as a bound parameter, and the two raw expressions
 * below are literals written here, never composed from input.
 *
 * It answers one real question the admin list could not: "who is missing a
 * phone number?" — and the seven other shapes of the same question, because a
 * platform full of people entered off a paper sheet at a weigh-in (see *Who
 * Fills The Form Decides What It Demands*) accumulates blanks by design, and
 * somebody has to be able to find them.
 */
final class MemberFilters
{
    /** Fields a member can be missing. The vocabulary of the "Missing data" group. */
    public const MISSING = ['phone', 'email', 'birthdate', 'gender', 'nationality', 'photo', 'club'];

    /** Independent account toggles. */
    public const FLAGS = ['unclaimed', 'trainer', 'hidden'];

    /** Email-verification tri-state. */
    public const VERIFIED = ['any', 'yes', 'no'];

    /**
     * The phone number actually on file, or '' when there is none.
     *
     * `users.mobile` is a JSON object cast to an array, and it is blank in three
     * different ways: the column is NULL, the column holds something that is not
     * JSON at all (legacy rows), or the JSON is present with a null `number`
     * inside it — that last one is the common case, because a form that captured
     * a dialling code but no digits still wrote `{"code":"+973","number":null}`.
     * A naive `WHERE mobile IS NULL` finds none of those.
     *
     * The CASE is not decoration: SQL does not promise left-to-right evaluation
     * of an OR, so `json_valid(x) = 0 OR json_extract(x, …) = ''` can still run
     * json_extract against a non-JSON value and error. The guard has to be
     * inside the same expression.
     */
    private const PHONE_SQL = "case when json_valid(coalesce(mobile, '')) = 1 then coalesce(trim(json_extract(mobile, '\$.number')), '') else '' end";

    private function __construct(
        /** @var list<string> */
        public readonly array $missing,
        public readonly ?string $gender,
        public readonly ?int $ageMin,
        public readonly ?int $ageMax,
        public readonly ?string $nationality,
        /** 'none', a tenant id as a string, or null. */
        public readonly ?string $club,
        public readonly string $verified,
        /** @var list<string> */
        public readonly array $flags,
        public readonly ?string $joinedFrom,
        public readonly ?string $joinedTo,
    ) {}

    /**
     * Read the filters out of a request, keeping only what is known-good.
     */
    public static function from(Request $request): self
    {
        $list = function ($value, array $allowed): array {
            $value = is_string($value) ? explode(',', $value) : $value;

            return is_array($value)
                ? array_values(array_intersect($allowed, array_map('strval', $value)))
                : [];
        };

        $age = function ($value): ?int {
            if ($value === null || $value === '' || ! is_numeric($value)) {
                return null;
            }

            return max(0, min(120, (int) $value));
        };

        $date = function ($value): ?string {
            if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return null;
            }

            try {
                return Carbon::createFromFormat('Y-m-d', $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        };

        $gender = $request->input('gender');
        $nationality = strtoupper(trim((string) $request->input('nationality')));
        $club = trim((string) $request->input('club'));
        $verified = (string) $request->input('verified', 'any');

        $ageMin = $age($request->input('age_min'));
        $ageMax = $age($request->input('age_max'));

        // A reversed range asks for nobody. Swapping it is what the reader meant.
        if ($ageMin !== null && $ageMax !== null && $ageMin > $ageMax) {
            [$ageMin, $ageMax] = [$ageMax, $ageMin];
        }

        $from = $date($request->input('joined_from'));
        $to = $date($request->input('joined_to'));

        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return new self(
            missing: $list($request->input('missing'), self::MISSING),
            gender: in_array($gender, ['Male', 'Female'], true) ? $gender : null,
            ageMin: $ageMin,
            ageMax: $ageMax,
            nationality: in_array($nationality, self::nationalityCodes(), true) ? $nationality : null,
            club: $club === 'none'
                ? 'none'
                : (in_array($club, self::clubIds(), true) ? $club : null),
            verified: in_array($verified, self::VERIFIED, true) ? $verified : 'any',
            flags: $list($request->input('flags'), self::FLAGS),
            joinedFrom: $from,
            joinedTo: $to,
        );
    }

    /** True when nothing is being filtered on. */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /** How many filters are active — the number on the Filters button's badge. */
    public function count(): int
    {
        return count($this->missing)
            + count($this->flags)
            + ($this->gender !== null ? 1 : 0)
            + ($this->ageMin !== null || $this->ageMax !== null ? 1 : 0)
            + ($this->nationality !== null ? 1 : 0)
            + ($this->club !== null ? 1 : 0)
            + ($this->verified !== 'any' ? 1 : 0)
            + ($this->joinedFrom !== null || $this->joinedTo !== null ? 1 : 0);
    }

    /**
     * The query-string this filter set travels as — merged into every link and
     * every AJAX fetch so a filtered page can be bookmarked and shared.
     *
     * @return array<string, mixed>
     */
    public function toQuery(): array
    {
        return array_filter([
            'missing' => $this->missing ?: null,
            'gender' => $this->gender,
            'age_min' => $this->ageMin,
            'age_max' => $this->ageMax,
            'nationality' => $this->nationality,
            'club' => $this->club,
            'verified' => $this->verified !== 'any' ? $this->verified : null,
            'flags' => $this->flags ?: null,
            'joined_from' => $this->joinedFrom,
            'joined_to' => $this->joinedTo,
        ], fn ($v) => $v !== null && $v !== []);
    }

    /** The shape the Blade component seeds its Alpine state from. */
    public function toArray(): array
    {
        return [
            'missing' => $this->missing,
            'gender' => $this->gender ?? '',
            'age_min' => $this->ageMin,
            'age_max' => $this->ageMax,
            'nationality' => $this->nationality ?? '',
            'club' => $this->club ?? '',
            'verified' => $this->verified,
            'flags' => $this->flags,
            'joined_from' => $this->joinedFrom ?? '',
            'joined_to' => $this->joinedTo ?? '',
        ];
    }

    /**
     * Narrow a user query by everything that is set.
     *
     * Every clause is its own group, so this composes with a search term (which
     * is a chain of ORs) without the two bleeding into each other.
     */
    public function apply(Builder $query): Builder
    {
        foreach ($this->missing as $field) {
            $this->applyMissing($query, $field);
        }

        if ($this->gender !== null) {
            $query->where('gender', $this->gender);
        }

        // Age is derived from a birthdate, so it is asked of the birthdate:
        // "at least 18" is "born on or before this day 18 years ago".
        if ($this->ageMin !== null) {
            $query->whereNotNull('birthdate')
                ->whereDate('birthdate', '<=', Carbon::today()->subYears($this->ageMin));
        }

        if ($this->ageMax !== null) {
            $query->whereNotNull('birthdate')
                ->whereDate('birthdate', '>', Carbon::today()->subYears($this->ageMax + 1));
        }

        if ($this->nationality !== null) {
            $query->whereRaw("upper(trim(coalesce(nationality, ''))) = ?", [$this->nationality]);
        }

        if ($this->club === 'none') {
            $query->doesntHave('memberClubs');
        } elseif ($this->club !== null) {
            $query->whereHas('memberClubs', fn ($q) => $q->where('tenants.id', (int) $this->club));
        }

        if ($this->verified === 'yes') {
            $query->whereNotNull('email_verified_at');
        } elseif ($this->verified === 'no') {
            $query->whereNull('email_verified_at');
        }

        foreach ($this->flags as $flag) {
            match ($flag) {
                'unclaimed' => $query->where('is_unclaimed', true),
                'trainer' => $query->where('is_personal_trainer', true),
                'hidden' => $query->where('is_discoverable', false),
            };
        }

        if ($this->joinedFrom !== null) {
            $query->whereDate('created_at', '>=', $this->joinedFrom);
        }

        if ($this->joinedTo !== null) {
            $query->whereDate('created_at', '<=', $this->joinedTo);
        }

        return $query;
    }

    /**
     * "This field is not filled in" — for the seven shapes that means something
     * different in the database.
     */
    private function applyMissing(Builder $query, string $field): void
    {
        match ($field) {
            'phone' => $query->whereRaw(self::PHONE_SQL." = ''"),
            'club' => $query->doesntHave('memberClubs'),
            'email' => $this->blank($query, 'email'),
            'birthdate' => $query->whereNull('birthdate'),
            'gender' => $this->blank($query, 'gender'),
            'nationality' => $this->blank($query, 'nationality'),
            'photo' => $this->blank($query, 'profile_picture'),
        };
    }

    /** NULL and '' are the same emptiness to a reader; both count as missing. */
    private function blank(Builder $query, string $column): void
    {
        $query->where(fn ($q) => $q->whereNull($column)->orWhereRaw("trim($column) = ''"));
    }

    /**
     * The nationalities actually stored, as the picker wants them.
     *
     * Built from the members themselves rather than from the world's 200
     * countries: a filter offering a country nobody is from is a list of dead
     * ends, and this one is short enough to read.
     *
     * @return list<array{code: string, label: string, count: int}>
     */
    public static function nationalityOptions(): array
    {
        return User::query()
            ->whereNull('deleted_at')
            ->whereNotNull('nationality')
            ->whereRaw("trim(nationality) <> ''")
            ->selectRaw('upper(trim(nationality)) as code, count(*) as total')
            ->groupBy('code')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'code' => (string) $row->code,
                'label' => Countries::name((string) $row->code) ?? (string) $row->code,
                'count' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    /** @return list<array{id: string, name: string}> */
    public static function clubOptions(): array
    {
        return Tenant::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($club) => ['id' => (string) $club->id, 'name' => (string) $club->name])
            ->values()
            ->all();
    }

    /** @return list<string> */
    private static function nationalityCodes(): array
    {
        return array_column(self::nationalityOptions(), 'code');
    }

    /** @return list<string> */
    private static function clubIds(): array
    {
        return array_column(self::clubOptions(), 'id');
    }
}
