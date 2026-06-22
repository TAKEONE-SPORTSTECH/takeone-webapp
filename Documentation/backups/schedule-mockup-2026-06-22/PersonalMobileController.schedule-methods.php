    public function schedule(): View
    {
        // DUMMY training schedule (self + family) for design preview. Build the
        // current week's day strip and tag each day with its sessions/status.
        $data    = $this->demoSchedule();
        $now     = \Carbon\Carbon::now();
        $weekStart = $now->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);

        $weekDays = collect(range(0, 6))->map(function ($i) use ($weekStart, $now) {
            $d = $weekStart->copy()->addDays($i);
            return [
                'key'     => strtolower($d->format('l')),
                'short'   => $d->format('D'),
                'd'       => $d->format('j'),
                'isToday' => $d->isSameDay($now),
                'isPast'  => $d->lt($now->copy()->startOfDay()),
            ];
        });

        // Stamp each session with a status relative to today.
        $todayKey = strtolower($now->format('l'));
        $order    = ['sunday'=>0,'monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6];
        $sessions = collect($data['sessions'])->map(function ($s) use ($order, $todayKey) {
            $cmp = $order[$s['day']] <=> $order[$todayKey];
            $s['status'] = $cmp < 0 ? 'done' : ($cmp === 0 ? 'today' : 'upcoming');
            return $s;
        })->values();

        $members  = $data['members'];
        $todayShort = $now->format('D');

        return view('personal.schedule', compact('weekDays', 'sessions', 'members', 'todayKey', 'todayShort'));
    }

    /** DUMMY training-session detail with the full workout breakdown. */
    public function scheduleShow(int $session): View
    {
        $data = $this->demoSchedule();
        $s = $data['sessions'][$session] ?? $data['sessions'][array_key_first($data['sessions'])];
        $member = $data['members'][$s['who']] ?? $data['members']['me'];

        return view('personal.schedule-show', ['s' => $s, 'member' => $member]);
    }

    /** Shared curated dummy training schedule — family members + weekly sessions. */
    public function demoSchedule(): array
    {
        $members = [
            'me'   => ['key' => 'me',   'name' => 'You',   'relation' => 'Me',       'initials' => 'YO', 'color' => '#7c3aed'],
            'sara' => ['key' => 'sara', 'name' => 'Sara',  'relation' => 'Daughter', 'initials' => 'SA', 'color' => '#ec4899'],
            'omar' => ['key' => 'omar', 'name' => 'Omar',  'relation' => 'Son',      'initials' => 'OM', 'color' => '#0ea5e9'],
        ];

        $sessions = [
            1 => [
                'id' => 1, 'who' => 'me', 'day' => 'monday', 'start' => '6:30 AM', 'end' => '7:30 AM',
                'duration' => '60 min', 'title' => 'Strength & Power', 'discipline' => 'Weight Training',
                'icon' => 'bi-trophy', 'color' => '#7c3aed', 'coach' => 'Coach Adam', 'location' => 'Gym Floor 1',
                'intensity' => 'High', 'focus' => ['Legs', 'Glutes', 'Core'],
                'notes' => 'Leave 2 reps in the tank on the big lifts. Add load only if last week’s sets felt clean.',
                'workout' => [
                    'warmup' => ['5 min row, easy', 'Hip openers & band walks', '2 light warm-up sets per lift'],
                    'main' => [
                        ['name' => 'Back Squat', 'sets' => 5, 'reps' => '5', 'note' => '@ 75% 1RM'],
                        ['name' => 'Romanian Deadlift', 'sets' => 4, 'reps' => '8', 'note' => 'Slow eccentric'],
                        ['name' => 'Walking Lunges', 'sets' => 3, 'reps' => '12 / leg', 'note' => 'Dumbbells'],
                        ['name' => 'Hanging Leg Raise', 'sets' => 3, 'reps' => '12', 'note' => 'Controlled'],
                    ],
                    'cooldown' => ['5 min walk', 'Couch stretch 1 min / side', 'Foam roll quads'],
                ],
            ],
            2 => [
                'id' => 2, 'who' => 'me', 'day' => 'wednesday', 'start' => '6:00 PM', 'end' => '7:00 PM',
                'duration' => '60 min', 'title' => 'Conditioning', 'discipline' => 'HIIT',
                'icon' => 'bi-heart-pulse-fill', 'color' => '#ef4444', 'coach' => 'Coach Lina', 'location' => 'Studio B',
                'intensity' => 'High', 'focus' => ['Cardio', 'Full body'],
                'notes' => 'Keep rest strict. Scale the burpees to step-backs if your form breaks down.',
                'workout' => [
                    'warmup' => ['400 m jog', 'Dynamic mobility flow', 'Build-up sprints x3'],
                    'main' => [
                        ['name' => 'Assault Bike Intervals', 'sets' => 6, 'reps' => '30s on / 30s off', 'note' => 'Max effort'],
                        ['name' => 'Burpees', 'sets' => 4, 'reps' => '12', 'note' => 'Chest to floor'],
                        ['name' => 'Kettlebell Swings', 'sets' => 4, 'reps' => '20', 'note' => '24 kg'],
                        ['name' => 'Plank Hold', 'sets' => 3, 'reps' => '45s', 'note' => 'Brace hard'],
                    ],
                    'cooldown' => ['Box breathing 2 min', 'Full-body stretch 5 min'],
                ],
            ],
            3 => [
                'id' => 3, 'who' => 'me', 'day' => 'friday', 'start' => '7:00 AM', 'end' => '8:15 AM',
                'duration' => '75 min', 'title' => 'Speed & Agility', 'discipline' => 'Track',
                'icon' => 'bi-lightning-charge-fill', 'color' => '#f59e0b', 'coach' => 'Coach Adam', 'location' => 'Main Track',
                'intensity' => 'Moderate', 'focus' => ['Speed', 'Footwork'],
                'notes' => 'Quality over quantity — full recovery between sprints. Stop if turnover slows.',
                'workout' => [
                    'warmup' => ['Jog 800 m', 'A-skips, B-skips, high knees', 'Strides x4'],
                    'main' => [
                        ['name' => 'Flying 30 m Sprints', 'sets' => 6, 'reps' => '30 m', 'note' => 'Full recovery'],
                        ['name' => 'Ladder Drills', 'sets' => 4, 'reps' => '2 patterns', 'note' => 'Fast feet'],
                        ['name' => 'Cone Cuts', 'sets' => 4, 'reps' => '5-10-5', 'note' => 'Sharp turns'],
                    ],
                    'cooldown' => ['Easy jog 5 min', 'Calf & hip flexor stretch'],
                ],
            ],
            4 => [
                'id' => 4, 'who' => 'me', 'day' => 'sunday', 'start' => '8:00 AM', 'end' => '9:30 AM',
                'duration' => '90 min', 'title' => 'Long Run', 'discipline' => 'Endurance',
                'icon' => 'bi-activity', 'color' => '#10b981', 'coach' => 'Self-guided', 'location' => 'Outdoor Loop',
                'intensity' => 'Low', 'focus' => ['Aerobic base'],
                'notes' => 'Conversational pace the whole way. Hydrate and fuel after.',
                'workout' => [
                    'warmup' => ['Brisk walk 5 min', 'Leg swings'],
                    'main' => [
                        ['name' => 'Steady Run', 'sets' => 1, 'reps' => '10 km', 'note' => 'Zone 2 · easy pace'],
                    ],
                    'cooldown' => ['Walk 5 min', 'Full lower-body stretch 8 min'],
                ],
            ],
            5 => [
                'id' => 5, 'who' => 'sara', 'day' => 'tuesday', 'start' => '5:00 PM', 'end' => '6:00 PM',
                'duration' => '60 min', 'title' => 'Junior Swimming', 'discipline' => 'Swimming',
                'icon' => 'bi-water', 'color' => '#ec4899', 'coach' => 'Coach Maya', 'location' => 'Pool',
                'intensity' => 'Moderate', 'focus' => ['Technique', 'Freestyle'],
                'notes' => 'Focus on breathing rhythm. Kickboard drills between sets.',
                'workout' => [
                    'warmup' => ['200 m easy freestyle', 'Kick drill 4 x 25 m'],
                    'main' => [
                        ['name' => 'Freestyle Intervals', 'sets' => 6, 'reps' => '50 m', 'note' => '20s rest'],
                        ['name' => 'Pull-buoy Drill', 'sets' => 4, 'reps' => '25 m', 'note' => 'Long strokes'],
                    ],
                    'cooldown' => ['100 m easy backstroke'],
                ],
            ],
            6 => [
                'id' => 6, 'who' => 'sara', 'day' => 'thursday', 'start' => '5:00 PM', 'end' => '6:00 PM',
                'duration' => '60 min', 'title' => 'Gymnastics', 'discipline' => 'Gymnastics',
                'icon' => 'bi-stars', 'color' => '#ec4899', 'coach' => 'Coach Rana', 'location' => 'Hall C',
                'intensity' => 'Moderate', 'focus' => ['Flexibility', 'Balance'],
                'notes' => 'Spotter required for the beam routine.',
                'workout' => [
                    'warmup' => ['Joint rotations', 'Bridges & splits hold'],
                    'main' => [
                        ['name' => 'Beam Balance', 'sets' => 4, 'reps' => '1 routine', 'note' => 'With spotter'],
                        ['name' => 'Floor Tumbling', 'sets' => 3, 'reps' => '3 passes', 'note' => 'Cartwheel → roundoff'],
                    ],
                    'cooldown' => ['Cool-down stretch 5 min'],
                ],
            ],
            7 => [
                'id' => 7, 'who' => 'omar', 'day' => 'monday', 'start' => '4:30 PM', 'end' => '5:45 PM',
                'duration' => '75 min', 'title' => 'Football Training', 'discipline' => 'Football',
                'icon' => 'bi-dribbble', 'color' => '#0ea5e9', 'coach' => 'Coach Sami', 'location' => 'Pitch 2',
                'intensity' => 'High', 'focus' => ['Passing', 'Stamina'],
                'notes' => 'Bring shin pads and water. Small-sided games at the end.',
                'workout' => [
                    'warmup' => ['Jog & dynamic stretches', 'Passing in pairs 5 min'],
                    'main' => [
                        ['name' => 'Dribbling Course', 'sets' => 4, 'reps' => '1 lap', 'note' => 'Both feet'],
                        ['name' => 'Shooting Drill', 'sets' => 3, 'reps' => '8 shots', 'note' => 'Top corners'],
                        ['name' => 'Small-Sided Game', 'sets' => 1, 'reps' => '15 min', 'note' => '4 v 4'],
                    ],
                    'cooldown' => ['Light jog', 'Hamstring & calf stretch'],
                ],
            ],
            8 => [
                'id' => 8, 'who' => 'omar', 'day' => 'saturday', 'start' => '10:00 AM', 'end' => '11:00 AM',
                'duration' => '60 min', 'title' => 'Karate', 'discipline' => 'Martial Arts',
                'icon' => 'bi-trophy', 'color' => '#0ea5e9', 'coach' => 'Sensei Tariq', 'location' => 'Dojo',
                'intensity' => 'Moderate', 'focus' => ['Discipline', 'Kata'],
                'notes' => 'Belt grading prep — revise the full kata sequence.',
                'workout' => [
                    'warmup' => ['Bowing & stances', 'Joint mobility'],
                    'main' => [
                        ['name' => 'Kata Practice', 'sets' => 5, 'reps' => '1 sequence', 'note' => 'Sharp & slow'],
                        ['name' => 'Pad Strikes', 'sets' => 4, 'reps' => '10 combos', 'note' => 'With partner'],
                    ],
                    'cooldown' => ['Seated stretches', 'Breathing & bow out'],
                ],
            ],
        ];

        return compact('members', 'sessions');
    }

    public function scheduleLegacy(): View
    {
        $subscriptions = ClubMemberSubscription::where('user_id', Auth::id())
            ->whereIn('status', ['active', 'pending'])
            ->with([
                'package:id,name,type,price,duration_months,session_count,description,translations',
                'package.packageActivities.activity:id,name',
                'tenant:id,club_name,logo,slug,country,currency,translations',
            ])
            ->get();

        $now = \Carbon\Carbon::now();
        $weekdays = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];

        // Build a session-by-session projection for each enrolment so the view can
        // show which classes are done, which are coming, and how many are left.
        $enrolments = $subscriptions->map(function ($sub) use ($now, $weekdays) {
            // Weekly class slots from the package's activities' schedules.
            $slots = collect();
            foreach ($sub->package?->packageActivities ?? [] as $pa) {
                $sched = is_array($pa->schedule) ? $pa->schedule : (json_decode($pa->schedule ?? '[]', true) ?: []);
                foreach ($sched as $s) {
                    $day = strtolower($s['day'] ?? '');
                    if (! isset($weekdays[$day])) continue;
                    $slots->push([
                        'day'      => $day,
                        'dow'      => $weekdays[$day],
                        'start'    => $s['start_time'] ?? null,
                        'end'      => $s['end_time'] ?? null,
                        'activity' => $pa->activity->name ?? null,
                        'facility' => $s['facility_name'] ?? null,
                    ]);
                }
            }

            $start = $sub->start_date ? \Carbon\Carbon::parse($sub->start_date)->startOfDay() : null;
            $end   = $sub->end_date ? \Carbon\Carbon::parse($sub->end_date)->endOfDay() : null;
            $total = (int) ($sub->package?->session_count ?? 0); // 0 = unlimited

            // Project concrete session occurrences from the recurring weekly slots.
            $sessions = collect();
            if ($start && $slots->isNotEmpty()) {
                $hardEnd = $end ?: $start->copy()->addMonths(max(1, (int) ($sub->package?->duration_months ?? 1)));
                $cursor = $start->copy();
                $guard = 0;
                while ($cursor->lte($hardEnd) && $guard < 730) {
                    $guard++;
                    foreach ($slots as $slot) {
                        if ($slot['dow'] === $cursor->dayOfWeek) {
                            [$h, $m] = array_pad(explode(':', (string) ($slot['start'] ?? '0:0')), 2, '0');
                            $dt = $cursor->copy()->setTime((int) $h, (int) $m);
                            $sessions->push([
                                'datetime' => $dt,
                                'start'    => $slot['start'],
                                'end'      => $slot['end'],
                                'activity' => $slot['activity'],
                                'facility' => $slot['facility'],
                                'past'     => $dt->lt($now),
                            ]);
                        }
                    }
                    if ($total > 0 && $sessions->count() >= $total) break;
                    $cursor->addDay();
                }
            }

            $sessions = $sessions->sortBy(fn ($s) => $s['datetime']->timestamp)->values();
            if ($total > 0) {
                $sessions = $sessions->take($total)->values();
            }

            $done      = $sessions->where('past', true)->count();
            $remaining = $total > 0 ? max(0, $total - $done) : null;   // null = unlimited
            $next      = $sessions->firstWhere('past', false);

            return [
                'sub'          => $sub,
                'slots'        => $slots->unique(fn ($s) => $s['day'] . $s['start'])->sortBy('dow')->values(),
                'sessions'     => $sessions,
                'total'        => $total,
                'done'         => $done,
                'remaining'    => $remaining,
                'next'         => $next,
                'expired'      => $end ? $end->lt($now) : false,
                'daysUntilEnd' => $end ? (int) ceil($now->copy()->startOfDay()->diffInDays($end, false)) : null,
            ];
        });

        // Split: still-usable enrolments vs expired ones (end date passed or all
        // sessions used up) which move to a separate "renew" section.
        $isExpired = function ($e) {
            $exhausted = $e['remaining'] !== null && $e['remaining'] <= 0;
            return $e['expired'] || $exhausted;
        };
        $active  = $enrolments->reject($isExpired)->values();
        $expired = $enrolments->filter($isExpired)->values();

        // Does the member already belong to a club (any membership/affiliation),
        // even if they have no current class schedule? Drives the empty-state CTA:
        // existing members are sent to their packages, newcomers to explore.
        $belongsToClub = $subscriptions->isNotEmpty()
            || Auth::user()->clubAffiliations()->exists()
            || Auth::user()->memberClubs()->exists();

        // The club to enrol in when the member has no active packages — send
        // them straight to its packages tab to pick one.
        $enrolClub = Auth::user()->memberClubs()->first();

        return view('personal.schedule', compact('active', 'expired', 'belongsToClub', 'enrolClub'));
    }
