<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventOfficial;
use Illuminate\Support\Str;

/**
 * What each officiating job is CALLED, for one event.
 *
 * Two vocabularies meet on an officiating sheet and only one of them is ours:
 * the mat roles come from the SPORT (only Karate knows what a Kansa is, only
 * Taekwondo has a Sonsim), and the platform roles (jury, weigh-in, payments,
 * organiser) are the access this application grants. An event's sheet prints
 * both, in that order, because that is the order a competition reads them in.
 *
 * This lives here rather than in a controller because two surfaces now need
 * the same answer — the members' officiating sheet and the public event page —
 * and a second copy is how the two drift apart (CLAUDE.md → *Shared Stays
 * Shared*: add the seam, never duplicate the class).
 */
class OfficialRoles
{
    /**
     * Role key => human label, sport roles first.
     *
     * @return array<string, string>
     */
    public function labels(ClubEvent $event): array
    {
        return array_map(fn ($r) => $r['label'], $this->catalogue($event));
    }

    /**
     * The five parts an officials list reads in, the way international results
     * books print them (2026-10-18): the host's organising committee, the
     * supervisors (technical delegate, jury), the referees and judges, the
     * table, and the teams' own officials.
     */
    public const SECTIONS = ['organising', 'technical', 'referees', 'table', 'team'];

    /**
     * Role key => section, in the same order as labels().
     *
     * @return array<string, string>
     */
    public function sections(ClubEvent $event): array
    {
        return array_map(fn ($r) => $r['section'], $this->catalogue($event));
    }

    /**
     * Role key => what the job is, for a picker to print under its name.
     *
     * @return array<string, string|null>
     */
    public function hints(ClubEvent $event): array
    {
        return array_map(fn ($r) => $r['hint'], $this->catalogue($event));
    }

    /**
     * Which section one appointment reads under. `other` has no fixed word,
     * so its own title decides — "Team manager" and "Referee 1", which is what
     * a club names on its entry form, land with the team officials and the
     * referees rather than with the organisers.
     */
    public function sectionOf(ClubEvent $event, string $role, ?string $roleLabel = null): string
    {
        if ($role !== EventOfficial::ROLE_OTHER) {
            return $this->sections($event)[$role] ?? 'organising';
        }

        $t = mb_strtolower(trim((string) $roleLabel));
        $has = fn (array $words) => collect($words)->contains(fn ($w) => str_contains($t, $w));

        return match (true) {
            $t === '' => 'organising',
            $has(['team', 'coach', 'فريق', 'مدرب']) => 'team',
            $has(['delegate', 'supervisor', 'jury', 'مندوب', 'مشرف']) => 'technical',
            $has(['referee', 'judge', 'حكم']) => 'referees',
            $has(['timekeeper', 'scorekeeper', 'recorder', 'ميقات', 'مسجل']) => 'table',
            default => 'organising',
        };
    }

    /**
     * The JOB an appointment belongs to, as a stable key — what a job's own
     * badge and certificate designs are filed under (owner, 2026-10-20). A
     * listed job is its role key; a typed-in one is its wording without the
     * seat number ("Judge 1", "Judge 2" → one job), and wording that IS a
     * listed job's name ("Team manager") is that job.
     */
    public function jobKey(ClubEvent $event, EventOfficial $official): string
    {
        if ($official->role !== EventOfficial::ROLE_OTHER) {
            return (string) $official->role;
        }
        $label = $this->typedJob((string) $official->role_label);
        $listed = collect($this->labels($event))->search(fn ($l) => mb_strtolower(trim((string) $l)) === mb_strtolower($label));
        if ($listed !== false) {
            return (string) $listed;
        }
        $slug = Str::slug($label);

        return 'job-'.($slug !== '' ? Str::limit($slug, 40, '') : substr(sha1(mb_strtolower($label)), 0, 10));
    }

    /**
     * The jobs this event's officials hold, key => name, in the order the
     * officials list reads (organisers first, team officials last).
     *
     * @return array<string, string>
     */
    public function jobs(ClubEvent $event): array
    {
        $labels = $this->labels($event);
        $found = [];
        foreach (EventOfficial::where('event_id', $event->id)->orderBy('id')->get(['id', 'role', 'role_label', 'event_id']) as $o) {
            $key = $this->jobKey($event, $o);
            $found[$key] ??= $labels[$key] ?? ($this->typedJob((string) $o->role_label) ?: Str::headline($key));
        }
        $order = array_flip(array_keys($labels));

        return collect($found)->sortBy(fn ($name, $key) => $order[$key] ?? PHP_INT_MAX)->all();
    }

    /** "Judge 2" → "Judge": a typed-in job without its seat number. */
    private function typedJob(string $label): string
    {
        return trim((string) preg_replace('/\s*\d+$/u', '', trim($label)));
    }

    public static function sectionLabel(string $section): string
    {
        return __('events::events.official_section_'.$section);
    }

    /**
     * Every role this event offers, ordered by section and then the order each
     * vocabulary declares. Sport roles bring their own section (a sport that
     * names none reads as referees); the platform's carry theirs here.
     *
     * @return array<string, array{label: string, hint: ?string, section: string}>
     */
    private function catalogue(ClubEvent $event): array
    {
        $role = fn (string $key, string $section) => [$key => [
            'label' => __('personal.personal_event_officials_role_'.$key),
            'hint' => __('personal.personal_event_officials_role_'.$key.'_hint'),
            'section' => $section,
        ]];
        $credit = fn (string $key) => [$key => [
            'label' => __('events::events.official_role_'.$key),
            'hint' => __('events::events.official_role_'.$key.'_hint'),
            'section' => EventOfficial::creditRoles()[$key],
        ]];

        $sportRoles = [];
        $sport = app(\App\Sports\Combat\SportRegistry::class)->get($event->sport);

        if ($sport !== null) {
            foreach ($sport->officialRoles() as $r) {
                if (! empty($r['key'])) {
                    $section = in_array($r['section'] ?? null, self::SECTIONS, true) ? $r['section'] : 'referees';
                    $sportRoles[$r['key']] = ['label' => $r['label'] ?? Str::title($r['key']), 'hint' => $r['hint'] ?? null, 'section' => $section];
                }
            }
        }

        $platform = $role(EventOfficial::ROLE_ORGANISER, 'organising')
            + $credit(EventOfficial::ROLE_COMPETITION_MANAGER)
            + $role(EventOfficial::ROLE_WEIGH_IN, 'organising')
            + $role(EventOfficial::ROLE_PAYMENTS, 'organising')
            + $credit(EventOfficial::ROLE_ACCREDITATION)
            + $credit(EventOfficial::ROLE_MEDICAL)
            + $credit(EventOfficial::ROLE_TECHNICAL_DELEGATE)
            + $credit(EventOfficial::ROLE_CHIEF_REFEREE)
            + $role(EventOfficial::ROLE_JURY, 'technical')
            + $credit(EventOfficial::ROLE_TEAM_MANAGER)
            + $credit(EventOfficial::ROLE_COACH)
            + $credit(EventOfficial::ROLE_TEAM_DOCTOR);

        // The host's own designations (OfficialDesignation), each under the
        // section it was given. Their keys (`cd_<id>`) cannot collide with either.
        $custom = [];
        foreach (\App\Events\Models\OfficialDesignation::forEvent($event) as $d) {
            $custom[$d->key()] = [
                'label' => (string) $d->label,
                'hint' => __('events::events.official_designation_hint'),
                'section' => in_array($d->section, self::SECTIONS, true) ? $d->section : 'organising',
            ];
        }

        // A sport key never shadows a platform one: the platform's are what
        // EventAccess reads, so the platform's meaning wins a collision.
        $all = $platform + $sportRoles + $custom;

        $out = [];
        foreach (self::SECTIONS as $section) {
            foreach ($all as $key => $r) {
                if ($r['section'] === $section) {
                    $out[$key] = $r;
                }
            }
        }

        return $out;
    }
}
