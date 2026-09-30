<?php

namespace App\Events\Support;

use App\Members\Models\User;
use Illuminate\Support\Collection;

/**
 * "Is this somebody we already have?" — asked at every door that would
 * otherwise make a new person for a competition.
 *
 * The public entry form, the coach's entry desk and the organiser's walk-in
 * all used to create a fresh account or person record on every submit. The
 * same athlete entering twice, on another phone, or being entered by a coach
 * after entering themselves became two, three, four profiles — and two,
 * three, four places in the draw.
 *
 * The rule is deliberately narrow, because a wrong "yes" gives one person's
 * profile to somebody else:
 *
 *   SAME PHONE and a name that is recognisably the same person.
 *
 * A phone alone is not enough: a telephone belongs to a household, and a
 * parent entering two children on one number is the ordinary case (different
 * first names → different people). A name alone is not enough either: two
 * Mohammed Alis are two people. Anything weaker than both is left to the
 * duplicate scan and the organiser's pre-draw check, which a human decides.
 *
 * Shared by every door on purpose (CLAUDE.md → Shared Stays Shared): one
 * definition of "the same person", so two doors can never disagree about it.
 * Behind `events.entry_dedupe` — see the config for the switch.
 */
class KnownPerson
{
    public static function enabled(): bool
    {
        return (bool) config('events.entry_dedupe', false);
    }

    /**
     * Live accounts and person records on this phone number, oldest first.
     *
     * @return Collection<int, User>
     */
    public function byPhone(?string $code, ?string $number): Collection
    {
        return $this->byTyped(trim(($code ?: '+973').' '.$number));
    }

    /**
     * The same, for a number typed as one string ("33165444", "+973 3316 5444",
     * "0097333165444") — the coach's desk takes it that way. Uses the
     * platform's one phone rule (User::withPhone), so a local number finds the
     * profile stored with its country code and the other way round.
     *
     * @return Collection<int, User>
     */
    public function byTyped(?string $typed): Collection
    {
        if (strlen((string) User::normalisePhoneInput($typed)) < 6) {
            return collect();
        }

        return User::withPhone($typed)->orderBy('id')->limit(20)->get();
    }

    /** @return Collection<int, User> */
    public function matchesTyped(?string $typed, string $name): Collection
    {
        return $this->byTyped($typed)
            ->filter(fn (User $u) => $this->samePerson($name, (string) ($u->full_name ?: $u->name)))
            ->values();
    }

    /**
     * A typed number in the platform's `{code, number}` shape, so a person the
     * desk records can be recognised by the next door. A number with its
     * country code keeps it inside `number` (the key is the same digits).
     */
    public static function mobileFromTyped(?string $typed): ?array
    {
        $typed = trim((string) $typed);
        if (strlen(preg_replace('/\D/', '', $typed)) < 6) {
            return null;
        }
        if (str_starts_with($typed, '+') || str_starts_with(preg_replace('/\s+/', '', $typed), '00')) {
            return ['code' => '', 'number' => '+'.ltrim(preg_replace('/\D/', '', $typed), '0')];
        }

        return ['code' => '+973', 'number' => preg_replace('/\D/', '', $typed)];
    }

    /**
     * The people on this phone who are recognisably `$name`.
     *
     * @return Collection<int, User>
     */
    public function matches(?string $code, ?string $number, string $name): Collection
    {
        return $this->byPhone($code, $number)
            ->filter(fn (User $u) => $this->samePerson($name, (string) ($u->full_name ?: $u->name)))
            ->values();
    }

    /**
     * Two spellings of one person's name?
     *
     * "Ahmed Yusuf Ali" and "Ahmad Yousif" — yes. "Yousif Hasan" and
     * "Noor Hasan" (a brother and sister on their mother's phone) — no. The
     * FIRST name must agree, and when both have more than one name, one more
     * must agree too. Names agree when they are equal, when they share their
     * consonants (Ahmed/Ahmad, Yusuf/Yousif, Hasan/Hassan, Mohammed/Muhammad),
     * or when they are one typo apart — and their first vowel always agrees, so
     * Hasan and Husain stay two people.
     */
    public function samePerson(string $a, string $b): bool
    {
        $ta = $this->tokens($a);
        $tb = $this->tokens($b);

        if ($ta === [] || $tb === []) {
            return false;
        }
        if (implode(' ', $ta) === implode(' ', $tb)) {
            return true;
        }
        if (! $this->close($ta[0], $tb[0])) {
            return false;
        }
        if (count($ta) === 1 || count($tb) === 1) {
            // "Ahmed" against "Ahmed Yusuf": the one name they both have agrees.
            return true;
        }

        $rest = array_slice($tb, 1);
        foreach (array_slice($ta, 1) as $x) {
            foreach ($rest as $i => $y) {
                if ($this->close($x, $y)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** A name reduced to what decides it: "Jawad  Abdulhadi." → "jawad abdulhadi". */
    public function normalised(string $name): string
    {
        return implode(' ', $this->tokens($name));
    }

    /** @return string[] */
    private function tokens(string $name): array
    {
        $name = mb_strtolower($name);
        // Arabic letters that are written several ways for one sound.
        $name = strtr($name, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي', 'ـ' => '']);
        $name = preg_replace('/[\x{064B}-\x{0652}]/u', '', $name);   // tashkeel
        $name = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $name);
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        // "Al" / "Bin" / "Abu" joined to what follows, so "Al Majed" == "Almajed".
        $out = [];
        for ($i = 0, $n = count($parts); $i < $n; $i++) {
            $p = $parts[$i];
            if ($p === '') {
                continue;
            }
            if (in_array($p, ['al', 'el', 'bin', 'ibn', 'bint', 'abu', 'abo', 'abd'], true) && isset($parts[$i + 1])) {
                $parts[$i + 1] = $p.$parts[$i + 1];

                continue;
            }
            $out[] = $p;
        }

        return $out;
    }

    /**
     * Two names that are the same name.
     *
     * Latin spellings of one Arabic name drift in their vowels (Ahmed/Ahmad,
     * Yusuf/Yousif, Mohammed/Muhammad) but keep their consonants and their
     * FIRST vowel. That first vowel is what tells Hasan from Husain — brothers
     * who share every consonant and, very often, a phone. Arabic script is
     * already one spelling, so it must match exactly.
     */
    private function close(string $x, string $y): bool
    {
        if ($x === $y) {
            return true;
        }
        if (! preg_match('/^[a-z]+$/', $x) || ! preg_match('/^[a-z]+$/', $y)) {
            return false;
        }
        if ($x[0] !== $y[0] || $this->firstVowel($x) !== $this->firstVowel($y)) {
            return false;
        }
        $d = levenshtein($x, $y);

        return ($this->skeleton($x) === $this->skeleton($y) && $d <= 2)
            || (mb_strlen($x) >= 5 && $d <= 1);
    }

    /** o and u, e and i are the same vowel in transliteration. */
    private function firstVowel(string $w): string
    {
        return preg_match('/[aeiou]/', $w, $m) ? strtr($m[0], ['o' => 'u', 'e' => 'i']) : '';
    }

    /** The consonants, doubled letters collapsed: ahmed → hmd, hassan → hsn. */
    private function skeleton(string $w): string
    {
        $w = preg_replace('/[aeiouyw]/', '', $w);

        return preg_replace('/(.)\1+/u', '$1', $w);
    }
}
