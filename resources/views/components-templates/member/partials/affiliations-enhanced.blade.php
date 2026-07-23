@php
    // Helper function to calculate age at a specific date with detailed format.
    // Guarded: both member & family affiliations partials declare this.
    if (! function_exists('calculateAgeAtDate')) {
    function calculateAgeAtDate($birthdate, $date) {
        if (!$birthdate || !$date) return null;
        $birth = \Carbon\Carbon::parse($birthdate);
        $targetDate = \Carbon\Carbon::parse($date);

        $diff = $birth->diff($targetDate);
        $parts = [];

        if ($diff->y > 0) $parts[] = $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
        if ($diff->m > 0) $parts[] = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
        if ($diff->d > 0) $parts[] = $diff->d . ' day' . ($diff->d > 1 ? 's' : '');

        return implode(' ', $parts) ?: __('member.partials_affiliations_enhanced_same_day');
    }
    }
@endphp

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <!-- Header with Filter -->
        <div class="flex justify-between items-center mb-4">
            <div>
                <h5 class="font-bold mb-1"><i class="bi bi-diagram-3 me-2"></i>{{ __('member.partials_affiliations_enhanced_header_title') }}</h5>
                <p class="text-muted-foreground text-sm mb-0">{{ __('member.partials_affiliations_enhanced_header_subtitle') }}</p>
            </div>
            <div class="flex gap-2">
                <select class="form-select form-select-sm" id="skillFilter" style="width: 200px;">
                    <option value="all">{{ __('member.partials_affiliations_enhanced_filter_all_skills') }}</option>
                    @foreach($allSkills ?? [] as $skill)
                        <option value="{{ $skill }}">{{ $skill }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline-secondary" id="resetFilters">
                    <i class="bi bi-arrow-clockwise"></i> {{ __('member.partials_affiliations_enhanced_reset') }}
                </button>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAffiliationModal">
                    <i class="bi bi-plus-circle me-1"></i> {{ __('member.partials_affiliations_enhanced_add_affiliation') }}
                </button>
            </div>
        </div>

        @if($clubAffiliations->count() > 0)
            <!-- Summary Stats -->
            <div class="grid grid-cols-12 gap-3 mb-4">
                <div class="col-span-12 md:col-span-3">
                    <div class="card shadow-sm h-full" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                        <div class="card-body text-center text-white p-3">
                            <i class="bi bi-building text-5xl mb-2"></i>
                            <h3 class="font-bold mb-1">{{ $totalAffiliations }}</h3>
                            <small class="opacity-75">{{ __('member.partials_affiliations_enhanced_total_clubs') }}</small>
                        </div>
                    </div>
                </div>
                <div class="col-span-12 md:col-span-3">
                    <div class="card shadow-sm h-full" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">
                        <div class="card-body text-center text-white p-3">
                            <i class="bi bi-star-fill text-5xl mb-2"></i>
                            <h3 class="font-bold mb-1">{{ $distinctSkills }}</h3>
                            <small class="opacity-75">{{ __('member.partials_affiliations_enhanced_unique_skills') }}</small>
                        </div>
                    </div>
                </div>
                <div class="col-span-12 md:col-span-3">
                    <div class="card shadow-sm h-full" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                        <div class="card-body text-center text-white p-3">
                            <i class="bi bi-calendar-check text-5xl mb-2"></i>
                            <h3 class="font-bold mb-1">{{ floor($totalMembershipDuration / 12) }}y {{ $totalMembershipDuration % 12 }}m</h3>
                            <small class="opacity-75">{{ __('member.partials_affiliations_enhanced_total_training') }}</small>
                        </div>
                    </div>
                </div>
                <div class="col-span-12 md:col-span-3">
                    <div class="card shadow-sm h-full" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); border: none;">
                        <div class="card-body text-center text-white p-3">
                            <i class="bi bi-people-fill text-5xl mb-2"></i>
                            <h3 class="font-bold mb-1">{{ $totalInstructors ?? 0 }}</h3>
                            <small class="opacity-75">{{ __('member.partials_affiliations_enhanced_instructors') }}</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                            <h6 class="card-title mb-0 text-white">
                                <i class="bi bi-clock-history me-2"></i>{{ __('member.partials_affiliations_enhanced_membership_timeline') }}
                            </h6>
                        </div>
                        <div class="card-body p-4" style="max-height: 800px; overflow-y: auto;">
                            <div class="timeline-enhanced" id="affiliationsTimeline">
                                @foreach($clubAffiliations as $index => $affiliation)
                                    @php
                                        $ageAtStart = calculateAgeAtDate($user->birthdate, $affiliation->start_date);
                                        $ageAtEnd = $affiliation->end_date ? calculateAgeAtDate($user->birthdate, $affiliation->end_date) : null;
                                        $isOngoing = !$affiliation->end_date;

                                        // Get all skills for this affiliation
                                        $affiliationSkills = $affiliation->skillAcquisitions ?? collect();
                                        $skillNames = $affiliationSkills->pluck('skill_name')->unique()->implode(',');
                                    @endphp

                                    <div class="timeline-item-enhanced mb-4" id="affiliation-{{ $affiliation->id }}" data-affiliation-id="{{ $affiliation->id }}" data-skills="{{ $skillNames }}">
                                        <!-- Timeline Marker -->
                                        <div class="timeline-marker-enhanced {{ $isOngoing ? 'pulse' : '' }}"></div>

                                        <!-- Affiliation Card -->
                                        <div class="affiliation-card-enhanced card border-0 shadow-sm">
                                            <!-- Card Header with Gradient -->
                                            <div class="card-header border-0 p-3" style="background: linear-gradient(135deg, {{ $index % 4 == 0 ? '#667eea 0%, #764ba2' : ($index % 4 == 1 ? '#f093fb 0%, #f5576c' : ($index % 4 == 2 ? '#4facfe 0%, #00f2fe' : '#fa709a 0%, #fee140')) }} 100%);">
                                                <div class="flex items-center">
                                                    @php
                                                        $logoUrl = null;
                                                        if ($affiliation->tenant?->logo) {
                                                            $logoUrl = asset('storage/' . $affiliation->tenant->logo);
                                                        } elseif ($affiliation->logo) {
                                                            $logoUrl = filter_var($affiliation->logo, FILTER_VALIDATE_URL)
                                                                ? $affiliation->logo
                                                                : asset('storage/' . $affiliation->logo);
                                                        }
                                                    @endphp
                                                    @if($logoUrl)
                                                        <img src="{{ $logoUrl }}" alt="{{ $affiliation->club_name }}" class="rounded-full me-3" style="width: 50px; height: 50px; object-fit: cover; border: 3px solid white;">
                                                    @else
                                                        <div class="rounded-full bg-white flex items-center justify-center me-3" style="width: 50px; height: 50px;">
                                                            <i class="bi bi-building" style="font-size: 1.5rem; color: #667eea;"></i>
                                                        </div>
                                                    @endif
                                                    <div class="grow text-white">
                                                        <h5 class="mb-1 font-bold" id="affiliation-name-{{ $affiliation->id }}">{{ $affiliation->club_name }}</h5>
                                                        <div class="flex gap-3 flex-wrap">
                                                            @if($affiliation->start_date)
                                                                <small class="opacity-90" id="affiliation-dates-{{ $affiliation->id }}">
                                                                    <i class="bi bi-calendar-event me-1"></i>{{ $affiliation->start_date->format('M Y') }} - {{ $isOngoing ? __('member.partials_affiliations_enhanced_present') : ($affiliation->end_date ? $affiliation->end_date->format('M Y') : __('member.partials_affiliations_enhanced_na')) }}
                                                                </small>
                                                            @endif
                                                            @if($affiliation->formatted_duration)
                                                                <small class="opacity-90" id="affiliation-duration-{{ $affiliation->id }}">
                                                                    <i class="bi bi-hourglass-split me-1"></i>{{ $affiliation->formatted_duration }}
                                                                </small>
                                                            @endif
                                                            @if($ageAtStart)
                                                                <small class="opacity-90">
                                                                    <i class="bi bi-person me-1"></i>{{ __('member.partials_affiliations_enhanced_age') }} {{ $ageAtStart }}{{ $ageAtEnd && $ageAtEnd != $ageAtStart ? ' ' . __('member.partials_affiliations_enhanced_age_to') . " $ageAtEnd" : '' }}
                                                                </small>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @if($isOngoing)
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>{{ __('member.partials_affiliations_enhanced_active') }}
                                                        </span>
                                                    @endif
                                                    <div class="flex gap-1 ms-2">
                                                        <button type="button"
                                                                class="btn btn-sm btn-edit-affiliation"
                                                                style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 2px 8px;"
                                                                data-affiliation-id="{{ $affiliation->id }}"
                                                                data-club-name="{{ $affiliation->club_name }}"
                                                                data-start-date="{{ $affiliation->start_date->format('Y-m-d') }}"
                                                                data-end-date="{{ $affiliation->end_date ? $affiliation->end_date->format('Y-m-d') : '' }}"
                                                                data-location="{{ $affiliation->location }}"
                                                                data-description="{{ $affiliation->description }}"
                                                                data-coaches="{{ is_array($affiliation->coaches) ? implode(', ', $affiliation->coaches) : '' }}"
                                                                data-member-id="{{ $user->id }}"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#editAffiliationModal"
                                                                title="{{ __('shared.edit') }}">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button"
                                                                class="btn btn-sm btn-delete-affiliation"
                                                                style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 2px 8px;"
                                                                data-affiliation-id="{{ $affiliation->id }}"
                                                                data-club-name="{{ $affiliation->club_name }}"
                                                                data-member-id="{{ $user->id }}"
                                                                title="{{ __('shared.delete') }}">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Card Body -->
                                            <div class="card-body p-3">
                                                @if($affiliation->location)
                                                    <div class="mb-3">
                                                        <i class="bi bi-geo-alt text-primary me-2"></i>
                                                        <span class="text-muted-foreground">{{ $affiliation->location }}</span>
                                                    </div>
                                                @endif

                                                <!-- Skills Acquired as Badges -->
                                                <div class="mb-3">
                                                    <div class="flex justify-between items-center mb-2">
                                                        <h6 class="font-bold mb-0">
                                                            <i class="bi bi-star-fill me-2 text-warning"></i>{{ __('member.partials_affiliations_enhanced_skills_acquired') }} (<span id="skills-count-{{ $affiliation->id }}">{{ $affiliationSkills->count() }}</span>)
                                                        </h6>
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-warning btn-add-skill"
                                                                data-affiliation-id="{{ $affiliation->id }}"
                                                                data-member-id="{{ $user->id }}"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#addSkillModal">
                                                            <i class="bi bi-plus-circle me-1"></i> {{ __('member.partials_affiliations_enhanced_add_skill') }}
                                                        </button>
                                                    </div>
                                                    <div class="flex gap-2 flex-wrap" id="skills-list-{{ $affiliation->id }}" style="{{ $affiliationSkills->count() > 0 ? '' : 'display:none;' }}">
                                                            @foreach($affiliationSkills as $skill)
                                                                <div class="d-inline-flex align-items-center gap-1" id="skill-{{ $skill->id }}">
                                                                    <span class="badge skill-badge bg-{{ $skill->proficiency_level == 'expert' ? 'danger' : ($skill->proficiency_level == 'advanced' ? 'warning' : ($skill->proficiency_level == 'intermediate' ? 'info' : 'secondary')) }}"
                                                                          data-bs-toggle="tooltip"
                                                                          data-bs-placement="top"
                                                                          data-bs-html="true"
                                                                          title="<strong>{{ $skill->skill_name }}</strong><br>
                                                                                 {{ __('member.partials_affiliations_enhanced_tooltip_proficiency') }} {{ ucfirst($skill->proficiency_level) }}<br>
                                                                                 {{ __('member.partials_affiliations_enhanced_tooltip_duration') }} {{ $skill->formatted_duration }}<br>
                                                                                 @if($skill->instructor){{ __('member.partials_affiliations_enhanced_tooltip_instructor') }} {{ $skill->instructor->user->full_name ?? __('member.partials_affiliations_enhanced_unknown') }}<br>@endif
                                                                                 @if($skill->start_date){{ __('member.partials_affiliations_enhanced_tooltip_started') }} {{ $skill->start_date->format('M Y') }}@endif">
                                                                        <i class="bi bi-star-fill me-1"></i>{{ $skill->skill_name }}
                                                                        <span class="badge bg-white text-dark ms-1" style="font-size: 0.65rem;">{{ ucfirst($skill->proficiency_level) }}</span>
                                                                    </span>
                                                                    <button type="button"
                                                                            class="btn-delete-skill"
                                                                            style="background: none; border: none; color: #dc3545; padding: 0 2px; font-size: 0.8rem; line-height: 1;"
                                                                            data-skill-id="{{ $skill->id }}"
                                                                            data-member-id="{{ $user->id }}"
                                                                            data-affiliation-id="{{ $affiliation->id }}"
                                                                            title="{{ __('member.partials_affiliations_enhanced_remove_skill') }}">
                                                                        <i class="bi bi-x-circle"></i>
                                                                    </button>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                </div>

                                                <!-- Training Packages -->
                                                @if($affiliation->subscriptions && $affiliation->subscriptions->count() > 0)
                                                    <div class="mb-3">
                                                        <h6 class="font-bold mb-2">
                                                            <i class="bi bi-box-seam me-2 text-primary"></i>{{ __('member.partials_affiliations_enhanced_training_packages') }} ({{ $affiliation->subscriptions->count() }})
                                                        </h6>
                                                        <div class="flex gap-2 flex-wrap">
                                                            @foreach($affiliation->subscriptions as $subIndex => $subscription)
                                                                @if($subscription->package)
                                                                    <button type="button" class="btn btn-sm btn-outline-primary package-card-btn"
                                                                            data-bs-toggle="modal"
                                                                            data-bs-target="#packageModal_{{ $affiliation->id }}_{{ $subscription->id }}">
                                                                        <i class="bi bi-box me-1"></i>{{ $subscription->package->name }}
                                                                    </button>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif

                                                <!-- Instructors -->
                                                @php
                                                    $instructors = $affiliationSkills->pluck('instructor')->filter()->unique('id');
                                                @endphp
                                                @if($instructors->count() > 0)
                                                    <div class="mb-3">
                                                        <h6 class="font-bold mb-2">
                                                            <i class="bi bi-people-fill me-2 text-success"></i>{{ __('member.partials_affiliations_enhanced_instructors') }} ({{ $instructors->count() }})
                                                        </h6>
                                                        <div class="flex gap-2 flex-wrap">
                                                            @foreach($instructors as $instructor)
                                                                <div class="instructor-badge" role="button"
                                                                     data-bs-toggle="modal"
                                                                     data-bs-target="#instructorModal_{{ $instructor->id }}">
                                                                    <div class="flex items-center gap-2 p-2 bg-muted rounded">
                                                                        <div class="rounded-full bg-success text-white flex items-center justify-center" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                                            {{ mb_strtoupper(mb_substr($instructor->user->full_name ?? 'I', 0, 1, 'UTF-8'), 'UTF-8') }}
                                                                        </div>
                                                                        <div>
                                                                            <div class="font-semibold text-sm">{{ $instructor->user->full_name ?? __('member.partials_affiliations_enhanced_unknown') }}</div>
                                                                            <div class="text-muted-foreground" style="font-size: 0.7rem;">{{ $instructor->role ?? __('member.partials_affiliations_enhanced_instructor') }}</div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif

                                                <!-- Media & Certificates -->
                                                <div class="pt-2 border-top">
                                                    <div class="flex justify-between items-center mb-2">
                                                        <h6 class="font-bold mb-0">
                                                            <i class="bi bi-paperclip me-2 text-info"></i>{{ __('member.partials_affiliations_enhanced_media_certificates') }}
                                                            <span id="media-count-wrap-{{ $affiliation->id }}" style="{{ $affiliation->affiliationMedia->count() > 0 ? '' : 'display:none;' }}">(<span id="media-count-{{ $affiliation->id }}">{{ $affiliation->affiliationMedia->count() }}</span>)</span>
                                                        </h6>
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-info btn-add-media"
                                                                data-affiliation-id="{{ $affiliation->id }}"
                                                                data-member-id="{{ $user->id }}"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#addMediaModal">
                                                            <i class="bi bi-plus-circle me-1"></i> {{ __('member.partials_affiliations_enhanced_add_media') }}
                                                        </button>
                                                    </div>
                                                    <div class="flex gap-2 flex-wrap mt-2" id="media-list-{{ $affiliation->id }}" style="{{ $affiliation->affiliationMedia->count() > 0 ? '' : 'display:none;' }}">
                                                            @foreach($affiliation->affiliationMedia as $media)
                                                                <div class="d-inline-flex align-items-center gap-1 border rounded px-2 py-1 bg-muted" id="media-{{ $media->id }}" style="font-size: 0.85rem;">
                                                                    <i class="bi {{ $media->icon_class }} text-info"></i>
                                                                    <a href="{{ $media->full_url }}" target="_blank" class="text-decoration-none text-dark">{{ $media->title }}</a>
                                                                    <button type="button"
                                                                            class="btn-delete-media"
                                                                            style="background: none; border: none; color: #dc3545; padding: 0 2px; font-size: 0.8rem; line-height: 1;"
                                                                            data-media-id="{{ $media->id }}"
                                                                            data-member-id="{{ $user->id }}"
                                                                            data-affiliation-id="{{ $affiliation->id }}"
                                                                            title="{{ __('member.partials_affiliations_enhanced_remove') }}">
                                                                        <i class="bi bi-x-circle"></i>
                                                                    </button>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Instructor Modals -->
                            @php
                                $allInstructors = collect();
                                foreach($clubAffiliations as $aff) {
                                    $affInstructors = $aff->skillAcquisitions->pluck('instructor')->filter()->filter(fn($i) => $i->user !== null)->unique('id');
                                    $allInstructors = $allInstructors->merge($affInstructors);
                                }
                                $allInstructors = $allInstructors->unique('id');
                            @endphp

                            @foreach($allInstructors as $instructor)
                                <div class="modal fade" id="instructorModal_{{ $instructor->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 600px;">
                                        <div class="modal-content">
                                            <div class="modal-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                                                <h5 class="modal-title text-white">
                                                    <i class="bi bi-person-badge me-2"></i>{{ __('member.partials_affiliations_enhanced_instructor_profile') }}
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{{ __('member.partials_affiliations_enhanced_close') }}"></button>
                                            </div>
                                            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                                                <div class="text-center mb-4">
                                                    <!-- Profile Picture -->
                                                    <div class="mb-3">
                                                        @if($instructor->user->profile_picture)
                                                            <img src="{{ asset('storage/' . $instructor->user->profile_picture) }}"
                                                                 alt="{{ $instructor->user->full_name }}"
                                                                 class="rounded-full"
                                                                 style="width: 100px; height: 100px; object-fit: cover; border: 4px solid #11998e;">
                                                        @else
                                                            <div class="rounded-full mx-auto flex items-center justify-center text-white"
                                                                 style="width: 100px; height: 100px; font-size: 2.5rem; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                                                                {{ mb_strtoupper(mb_substr($instructor->user->full_name ?? 'I', 0, 1, 'UTF-8'), 'UTF-8') }}
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <!-- Name & Role -->
                                                    <h5 class="font-bold mb-1">{{ $instructor->user->full_name ?? __('member.partials_affiliations_enhanced_unknown_instructor') }}</h5>
                                                    <p class="text-muted-foreground mb-2">{{ $instructor->role ?? __('member.partials_affiliations_enhanced_instructor') }}</p>

                                                    <!-- Average Rating -->
                                                    @php
                                                        $avgRating = $instructor->reviews()->avg('rating') ?? 0;
                                                        $reviewCount = $instructor->reviews()->count();
                                                    @endphp
                                                    <div class="mb-3">
                                                        <div class="flex justify-center items-center gap-2">
                                                            <div class="stars-display" id="avgStars_{{ $instructor->id }}">
                                                                @for($i = 1; $i <= 5; $i++)
                                                                    <i class="bi bi-star{{ $i <= round($avgRating) ? '-fill' : '' }} text-warning"></i>
                                                                @endfor
                                                            </div>
                                                            <span class="text-muted-foreground text-sm" id="avgMeta_{{ $instructor->id }}">({{ number_format($avgRating, 1) }} / {{ $reviewCount }} {{ $reviewCount == 1 ? __('member.partials_affiliations_enhanced_review_singular') : __('member.partials_affiliations_enhanced_review_plural') }})</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Quick Stats -->
                                                <div class="grid grid-cols-12 gap-2 mb-3">
                                                    @php
                                                        $instructorSkills = \App\Models\SkillAcquisition::where('instructor_id', $instructor->id)->get();
                                                        $studentsCount = $instructorSkills->pluck('clubAffiliation.member_id')->unique()->count();
                                                        $skillsTaught = $instructorSkills->pluck('skill_name')->unique();
                                                    @endphp
                                                    <div class="col-span-6">
                                                        <div class="card bg-muted border-0">
                                                            <div class="card-body p-2">
                                                                <div class="text-2xl mb-0 text-primary">{{ $studentsCount }}</div>
                                                                <small class="text-muted-foreground">{{ __('member.partials_affiliations_enhanced_students') }}</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-span-6">
                                                        <div class="card bg-muted border-0">
                                                            <div class="card-body p-2">
                                                                <div class="text-2xl mb-0 text-success">{{ $skillsTaught->count() }}</div>
                                                                <small class="text-muted-foreground">{{ __('member.partials_affiliations_enhanced_skills') }}</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Skills Taught -->
                                                @if($skillsTaught->count() > 0)
                                                    <div class="mb-3 text-start">
                                                        <label class="text-muted-foreground text-sm font-semibold mb-2">{{ __('member.partials_affiliations_enhanced_specializes_in') }}</label>
                                                        <div class="flex gap-1 flex-wrap">
                                                            @foreach($skillsTaught as $skill)
                                                                <span class="badge bg-success">{{ $skill }}</span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif

                                                <!-- Contact Info -->
                                                @if($instructor->user->email)
                                                    <div class="mb-2 text-start">
                                                        <small class="text-muted-foreground">
                                                            <i class="bi bi-envelope me-1"></i>{{ $instructor->user->email }}
                                                        </small>
                                                    </div>
                                                @endif

                                                @if($instructor->user->mobile_formatted)
                                                    <div class="mb-3 text-start">
                                                        <small class="text-muted-foreground">
                                                            <i class="bi bi-phone me-1"></i>{{ $instructor->user->mobile_formatted }}
                                                        </small>
                                                    </div>
                                                @endif

                                                <!-- Reviews Section -->
                                                <div class="mt-4">
                                                    <h6 class="font-bold mb-3">
                                                        <i class="bi bi-chat-left-text me-2"></i>{{ __('member.partials_affiliations_enhanced_reviews') }}
                                                    </h6>

                                                    <!-- Add/Edit Review Form -->
                                                    @php
                                                        $userReview = $instructor->reviews()->where('reviewer_user_id', auth()->id())->first();
                                                    @endphp

                                                    <div class="card bg-muted mb-3" id="reviewForm_{{ $instructor->id }}">
                                                        <div class="card-body">
                                                            <form class="instructor-review-form" data-instructor-id="{{ $instructor->id }}" data-review-id="{{ $userReview->id ?? '' }}">
                                                                @csrf
                                                                <div class="mb-3">
                                                                    <label class="form-label text-sm font-semibold">{{ __('member.partials_affiliations_enhanced_your_rating') }}</label>
                                                                    <div class="star-rating" data-rating="{{ $userReview->rating ?? 0 }}">
                                                                        @for($i = 1; $i <= 5; $i++)
                                                                            <i class="bi bi-star{{ $userReview && $i <= $userReview->rating ? '-fill' : '' }} star-input" data-value="{{ $i }}"></i>
                                                                        @endfor
                                                                    </div>
                                                                    <input type="hidden" name="rating" value="{{ $userReview->rating ?? 0 }}" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label text-sm font-semibold">{{ __('member.partials_affiliations_enhanced_your_review') }}</label>
                                                                    <textarea name="comment" class="form-control form-control-sm" rows="3" placeholder="{{ __('member.partials_affiliations_enhanced_share_experience') }}">{{ $userReview->comment ?? '' }}</textarea>
                                                                </div>
                                                                <button type="submit" class="btn btn-success btn-sm w-full">
                                                                    <i class="bi bi-{{ $userReview ? 'pencil' : 'plus-circle' }} me-1"></i>
                                                                    {{ $userReview ? __('member.partials_affiliations_enhanced_update_review') : __('member.partials_affiliations_enhanced_submit_review') }}
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>

                                                    <!-- Reviews List -->
                                                    <div class="reviews-list" id="reviewsList_{{ $instructor->id }}">
                                                        @foreach($instructor->reviews()->with('reviewer')->latest()->get() as $review)
                                                            <div class="card mb-2" id="review-row-{{ $review->id }}">
                                                                <div class="card-body p-3">
                                                                    <div class="flex items-start mb-2">
                                                                        <div class="grow">
                                                                            <div class="font-semibold text-sm">{{ $review->reviewer->full_name }}</div>
                                                                            <div class="stars-display text-sm">
                                                                                @for($i = 1; $i <= 5; $i++)
                                                                                    <i class="bi bi-star{{ $i <= $review->rating ? '-fill' : '' }} text-warning"></i>
                                                                                @endfor
                                                                            </div>
                                                                        </div>
                                                                        <small class="text-muted-foreground">
                                                                            {{ $review->wasUpdated() ? __('member.partials_affiliations_enhanced_updated') . ' ' : '' }}{{ $review->wasUpdated() ? $review->updated_at->diffForHumans() : $review->reviewed_at->diffForHumans() }}
                                                                        </small>
                                                                    </div>
                                                                    @if($review->comment)
                                                                        <p class="mb-0 text-sm text-muted-foreground">{{ $review->comment }}</p>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <a href="{{ route('family.show', $instructor->user_id) }}" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-person-lines-fill me-1"></i>{{ __('member.partials_affiliations_enhanced_view_full_profile') }}
                                                </a>
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('member.partials_affiliations_enhanced_close') }}</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            <!-- Package Modals (Outside Timeline Loop) -->
                            @foreach($clubAffiliations as $affiliation)
                                @foreach($affiliation->subscriptions as $subIndex => $subscription)
                                    @if($subscription->package)
                                        <div class="modal fade" id="packageModal_{{ $affiliation->id }}_{{ $subscription->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                                <div class="modal-content">
                                                    <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                                        <h5 class="modal-title text-white">
                                                            <i class="bi bi-box-seam me-2"></i>{{ $subscription->package->name }}
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{{ __('member.partials_affiliations_enhanced_close') }}"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="text-muted-foreground text-sm font-semibold">{{ __('member.partials_affiliations_enhanced_subscription_period') }}</label>
                                                            <div>
                                                                <i class="bi bi-calendar-range me-2 text-primary"></i>
                                                                {{ $subscription->start_date ? $subscription->start_date->format('M d, Y') : __('member.partials_affiliations_enhanced_na') }} - {{ $subscription->end_date ? $subscription->end_date->format('M d, Y') : __('member.partials_affiliations_enhanced_na') }}
                                                            </div>
                                                            @php
                                                                $durationText = __('member.partials_affiliations_enhanced_na');
                                                                if ($subscription->start_date && $subscription->end_date) {
                                                                    $duration = $subscription->start_date->diff($subscription->end_date);
                                                                    $durationParts = [];
                                                                    if ($duration->y > 0) $durationParts[] = $duration->y . ' year' . ($duration->y > 1 ? 's' : '');
                                                                    if ($duration->m > 0) $durationParts[] = $duration->m . ' month' . ($duration->m > 1 ? 's' : '');
                                                                    if ($duration->d > 0) $durationParts[] = $duration->d . ' day' . ($duration->d > 1 ? 's' : '');
                                                                    $durationText = implode(' ', $durationParts) ?: __('member.partials_affiliations_enhanced_same_day');
                                                                }
                                                            @endphp
                                                            <small class="text-muted-foreground">
                                                                <i class="bi bi-hourglass-split me-1"></i>{{ __('member.partials_affiliations_enhanced_tooltip_duration') }} {{ $durationText }}
                                                            </small>
                                                        </div>

                                                        @if($subscription->package->description)
                                                            <div class="mb-3">
                                                                <label class="text-muted-foreground text-sm font-semibold">{{ __('member.partials_affiliations_enhanced_description') }}</label>
                                                                <p class="mb-0">{{ $subscription->package->description }}</p>
                                                            </div>
                                                        @endif

                                                        @if($subscription->package->price)
                                                            <div class="mb-3">
                                                                <label class="text-muted-foreground text-sm font-semibold">{{ __('member.partials_affiliations_enhanced_price') }}</label>
                                                                <div class="text-xl mb-0 text-success">
                                                                    <i class="bi bi-currency-dollar"></i>{{ number_format($subscription->package->price, 2) }}
                                                                </div>
                                                            </div>
                                                        @endif

                                                        @if($subscription->package->packageActivities && $subscription->package->packageActivities->count() > 0)
                                                            <div class="mb-3">
                                                                <label class="text-muted-foreground text-sm font-semibold">{{ __('member.partials_affiliations_enhanced_activities_skills_included') }}</label>
                                                                <div class="list-group">
                                                                    @foreach($subscription->package->packageActivities as $pkgActivity)
                                                                        @if($pkgActivity->activity)
                                                                            <div class="list-group-item">
                                                                                <div class="flex items-start mb-2">
                                                                                    <i class="bi bi-check-circle-fill text-success me-2 mt-1"></i>
                                                                                    <div class="grow">
                                                                                        <div class="font-semibold">{{ $pkgActivity->activity->name }}</div>
                                                                                        @if($pkgActivity->activity->description)
                                                                                            <small class="text-muted-foreground block mb-2">{{ $pkgActivity->activity->description }}</small>
                                                                                        @endif

                                                                                        @php
                                                                                            // Get skills taught in this activity
                                                                                            $activitySkills = \App\Models\SkillAcquisition::where('activity_id', $pkgActivity->activity_id)
                                                                                                ->where('club_affiliation_id', $affiliation->id)
                                                                                                ->get();
                                                                                        @endphp

                                                                                        @if($activitySkills->count() > 0)
                                                                                            <div class="mb-2">
                                                                                                <small class="text-muted-foreground block mb-1">{{ __('member.partials_affiliations_enhanced_skills_practiced') }}</small>
                                                                                                <div class="flex gap-1 flex-wrap">
                                                                                                    @foreach($activitySkills as $actSkill)
                                                                                                        <span class="badge bg-{{ $actSkill->proficiency_level == 'expert' ? 'danger' : ($actSkill->proficiency_level == 'advanced' ? 'warning' : ($actSkill->proficiency_level == 'intermediate' ? 'info' : 'secondary')) }}" style="font-size: 0.7rem;">
                                                                                                            <i class="bi bi-star-fill me-1"></i>{{ $actSkill->skill_name }}
                                                                                                        </span>
                                                                                                    @endforeach
                                                                                                </div>
                                                                                            </div>
                                                                                        @endif
                                                                                    </div>
                                                                                    @if($pkgActivity->instructor && $pkgActivity->instructor->user)
                                                                                        <div class="text-end">
                                                                                            <small class="text-muted-foreground">
                                                                                                <i class="bi bi-person-badge"></i>
                                                                                                {{ $pkgActivity->instructor->user->full_name }}
                                                                                            </small>
                                                                                        </div>
                                                                                    @endif
                                                                                </div>
                                                                            </div>
                                                                        @endif
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endif

                                                        @php
                                                            // Check if this package was subscribed to multiple times
                                                            $samePackageSubscriptions = $affiliation->subscriptions
                                                                ->where('package_id', $subscription->package_id)
                                                                ->where('id', '!=', $subscription->id);
                                                        @endphp

                                                        @if($samePackageSubscriptions->count() > 0)
                                                            <div class="mb-3">
                                                                <label class="text-muted-foreground text-sm font-semibold">
                                                                    <i class="bi bi-arrow-repeat me-1"></i>{{ __('member.partials_affiliations_enhanced_other_subscriptions') }}
                                                                </label>
                                                                <div class="alert alert-info mb-0" style="font-size: 0.85rem;">
                                                                    <div class="font-semibold mb-1">{{ __('member.partials_affiliations_enhanced_subscribed_times', ['count' => $samePackageSubscriptions->count() + 1]) }}</div>
                                                                    <ul class="mb-0 ps-3">
                                                                        <li class="text-primary font-semibold">
                                                                            {{ $subscription->start_date ? $subscription->start_date->format('M d, Y') : __('member.partials_affiliations_enhanced_na') }} - {{ $subscription->end_date ? $subscription->end_date->format('M d, Y') : __('member.partials_affiliations_enhanced_na') }} {{ __('member.partials_affiliations_enhanced_current_paren') }}
                                                                        </li>
                                                                        @foreach($samePackageSubscriptions as $otherSub)
                                                                            <li>
                                                                                {{ $otherSub->start_date ? $otherSub->start_date->format('M d, Y') : __('member.partials_affiliations_enhanced_na') }} - {{ $otherSub->end_date ? $otherSub->end_date->format('M d, Y') : __('member.partials_affiliations_enhanced_na') }}
                                                                                @php
                                                                                    $gap = 0;
                                                                                    if ($subscription->start_date && $otherSub->start_date) {
                                                                                        $gap = $subscription->start_date->diffInMonths($otherSub->start_date);
                                                                                    }
                                                                                @endphp
                                                                                @if($gap > 0)
                                                                                    <small class="text-muted-foreground">({{ abs($gap) }} {{ __('member.partials_affiliations_enhanced_months') }} {{ $subscription->start_date->gt($otherSub->start_date) ? __('member.partials_affiliations_enhanced_before') : __('member.partials_affiliations_enhanced_after') }} {{ __('member.partials_affiliations_enhanced_current_word') }})</small>
                                                                                @endif
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                        @endif

                                                        <div class="mb-0">
                                                            <label class="text-muted-foreground text-sm font-semibold">{{ __('member.partials_affiliations_enhanced_status') }}</label>
                                                            <div>
                                                                <span class="badge bg-{{ $subscription->status == 'active' ? 'success' : 'secondary' }}">
                                                                    {{ ucfirst($subscription->status) }}
                                                                </span>
                                                                <span class="badge bg-{{ $subscription->payment_status == 'paid' ? 'success' : 'warning' }} ms-2">
                                                                    {{ __('member.partials_affiliations_enhanced_payment') }} {{ ucfirst($subscription->payment_status) }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('member.partials_affiliations_enhanced_close') }}</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="text-center py-5">
                <i class="bi bi-diagram-3 text-muted-foreground" style="font-size: 3rem;"></i>
                <h5 class="text-muted-foreground mt-3 mb-2">{{ __('member.partials_affiliations_enhanced_no_affiliations') }}</h5>
                <p class="text-muted-foreground mb-3">{{ __('member.partials_affiliations_enhanced_empty_hint') }}</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAffiliationModal">
                    <i class="bi bi-plus-circle me-2"></i>{{ __('member.partials_affiliations_enhanced_add_first') }}
                </button>
            </div>
        @endif
    </div>
</div>

<!-- ── Add Affiliation Modal ──────────────────────────────────────────────── -->
<div class="modal fade" id="addAffiliationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 600px;">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h5 class="modal-title text-white"><i class="bi bi-plus-circle me-2"></i>{{ __('member.partials_affiliations_enhanced_add_club_affiliation') }}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addAffiliationForm">
                @csrf
                <div class="modal-body">
                    <!-- Club source toggle -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('member.partials_affiliations_enhanced_club') }}</label>
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-primary" id="togglePlatformClub">
                                <i class="bi bi-building me-1"></i> {{ __('member.partials_affiliations_enhanced_select_from_platform') }}
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleExternalClub">
                                <i class="bi bi-pencil me-1"></i> {{ __('member.partials_affiliations_enhanced_enter_manually') }}
                            </button>
                        </div>
                        <!-- Platform club selector -->
                        <div id="platformClubSection">
                            <select name="tenant_id" id="addTenantSelect" class="form-select">
                                <option value="">{{ __('member.partials_affiliations_enhanced_select_club_placeholder') }}</option>
                                @foreach($allClubs ?? [] as $club)
                                    <option value="{{ $club->id }}" data-location="{{ $club->address }}">{{ $club->club_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <!-- External / free-text club -->
                        <div id="externalClubSection" style="display:none;">
                            <input type="text" name="club_name" id="addClubNameInput" class="form-control" placeholder="{{ __('member.partials_affiliations_enhanced_external_club_placeholder') }}">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">{{ __('member.partials_affiliations_enhanced_start_date') }} <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">{{ __('member.partials_affiliations_enhanced_end_date') }} <small class="text-muted">{{ __('member.partials_affiliations_enhanced_leave_blank_ongoing') }}</small></label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('member.partials_affiliations_enhanced_location') }}</label>
                        <input type="text" name="location" id="addLocationInput" class="form-control" placeholder="{{ __('member.partials_affiliations_enhanced_location_placeholder') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('member.partials_affiliations_enhanced_coaches') }} <small class="text-muted">{{ __('member.partials_affiliations_enhanced_comma_separated') }}</small></label>
                        <input type="text" name="coaches" class="form-control" placeholder="{{ __('member.partials_affiliations_enhanced_coaches_placeholder') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('member.partials_affiliations_enhanced_description') }}</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="{{ __('member.partials_affiliations_enhanced_description_placeholder') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('shared.cancel') }}</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>{{ __('member.partials_affiliations_enhanced_save_affiliation') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Edit Affiliation Modal ─────────────────────────────────────────────── -->
<div class="modal fade" id="editAffiliationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 600px;">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h5 class="modal-title text-white"><i class="bi bi-pencil me-2"></i>{{ __('member.partials_affiliations_enhanced_edit_club_affiliation') }}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editAffiliationForm">
                @csrf
                <input type="hidden" id="editAffiliationId">
                <input type="hidden" id="editAffiliationMemberId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('member.partials_affiliations_enhanced_club_name') }} <span class="text-danger">*</span></label>
                        <input type="text" id="editClubName" name="club_name" class="form-control" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">{{ __('member.partials_affiliations_enhanced_start_date') }} <span class="text-danger">*</span></label>
                            <input type="date" id="editStartDate" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">{{ __('member.partials_affiliations_enhanced_end_date') }} <small class="text-muted">{{ __('member.partials_affiliations_enhanced_leave_blank_ongoing') }}</small></label>
                            <input type="date" id="editEndDate" name="end_date" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('member.partials_affiliations_enhanced_location') }}</label>
                        <input type="text" id="editLocation" name="location" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('member.partials_affiliations_enhanced_coaches') }} <small class="text-muted">{{ __('member.partials_affiliations_enhanced_comma_separated') }}</small></label>
                        <input type="text" id="editCoaches" name="coaches" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('member.partials_affiliations_enhanced_description') }}</label>
                        <textarea id="editDescription" name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('shared.cancel') }}</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>{{ __('member.partials_affiliations_enhanced_update_affiliation') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Add Skill Modal ────────────────────────────────────────────────────── -->
{{--
  Self-contained Alpine modal. The outer .modal + id stay so the existing
  data-bs-toggle triggers and the app.blade.php bridge keep working untouched;
  everything inside is design-system Tailwind (Design Rule #4: no native
  <select>, no native date input, no OS-rendered popups).

  Contract with the surrounding page script:
    • opening  — the .btn-add-skill delegate dispatches `add-skill:open` with
                 {affiliationId, memberId, activitiesUrl}; the component resets
                 itself and loads the activity list.
    • posting  — every value is mirrored into a named input inside #addSkillForm,
                 so the existing formToObject(form) submit handler is unchanged.
    • closing  — the submit handler calls bsModal.hide() exactly as before.
--}}
<div class="modal fade" id="addSkillModal" tabindex="-1" aria-hidden="true"
     x-data="addSkillModal()"
     @add-skill:open.window="openFor($event.detail)">
    {{-- @click.self (NOT @click.outside): the trigger button lives outside the card,
         so an outside-handler fires on the very click that opens the modal and closes
         it again immediately. .self only fires when the backdrop area itself is hit. --}}
    <div class="min-h-full flex items-center justify-center p-4" @click.self="close()">
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden">

            {{-- Header: a soft accent band rather than the old pink gradient --}}
            <div class="relative px-6 pt-6 pb-5 bg-accent/60 border-b border-gray-100">
                <div class="absolute inset-0 pointer-events-none opacity-60"
                     style="background:radial-gradient(120% 100% at 0% 0%, hsl(250 65% 65% / .18), transparent 60%);"></div>
                <div class="relative flex items-start gap-3.5">
                    <span class="w-11 h-11 rounded-xl bg-primary text-white flex items-center justify-center flex-shrink-0 shadow-sm">
                        <i class="bi bi-stars text-xl"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h5 class="text-lg font-bold text-gray-900 mb-0.5">{{ __('member.partials_affiliations_enhanced_add_skill') }}</h5>
                        <p class="text-xs text-muted-foreground" x-text="clubLabel"></p>
                    </div>
                    <button type="button" @click="close()"
                            class="w-9 h-9 -mt-1 -me-1 rounded-lg flex items-center justify-center text-muted-foreground hover:bg-white/70 hover:text-foreground transition-colors flex-shrink-0"
                            aria-label="{{ __('shared.cancel') }}">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <form id="addSkillForm" autocomplete="off">
                @csrf
                <input type="hidden" id="addSkillAffiliationId">
                <input type="hidden" id="addSkillMemberId">

                <div class="px-6 py-5 space-y-5 max-h-[70vh] overflow-y-auto">

                    {{-- Skill name --}}
                    <div>
                        <label for="addSkillName" class="block text-sm font-medium text-gray-700 mb-1.5">
                            {{ __('member.partials_affiliations_enhanced_skill_name') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="addSkillName" name="skill_name" x-model="skillName" required
                               placeholder="{{ __('member.partials_affiliations_enhanced_skill_name_placeholder') }}"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent transition-shadow">
                    </div>

                    {{-- Activity — searchable combobox over the club's activities + the
                         global directory. Free text is still allowed (off-platform clubs
                         have no activity rows), so this is a combobox, not a select. --}}
                    <div x-data="{ get panelId() { return 'addSkillActivityPanel' } }">
                        <label for="addSkillActivity" class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('Activity') }}</label>
                        <div class="relative" @click.outside="acOpen = false" @keydown.escape.stop="acOpen = false">
                            <div class="relative">
                                <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                                <input type="text" id="addSkillActivity" x-model="activityQuery" role="combobox"
                                       :aria-expanded="acOpen" aria-controls="addSkillActivityPanel" aria-autocomplete="list"
                                       @focus="acOpen = true" @input="acOpen = true; activityId = ''; acIndex = 0"
                                       @keydown.arrow-down.prevent="acOpen = true; acIndex = Math.min(acIndex + 1, flatOptions.length - 1); scrollActive()"
                                       @keydown.arrow-up.prevent="acIndex = Math.max(acIndex - 1, 0); scrollActive()"
                                       @keydown.enter.prevent="chooseActive()"
                                       placeholder="{{ __('The discipline/class you trained') }}"
                                       class="w-full ps-9 pe-9 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent transition-shadow">
                                <button type="button" @click="acOpen = !acOpen; if (acOpen) $refs.acInput?.focus?.()"
                                        class="absolute end-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-md flex items-center justify-center text-gray-400 hover:text-foreground hover:bg-muted/60 transition-colors"
                                        aria-label="{{ __('Activity') }}">
                                    <i class="bi bi-chevron-down text-xs transition-transform duration-200" :class="acOpen && 'rotate-180'"></i>
                                </button>
                            </div>

                            {{-- Selected-club-activity confirmation chip --}}
                            <p x-show="activityId" x-cloak class="mt-1.5 text-[11px] text-primary font-medium flex items-center gap-1">
                                <i class="bi bi-patch-check-fill"></i>{{ __('Linked to this club\'s activity') }}
                            </p>

                            <div x-show="acOpen" x-cloak id="addSkillActivityPanel" role="listbox" x-ref="acPanel"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                 class="absolute z-20 mt-1.5 w-full max-h-60 overflow-y-auto bg-white border border-gray-100 rounded-xl shadow-lg">

                                <p x-show="acLoading" class="px-3 py-4 text-xs text-muted-foreground text-center">
                                    <i class="bi bi-arrow-repeat animate-spin me-1"></i>{{ __('shared.loading') }}
                                </p>

                                <template x-for="(group, gi) in groupedOptions" :key="group.key">
                                    <div x-show="group.items.length">
                                        <p class="px-3 pt-2.5 pb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground"
                                           x-text="group.label"></p>
                                        <template x-for="opt in group.items" :key="group.key + ':' + opt.name">
                                            <button type="button" role="option" :data-idx="opt.__i"
                                                    :aria-selected="activityQuery === opt.name"
                                                    @click="choose(opt)"
                                                    @mousemove="acIndex = opt.__i"
                                                    class="w-full text-start px-3 py-2 text-sm flex items-center gap-2.5 transition-colors"
                                                    :class="acIndex === opt.__i ? 'bg-muted/70' : 'hover:bg-muted/50'">
                                                <span class="w-6 h-6 rounded-md flex items-center justify-center flex-shrink-0"
                                                      :class="opt.id ? 'bg-accent text-primary' : 'bg-muted text-muted-foreground'">
                                                    <i class="bi text-[11px]" :class="opt.id ? 'bi-building' : 'bi-grid'"></i>
                                                </span>
                                                <span class="flex-1 truncate text-foreground" x-text="opt.name"></span>
                                                <i class="bi bi-check2 text-primary" x-show="activityQuery === opt.name"></i>
                                            </button>
                                        </template>
                                    </div>
                                </template>

                                {{-- Free text: keep whatever was typed, unlinked. --}}
                                <button type="button" x-show="!acLoading && flatOptions.length === 0 && activityQuery.trim()"
                                        @click="acOpen = false"
                                        class="w-full text-start px-3 py-3 text-sm flex items-center gap-2.5 hover:bg-muted/50 transition-colors">
                                    <span class="w-6 h-6 rounded-md bg-muted text-muted-foreground flex items-center justify-center flex-shrink-0">
                                        <i class="bi bi-pencil text-[11px]"></i>
                                    </span>
                                    <span class="text-muted-foreground">{{ __('Use') }} “<span class="text-foreground font-medium" x-text="activityQuery.trim()"></span>”</span>
                                </button>

                                <p x-show="!acLoading && flatOptions.length === 0 && !activityQuery.trim()"
                                   class="px-3 py-4 text-xs text-muted-foreground text-center">{{ __('No matching activities') }}</p>
                            </div>
                        </div>
                        {{-- What actually posts --}}
                        <input type="hidden" name="activity_name" :value="activityQuery.trim()">
                        <input type="hidden" name="activity_id" id="addSkillActivityId" :value="activityId">
                    </div>

                    {{-- Proficiency — four options, so a segmented picker beats a dropdown --}}
                    <div>
                        <span class="block text-sm font-medium text-gray-700 mb-1.5">
                            {{ __('member.partials_affiliations_enhanced_proficiency_level') }} <span class="text-red-500">*</span>
                        </span>
                        <div class="grid grid-cols-4 gap-2" role="radiogroup">
                            <template x-for="lvl in levels" :key="lvl.value">
                                <button type="button" role="radio" :aria-checked="level === lvl.value"
                                        @click="level = lvl.value"
                                        class="group px-2 py-2.5 rounded-xl border text-center transition-all"
                                        :class="level === lvl.value
                                            ? 'border-primary bg-primary/5 shadow-sm'
                                            : 'border-gray-200 hover:border-gray-300 hover:bg-muted/40'">
                                    <span class="flex items-center justify-center gap-0.5 mb-1">
                                        <template x-for="n in 4" :key="n">
                                            <i class="bi text-[9px]"
                                               :class="n <= lvl.pips
                                                    ? (level === lvl.value ? 'bi-circle-fill text-primary' : 'bi-circle-fill text-gray-300')
                                                    : 'bi-circle text-gray-200'"></i>
                                        </template>
                                    </span>
                                    <span class="block text-[11px] font-semibold leading-tight"
                                          :class="level === lvl.value ? 'text-primary' : 'text-gray-600'"
                                          x-text="lvl.label"></span>
                                </button>
                            </template>
                        </div>
                        <input type="hidden" name="proficiency_level" :value="level">
                    </div>

                    {{-- Start date + duration --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('member.partials_affiliations_enhanced_start_date') }}</label>
                            <x-date-picker model="startDate" name="start_date" :max="now()->toDateString()" />
                        </div>
                        <div>
                            <label for="addSkillDuration" class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('member.partials_affiliations_enhanced_duration_months') }}</label>
                            <input type="number" id="addSkillDuration" name="duration_months" x-model="duration" min="1" max="600"
                                   placeholder="{{ __('member.partials_affiliations_enhanced_duration_months_placeholder') }}"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent transition-shadow">
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div>
                        <label for="addSkillNotes" class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('member.partials_affiliations_enhanced_notes') }}</label>
                        <textarea id="addSkillNotes" name="notes" x-model="notes" rows="2" maxlength="500"
                                  placeholder="{{ __('member.partials_affiliations_enhanced_notes_placeholder') }}"
                                  class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent transition-shadow resize-none"></textarea>
                    </div>
                </div>

                <div class="px-6 py-4 bg-muted/40 border-t border-gray-100 flex items-center justify-end gap-2.5">
                    <button type="button" @click="close()"
                            class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-white hover:text-foreground transition-colors">
                        {{ __('shared.cancel') }}
                    </button>
                    <button type="submit" :disabled="!skillName.trim() || !level"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold shadow-sm hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        <i class="bi bi-plus-circle"></i>{{ __('member.partials_affiliations_enhanced_add_skill') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addSkillModal() {
    return {
        skillName: '', activityQuery: '', activityId: '', level: '',
        startDate: '', duration: '', notes: '',
        clubLabel: '',
        clubActivities: [], catalog: [],
        acOpen: false, acLoading: false, acIndex: 0,

        levels: [
            { value: 'beginner',     label: @js(__('member.partials_affiliations_enhanced_beginner')),     pips: 1 },
            { value: 'intermediate', label: @js(__('member.partials_affiliations_enhanced_intermediate')), pips: 2 },
            { value: 'advanced',     label: @js(__('member.partials_affiliations_enhanced_advanced')),     pips: 3 },
            { value: 'expert',       label: @js(__('member.partials_affiliations_enhanced_expert')),       pips: 4 },
        ],

        // Club activities first (they carry the id that links provenance), then the
        // rest of the directory. Filtering is a plain substring match on both.
        get groupedOptions() {
            const q = this.activityQuery.trim().toLowerCase();
            const match = (a) => !q || a.name.toLowerCase().includes(q);
            const club = this.clubActivities.filter(match);
            const all  = this.catalog.filter(match);
            let i = 0;
            const stamp = (arr) => arr.map(o => ({ ...o, __i: i++ }));
            return [
                { key: 'club', label: @js(__('This club')),      items: stamp(club) },
                { key: 'all',  label: @js(__('All activities')), items: stamp(all) },
            ];
        },
        get flatOptions() { return this.groupedOptions.flatMap(g => g.items); },

        choose(opt) {
            this.activityQuery = opt.name;
            this.activityId = opt.id || '';
            this.acOpen = false;
        },
        chooseActive() {
            const opt = this.flatOptions[this.acIndex];
            if (opt) this.choose(opt); else this.acOpen = false;
        },
        scrollActive() {
            this.$nextTick(() => {
                this.$refs.acPanel?.querySelector(`[data-idx="${this.acIndex}"]`)
                    ?.scrollIntoView({ block: 'nearest' });
            });
        },

        reset() {
            this.skillName = ''; this.activityQuery = ''; this.activityId = '';
            this.level = ''; this.startDate = ''; this.duration = ''; this.notes = '';
            this.acOpen = false; this.acIndex = 0;
            this.clubActivities = []; this.catalog = [];
        },

        async openFor(detail) {
            this.reset();
            this.clubLabel = detail.clubName || '';
            document.getElementById('addSkillAffiliationId').value = detail.affiliationId || '';
            document.getElementById('addSkillMemberId').value = detail.memberId || '';

            this.acLoading = true;
            try {
                const res = await fetch(`/member/${detail.memberId}/affiliations/${detail.affiliationId}/activities`,
                    { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                this.clubActivities = data.activities || [];
                this.catalog = data.suggestions || [];
            } catch (_) { /* the field still accepts free text */ }
            this.acLoading = false;
        },

        close() {
            const el = document.getElementById('addSkillModal');
            if (el?.classList.contains('show')) window.bsModal?.hide(el);
        },
    };
}
</script>

<!-- ── Add Media Modal ────────────────────────────────────────────────────── -->
<div class="modal fade" id="addMediaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <h5 class="modal-title text-white"><i class="bi bi-paperclip me-2"></i>{{ __('member.partials_affiliations_enhanced_add_media_certificate') }}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addMediaForm">
                @csrf
                <input type="hidden" id="addMediaAffiliationId">
                <input type="hidden" id="addMediaMemberId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('member.partials_affiliations_enhanced_type') }} <span class="text-danger">*</span></label>
                        <select name="media_type" class="form-select" required>
                            <option value="">{{ __('member.partials_affiliations_enhanced_select_type') }}</option>
                            <option value="certificate">{{ __('member.partials_affiliations_enhanced_certificate') }}</option>
                            <option value="photo">{{ __('member.partials_affiliations_enhanced_photo') }}</option>
                            <option value="video">{{ __('member.partials_affiliations_enhanced_video') }}</option>
                            <option value="document">{{ __('member.partials_affiliations_enhanced_document') }}</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('member.partials_affiliations_enhanced_title') }} <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="{{ __('member.partials_affiliations_enhanced_title_placeholder') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('member.partials_affiliations_enhanced_url_link') }} <span class="text-danger">*</span></label>
                        <input type="text" name="media_url" class="form-control" placeholder="{{ __('member.partials_affiliations_enhanced_url_placeholder') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('member.partials_affiliations_enhanced_description') }}</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="{{ __('member.partials_affiliations_enhanced_description_placeholder2') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('shared.cancel') }}</button>
                    <button type="submit" class="btn btn-info text-white"><i class="bi bi-plus-circle me-1"></i>{{ __('member.partials_affiliations_enhanced_add_media') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Enhanced Timeline Styles */
.timeline-enhanced {
    position: relative;
    padding-left: 40px;
}

.timeline-enhanced::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
}

.timeline-item-enhanced {
    position: relative;
    animation: fadeInUp 0.5s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.timeline-marker-enhanced {
    position: absolute;
    left: -28px;
    top: 25px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: 3px solid #fff;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
    z-index: 1;
}

.timeline-marker-enhanced.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
    }
    50% {
        box-shadow: 0 0 0 8px rgba(102, 126, 234, 0.4);
    }
}

.affiliation-card-enhanced {
    transition: all 0.3s ease;
    margin-left: 10px;
}

.affiliation-card-enhanced:hover {
    transform: translateX(5px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.15) !important;
}

.skill-badge {
    padding: 0.5rem 0.75rem;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s ease;
    cursor: pointer;
}

.skill-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.package-card-btn {
    transition: all 0.2s ease;
}

.package-card-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.modal-dialog {
    max-width: 600px;
}

.modal-body {
    max-height: 60vh;
    overflow-y: auto;
}

.instructor-badge {
    transition: all 0.2s ease;
    cursor: pointer;
}

.instructor-badge:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.instructor-badge:active {
    transform: scale(0.98);
}

/* Star Rating Styles */
.star-rating {
    font-size: 1.5rem;
    cursor: pointer;
}

.star-rating .star-input {
    color: #ddd;
    transition: color 0.2s ease;
}

.star-rating .star-input:hover,
.star-rating .star-input.active {
    color: #ffc107;
}

.star-rating .bi-star-fill {
    color: #ffc107;
}

.stars-display {
    font-size: 1rem;
}

.stars-display .bi-star-fill {
    color: #ffc107;
}

.stars-display .bi-star {
    color: #ddd;
}

/* Filter transition */
.timeline-item-enhanced.filtered-out {
    display: none;
    animation: fadeOut 0.3s ease-out;
}

@keyframes fadeOut {
    from {
        opacity: 1;
        transform: translateY(0);
    }
    to {
        opacity: 0;
        transform: translateY(-20px);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const memberId = {{ $user->id }};
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const skillFilter = document.getElementById('skillFilter');
    const resetButton = document.getElementById('resetFilters');
    const timelineItems = document.querySelectorAll('.timeline-item-enhanced');

    // ── Helpers ──────────────────────────────────────────────────────────────
    function affFetch(url, method, data) {
        return fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: data ? JSON.stringify(data) : undefined,
        }).then(r => r.json());
    }

    function showAlert(message, type) {
        // Route through the global toast — never render an inline alert on the page.
        window.showToast(type === 'danger' ? 'error' : type, message);
    }

    function formToObject(form) {
        const obj = {};
        new FormData(form).forEach((v, k) => { obj[k] = v; });
        return obj;
    }

    // ── Platform / External club toggle ──────────────────────────────────────
    const platformSection = document.getElementById('platformClubSection');
    const externalSection = document.getElementById('externalClubSection');
    const tenantSelect    = document.getElementById('addTenantSelect');
    const locationInput   = document.getElementById('addLocationInput');
    const clubNameInput   = document.getElementById('addClubNameInput');

    document.getElementById('togglePlatformClub')?.addEventListener('click', function() {
        platformSection.style.display = '';
        externalSection.style.display = 'none';
        if (clubNameInput) clubNameInput.removeAttribute('required');
        this.classList.replace('btn-outline-secondary', 'btn-primary');
        document.getElementById('toggleExternalClub').classList.replace('btn-primary', 'btn-outline-secondary');
    });

    document.getElementById('toggleExternalClub')?.addEventListener('click', function() {
        platformSection.style.display = 'none';
        externalSection.style.display = '';
        if (tenantSelect) tenantSelect.value = '';
        if (clubNameInput) clubNameInput.setAttribute('required', '');
        this.classList.replace('btn-outline-secondary', 'btn-primary');
        document.getElementById('togglePlatformClub').classList.replace('btn-primary', 'btn-outline-secondary');
    });

    // Auto-fill location when a platform club is selected
    tenantSelect?.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (locationInput && opt.dataset.location) locationInput.value = opt.dataset.location;
    });

    // ── Build a timeline card for a newly-created affiliation (no skills/media yet)
    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
    }
    const GRADIENTS = ['#667eea 0%, #764ba2', '#f093fb 0%, #f5576c', '#4facfe 0%, #00f2fe', '#fa709a 0%, #fee140'];

    function buildAffiliationCard(aff, index) {
        const gradient = GRADIENTS[index % 4];
        const logoHtml = aff.logo_url
            ? `<img src="${escapeHtml(aff.logo_url)}" alt="${escapeHtml(aff.club_name)}" class="rounded-full me-3" style="width: 50px; height: 50px; object-fit: cover; border: 3px solid white;">`
            : `<div class="rounded-full bg-white flex items-center justify-center me-3" style="width: 50px; height: 50px;"><i class="bi bi-building" style="font-size: 1.5rem; color: #667eea;"></i></div>`;
        const datesHtml = aff.start_label
            ? `<small class="opacity-90" id="affiliation-dates-${aff.id}"><i class="bi bi-calendar-event me-1"></i>${escapeHtml(aff.start_label)} - ${escapeHtml(aff.end_label)}</small>`
            : '';
        const durationHtml = aff.formatted_duration
            ? `<small class="opacity-90" id="affiliation-duration-${aff.id}"><i class="bi bi-hourglass-split me-1"></i>${escapeHtml(aff.formatted_duration)}</small>`
            : '';
        const activeBadge = aff.is_ongoing
            ? `<span class="badge bg-success"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>{{ __('member.partials_affiliations_enhanced_active') }}</span>`
            : '';
        const locationHtml = aff.location
            ? `<div class="mb-3"><i class="bi bi-geo-alt text-primary me-2"></i><span class="text-muted-foreground">${escapeHtml(aff.location)}</span></div>`
            : '';

        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <div class="timeline-item-enhanced mb-4" id="affiliation-${aff.id}" data-affiliation-id="${aff.id}" data-skills="">
                <div class="timeline-marker-enhanced ${aff.is_ongoing ? 'pulse' : ''}"></div>
                <div class="affiliation-card-enhanced card border-0 shadow-sm">
                    <div class="card-header border-0 p-3" style="background: linear-gradient(135deg, ${gradient} 100%);">
                        <div class="flex items-center">
                            ${logoHtml}
                            <div class="grow text-white">
                                <h5 class="mb-1 font-bold" id="affiliation-name-${aff.id}">${escapeHtml(aff.club_name)}</h5>
                                <div class="flex gap-3 flex-wrap">${datesHtml}${durationHtml}</div>
                            </div>
                            ${activeBadge}
                            <div class="flex gap-1 ms-2">
                                <button type="button" class="btn btn-sm btn-edit-affiliation" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 2px 8px;" data-affiliation-id="${aff.id}" data-club-name="${escapeHtml(aff.club_name)}" data-start-date="${escapeHtml(aff.start_date)}" data-end-date="${escapeHtml(aff.end_date || '')}" data-location="${escapeHtml(aff.location || '')}" data-description="${escapeHtml(aff.description || '')}" data-coaches="${escapeHtml(aff.coaches || '')}" data-member-id="${memberId}" data-bs-toggle="modal" data-bs-target="#editAffiliationModal" title="{{ __('shared.edit') }}"><i class="bi bi-pencil"></i></button>
                                <button type="button" class="btn btn-sm btn-delete-affiliation" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 2px 8px;" data-affiliation-id="${aff.id}" data-club-name="${escapeHtml(aff.club_name)}" data-member-id="${memberId}" title="{{ __('shared.delete') }}"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        ${locationHtml}
                        <div class="mb-3">
                            <div class="flex justify-between items-center mb-2">
                                <h6 class="font-bold mb-0"><i class="bi bi-star-fill me-2 text-warning"></i>{{ __('member.partials_affiliations_enhanced_skills_acquired') }} (<span id="skills-count-${aff.id}">0</span>)</h6>
                                <button type="button" class="btn btn-sm btn-outline-warning btn-add-skill" data-affiliation-id="${aff.id}" data-member-id="${memberId}" data-bs-toggle="modal" data-bs-target="#addSkillModal"><i class="bi bi-plus-circle me-1"></i> {{ __('member.partials_affiliations_enhanced_add_skill') }}</button>
                            </div>
                            <div class="flex gap-2 flex-wrap" id="skills-list-${aff.id}" style="display:none;"></div>
                        </div>
                        <div class="pt-2 border-top">
                            <div class="flex justify-between items-center mb-2">
                                <h6 class="font-bold mb-0"><i class="bi bi-paperclip me-2 text-info"></i>{{ __('member.partials_affiliations_enhanced_media_certificates') }} <span id="media-count-wrap-${aff.id}" style="display:none;">(<span id="media-count-${aff.id}">0</span>)</span></h6>
                                <button type="button" class="btn btn-sm btn-outline-info btn-add-media" data-affiliation-id="${aff.id}" data-member-id="${memberId}" data-bs-toggle="modal" data-bs-target="#addMediaModal"><i class="bi bi-plus-circle me-1"></i> {{ __('member.partials_affiliations_enhanced_add_media') }}</button>
                            </div>
                            <div class="flex gap-2 flex-wrap mt-2" id="media-list-${aff.id}" style="display:none;"></div>
                        </div>
                    </div>
                </div>
            </div>`;
        return wrapper.firstElementChild;
    }

    // ── Add Affiliation ───────────────────────────────────────────────────────
    document.getElementById('addAffiliationForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('[type=submit]');
        btn.disabled = true;
        const form = this;
        affFetch(`/member/${memberId}/affiliations`, 'POST', formToObject(this))
            .then(res => {
                if (res.success && res.affiliation) {
                    const timeline = document.getElementById('affiliationsTimeline');
                    if (!timeline) {
                        // Empty-state is showing — full scaffold (summary cards/timeline) is not present.
                        // Reload to render the scaffold for the very first affiliation.
                        showAlert(res.message, 'success');
                        setTimeout(() => location.reload(), 800);
                        return;
                    }
                    const index = timeline.querySelectorAll('.timeline-item-enhanced').length;
                    const card = buildAffiliationCard(res.affiliation, index);
                    timeline.appendChild(card);
                    wireAffiliationCard(card);
                    form.reset();
                    bsModal.hide(document.getElementById('addAffiliationModal'));
                    showAlert(res.message, 'success');
                    btn.disabled = false;
                } else if (res.success) {
                    showAlert(res.message, 'success'); setTimeout(() => location.reload(), 800);
                } else { showAlert(res.message || '{{ __("member.partials_affiliations_enhanced_js_error_saving_affiliation") }}', 'danger'); btn.disabled = false; }
            }).catch(() => { showAlert('{{ __("member.partials_affiliations_enhanced_js_error_generic") }}', 'danger'); btn.disabled = false; });
    });

    // ── Helpers for in-place patching ─────────────────────────────────────────
    function populateEditModal(btn) {
        document.getElementById('editAffiliationId').value = btn.dataset.affiliationId;
        document.getElementById('editAffiliationMemberId').value = btn.dataset.memberId;
        document.getElementById('editClubName').value = btn.dataset.clubName;
        document.getElementById('editStartDate').value = btn.dataset.startDate;
        document.getElementById('editEndDate').value = btn.dataset.endDate;
        document.getElementById('editLocation').value = btn.dataset.location;
        document.getElementById('editDescription').value = btn.dataset.description;
        document.getElementById('editCoaches').value = btn.dataset.coaches;
    }

    function buildSkillNode(skill, affiliationId) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <div class="d-inline-flex align-items-center gap-1" id="skill-${skill.id}">
                <span class="badge skill-badge bg-${skill.badge_color}" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true" title="<strong>${escapeHtml(skill.skill_name)}</strong><br>${skill.activity ? '{{ __('Activity') }}: ' + escapeHtml(skill.activity) + '<br>' : ''}{{ __('member.partials_affiliations_enhanced_tooltip_proficiency') }} ${escapeHtml(skill.proficiency_level.charAt(0).toUpperCase() + skill.proficiency_level.slice(1))}<br>{{ __('member.partials_affiliations_enhanced_tooltip_duration') }} ${escapeHtml(skill.formatted_duration)}<br>${skill.start_label ? '{{ __("member.partials_affiliations_enhanced_tooltip_started") }} ' + escapeHtml(skill.start_label) : ''}${skill.verification && skill.verification.status ? '<br>{{ __('Status') }}: ' + escapeHtml(skill.verification.status.replace('_',' ')) : ''}">
                    <i class="bi bi-star-fill me-1"></i>${escapeHtml(skill.skill_name)}
                    <span class="badge bg-white text-dark ms-1" style="font-size: 0.65rem;">${escapeHtml(skill.proficiency_level.charAt(0).toUpperCase() + skill.proficiency_level.slice(1))}</span>
                </span>
                <button type="button" class="btn-delete-skill" style="background: none; border: none; color: #dc3545; padding: 0 2px; font-size: 0.8rem; line-height: 1;" data-skill-id="${skill.id}" data-member-id="${memberId}" data-affiliation-id="${affiliationId}" title="{{ __('member.partials_affiliations_enhanced_remove_skill') }}"><i class="bi bi-x-circle"></i></button>
            </div>`;
        return wrapper.firstElementChild;
    }

    function buildMediaNode(media, affiliationId) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <div class="d-inline-flex align-items-center gap-1 border rounded px-2 py-1 bg-muted" id="media-${media.id}" style="font-size: 0.85rem;">
                <i class="bi ${escapeHtml(media.icon_class)} text-info"></i>
                <a href="${escapeHtml(media.full_url)}" target="_blank" class="text-decoration-none text-dark">${escapeHtml(media.title)}</a>
                <button type="button" class="btn-delete-media" style="background: none; border: none; color: #dc3545; padding: 0 2px; font-size: 0.8rem; line-height: 1;" data-media-id="${media.id}" data-member-id="${memberId}" data-affiliation-id="${affiliationId}" title="{{ __('member.partials_affiliations_enhanced_remove') }}"><i class="bi bi-x-circle"></i></button>
            </div>`;
        return wrapper.firstElementChild;
    }

    function initTooltip(el) {
        if (window.bootstrap && el.getAttribute('data-bs-toggle') === 'tooltip') {
            try { new bootstrap.Tooltip(el); } catch (e) {}
        }
        el.querySelectorAll?.('[data-bs-toggle="tooltip"]').forEach(t => { try { new bootstrap.Tooltip(t); } catch (e) {} });
    }

    // Placeholder kept for symmetry with the add flow (delegation handles wiring)
    function wireAffiliationCard(card) { /* events are delegated on document */ }

    // ── Edit Affiliation — submit ─────────────────────────────────────────────
    document.getElementById('editAffiliationForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const affiliationId = document.getElementById('editAffiliationId').value;
        const mid = document.getElementById('editAffiliationMemberId').value;
        const btn = this.querySelector('[type=submit]');
        btn.disabled = true;
        affFetch(`/member/${mid}/affiliations/${affiliationId}`, 'PUT', formToObject(this))
            .then(res => {
                if (res.success && res.affiliation) {
                    const a = res.affiliation;
                    const nameEl = document.getElementById(`affiliation-name-${a.id}`);
                    if (nameEl) nameEl.textContent = a.club_name;
                    const datesEl = document.getElementById(`affiliation-dates-${a.id}`);
                    if (datesEl && a.start_label) datesEl.innerHTML = `<i class="bi bi-calendar-event me-1"></i>${escapeHtml(a.start_label)} - ${escapeHtml(a.end_label)}`;
                    const durEl = document.getElementById(`affiliation-duration-${a.id}`);
                    if (durEl && a.formatted_duration) durEl.innerHTML = `<i class="bi bi-hourglass-split me-1"></i>${escapeHtml(a.formatted_duration)}`;
                    // Refresh the edit button's data-* so a subsequent edit shows fresh values
                    const editBtn = document.querySelector(`.btn-edit-affiliation[data-affiliation-id="${a.id}"]`);
                    if (editBtn) {
                        editBtn.dataset.clubName = a.club_name;
                        editBtn.dataset.startDate = a.start_date || '';
                        editBtn.dataset.endDate = a.end_date || '';
                        editBtn.dataset.location = a.location || '';
                        editBtn.dataset.description = a.description || '';
                        editBtn.dataset.coaches = a.coaches || '';
                    }
                    bsModal.hide(document.getElementById('editAffiliationModal'));
                    showAlert(res.message, 'success');
                    btn.disabled = false;
                } else if (res.success) {
                    showAlert(res.message, 'success'); setTimeout(() => location.reload(), 800);
                } else { showAlert(res.message || '{{ __("member.partials_affiliations_enhanced_js_error_updating_affiliation") }}', 'danger'); btn.disabled = false; }
            }).catch(() => { showAlert('{{ __("member.partials_affiliations_enhanced_js_error_generic") }}', 'danger'); btn.disabled = false; });
    });

    // ── Add Skill — submit ────────────────────────────────────────────────────
    document.getElementById('addSkillForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const affiliationId = document.getElementById('addSkillAffiliationId').value;
        const mid = document.getElementById('addSkillMemberId').value;
        const btn = this.querySelector('[type=submit]');
        const form = this;
        // activity_name / activity_id are bound by the modal's Alpine state — picking a
        // club activity sets the id, typing free text leaves it blank.
        btn.disabled = true;
        affFetch(`/member/${mid}/affiliations/${affiliationId}/skills`, 'POST', formToObject(this))
            .then(res => {
                if (res.success && res.skill) {
                    const list = document.getElementById(`skills-list-${affiliationId}`);
                    if (list) {
                        const node = buildSkillNode(res.skill, affiliationId);
                        list.appendChild(node);
                        list.style.display = '';
                        initTooltip(node.querySelector('.skill-badge'));
                        const countEl = document.getElementById(`skills-count-${affiliationId}`);
                        if (countEl) countEl.textContent = list.querySelectorAll('[id^="skill-"]').length;
                        const item = document.getElementById(`affiliation-${affiliationId}`);
                        if (item) {
                            const names = Array.from(list.querySelectorAll('.skill-badge')).map(b => b.textContent.trim().split('\n')[0]);
                            item.dataset.skills = names.join(',');
                        }
                        form.reset();
                        bsModal.hide(document.getElementById('addSkillModal'));
                        showAlert(res.message, 'success');
                        btn.disabled = false;
                    } else { showAlert(res.message, 'success'); setTimeout(() => location.reload(), 800); }
                } else if (res.success) {
                    showAlert(res.message, 'success'); setTimeout(() => location.reload(), 800);
                } else { showAlert(res.message || '{{ __("member.partials_affiliations_enhanced_js_error_adding_skill") }}', 'danger'); btn.disabled = false; }
            }).catch(() => { showAlert('{{ __("member.partials_affiliations_enhanced_js_error_generic") }}', 'danger'); btn.disabled = false; });
    });

    // ── Add Media — submit ────────────────────────────────────────────────────
    document.getElementById('addMediaForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const affiliationId = document.getElementById('addMediaAffiliationId').value;
        const mid = document.getElementById('addMediaMemberId').value;
        const btn = this.querySelector('[type=submit]');
        const form = this;
        btn.disabled = true;
        affFetch(`/member/${mid}/affiliations/${affiliationId}/media`, 'POST', formToObject(this))
            .then(res => {
                if (res.success && res.media) {
                    const list = document.getElementById(`media-list-${affiliationId}`);
                    if (list) {
                        list.appendChild(buildMediaNode(res.media, affiliationId));
                        list.style.display = '';
                        const countEl = document.getElementById(`media-count-${affiliationId}`);
                        const countWrap = document.getElementById(`media-count-wrap-${affiliationId}`);
                        if (countEl) countEl.textContent = list.querySelectorAll('[id^="media-"]').length;
                        if (countWrap) countWrap.style.display = '';
                        form.reset();
                        bsModal.hide(document.getElementById('addMediaModal'));
                        showAlert(res.message, 'success');
                        btn.disabled = false;
                    } else { showAlert(res.message, 'success'); setTimeout(() => location.reload(), 800); }
                } else if (res.success) {
                    showAlert(res.message, 'success'); setTimeout(() => location.reload(), 800);
                } else { showAlert(res.message || '{{ __("member.partials_affiliations_enhanced_js_error_adding_media") }}', 'danger'); btn.disabled = false; }
            }).catch(() => { showAlert('{{ __("member.partials_affiliations_enhanced_js_error_generic") }}', 'danger'); btn.disabled = false; });
    });

    // ── Delegated click handling for dynamic + existing cards ─────────────────
    document.addEventListener('click', async function(e) {
        const editBtn = e.target.closest('.btn-edit-affiliation');
        if (editBtn) { populateEditModal(editBtn); return; }

        const addSkillBtn = e.target.closest('.btn-add-skill');
        if (addSkillBtn) {
            // The modal owns its own state + activity loading now — just tell it which
            // affiliation it is opening for (see the add-skill:open contract above).
            window.dispatchEvent(new CustomEvent('add-skill:open', { detail: {
                affiliationId: addSkillBtn.dataset.affiliationId,
                memberId: addSkillBtn.dataset.memberId,
                clubName: document.getElementById('affiliation-name-' + addSkillBtn.dataset.affiliationId)?.textContent?.trim() || '',
            }}));
            return;
        }

        const addMediaBtn = e.target.closest('.btn-add-media');
        if (addMediaBtn) {
            document.getElementById('addMediaAffiliationId').value = addMediaBtn.dataset.affiliationId;
            document.getElementById('addMediaMemberId').value = addMediaBtn.dataset.memberId;
            return;
        }

        const delAffBtn = e.target.closest('.btn-delete-affiliation');
        if (delAffBtn) {
            const name = delAffBtn.dataset.clubName;
            const affiliationId = delAffBtn.dataset.affiliationId;
            const mid = delAffBtn.dataset.memberId;
            const ok = await window.confirmAction({ title: '{{ __("member.partials_affiliations_enhanced_js_delete_affiliation_title") }}', message: '{{ __("member.partials_affiliations_enhanced_js_delete_affiliation_confirm") }}'.replace(':name', name), type: 'danger', confirmText: '{{ __("shared.delete") }}' });
            if (!ok) return;
            affFetch(`/member/${mid}/affiliations/${affiliationId}`, 'DELETE')
                .then(res => {
                    if (res.success) {
                        document.getElementById(`affiliation-${affiliationId}`)?.remove();
                        const timeline = document.getElementById('affiliationsTimeline');
                        if (timeline && timeline.querySelectorAll('.timeline-item-enhanced').length === 0) {
                            setTimeout(() => location.reload(), 800);
                        }
                        showAlert(res.message, 'success');
                    } else { showAlert(res.message || '{{ __("member.partials_affiliations_enhanced_js_error_deleting_affiliation") }}', 'danger'); }
                }).catch(() => showAlert('{{ __("member.partials_affiliations_enhanced_js_error_generic") }}', 'danger'));
            return;
        }

        const delSkillBtn = e.target.closest('.btn-delete-skill');
        if (delSkillBtn) {
            e.stopPropagation();
            const ok = await window.confirmAction({ title: '{{ __("member.partials_affiliations_enhanced_js_remove_skill_title") }}', message: '{{ __("member.partials_affiliations_enhanced_js_remove_skill_confirm") }}', type: 'danger', confirmText: '{{ __("member.partials_affiliations_enhanced_remove") }}' });
            if (!ok) return;
            const skillId = delSkillBtn.dataset.skillId;
            const mid = delSkillBtn.dataset.memberId;
            const affiliationId = delSkillBtn.dataset.affiliationId;
            affFetch(`/member/${mid}/affiliations/${affiliationId}/skills/${skillId}`, 'DELETE')
                .then(res => {
                    if (res.success) {
                        document.getElementById(`skill-${skillId}`)?.remove();
                        const list = document.getElementById(`skills-list-${affiliationId}`);
                        const countEl = document.getElementById(`skills-count-${affiliationId}`);
                        const remaining = list ? list.querySelectorAll('[id^="skill-"]').length : 0;
                        if (countEl) countEl.textContent = remaining;
                        if (list && remaining === 0) list.style.display = 'none';
                        showAlert(res.message, 'success');
                    } else { showAlert(res.message || '{{ __("member.partials_affiliations_enhanced_js_error_removing_skill") }}', 'danger'); }
                }).catch(() => showAlert('{{ __("member.partials_affiliations_enhanced_js_error_generic") }}', 'danger'));
            return;
        }

        const delMediaBtn = e.target.closest('.btn-delete-media');
        if (delMediaBtn) {
            e.stopPropagation();
            const ok = await window.confirmAction({ title: '{{ __("member.partials_affiliations_enhanced_js_remove_media_title") }}', message: '{{ __("member.partials_affiliations_enhanced_js_remove_media_confirm") }}', type: 'danger', confirmText: '{{ __("member.partials_affiliations_enhanced_remove") }}' });
            if (!ok) return;
            const mediaId = delMediaBtn.dataset.mediaId;
            const mid = delMediaBtn.dataset.memberId;
            const affiliationId = delMediaBtn.dataset.affiliationId;
            affFetch(`/member/${mid}/affiliations/${affiliationId}/media/${mediaId}`, 'DELETE')
                .then(res => {
                    if (res.success) {
                        document.getElementById(`media-${mediaId}`)?.remove();
                        const list = document.getElementById(`media-list-${affiliationId}`);
                        const countEl = document.getElementById(`media-count-${affiliationId}`);
                        const countWrap = document.getElementById(`media-count-wrap-${affiliationId}`);
                        const remaining = list ? list.querySelectorAll('[id^="media-"]').length : 0;
                        if (countEl) countEl.textContent = remaining;
                        if (remaining === 0) { if (list) list.style.display = 'none'; if (countWrap) countWrap.style.display = 'none'; }
                        showAlert(res.message, 'success');
                    } else { showAlert(res.message || '{{ __("member.partials_affiliations_enhanced_js_error_removing_media") }}', 'danger'); }
                }).catch(() => showAlert('{{ __("member.partials_affiliations_enhanced_js_error_generic") }}', 'danger'));
            return;
        }
    });

    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Star Rating Functionality
    document.querySelectorAll('.star-rating').forEach(ratingContainer => {
        const stars = ratingContainer.querySelectorAll('.star-input');
        const ratingInput = ratingContainer.parentElement.querySelector('input[name="rating"]');

        stars.forEach((star, index) => {
            star.addEventListener('click', function() {
                const value = this.getAttribute('data-value');
                ratingInput.value = value;

                // Update star display
                stars.forEach((s, i) => {
                    if (i < value) {
                        s.classList.remove('bi-star');
                        s.classList.add('bi-star-fill');
                    } else {
                        s.classList.remove('bi-star-fill');
                        s.classList.add('bi-star');
                    }
                });
            });

            star.addEventListener('mouseenter', function() {
                const value = this.getAttribute('data-value');
                stars.forEach((s, i) => {
                    if (i < value) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
        });

        ratingContainer.addEventListener('mouseleave', function() {
            stars.forEach(s => s.classList.remove('active'));
        });
    });

    // Review Form Submission
    document.querySelectorAll('.instructor-review-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const instructorId = this.getAttribute('data-instructor-id');
            const reviewId = this.getAttribute('data-review-id');
            const formData = new FormData(this);
            const data = {
                rating: formData.get('rating'),
                comment: formData.get('comment')
            };

            // Validate rating
            if (!data.rating || data.rating == 0) {
                window.showToast('error', '{{ __("member.partials_affiliations_enhanced_js_select_rating") }}');
                return;
            }

            const url = reviewId
                ? `/instructor/reviews/${reviewId}`
                : `/instructor/${instructorId}/reviews`;

            const method = reviewId ? 'PUT' : 'POST';

            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success && result.review) {
                    patchInstructorReview(form, instructorId, result.review);
                    showAlert(result.message, 'success');
                } else if (result.success) {
                    showAlert(result.message, 'success');
                    setTimeout(() => { window.location.reload(); }, 1000);
                } else {
                    showAlert(result.message || '{{ __("member.partials_affiliations_enhanced_js_error_submitting_review") }}', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('{{ __("member.partials_affiliations_enhanced_js_error_submitting_review_retry") }}', 'danger');
            });
        });
    });

    function starsHtml(rating) {
        let html = '';
        for (let i = 1; i <= 5; i++) {
            html += `<i class="bi bi-star${i <= rating ? '-fill' : ''} text-warning"></i>`;
        }
        return html;
    }

    function patchInstructorReview(form, instructorId, review) {
        // Mark form as editing the now-existing review (subsequent submits become PUT)
        form.setAttribute('data-review-id', review.id);
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.innerHTML = `<i class="bi bi-pencil me-1"></i>{{ __('member.partials_affiliations_enhanced_update_review') }}`;

        const list = document.getElementById(`reviewsList_${instructorId}`);
        if (list) {
            let row = document.getElementById(`review-row-${review.id}`);
            const reviewerName = (review.reviewer && (review.reviewer.full_name || review.reviewer.name)) || 'You';
            const commentHtml = review.comment ? `<p class="mb-0 text-sm text-muted-foreground">${escapeHtml(review.comment)}</p>` : '';
            const cardHtml = `
                <div class="card-body p-3">
                    <div class="flex items-start mb-2">
                        <div class="grow">
                            <div class="font-semibold text-sm">${escapeHtml(reviewerName)}</div>
                            <div class="stars-display text-sm">${starsHtml(review.rating)}</div>
                        </div>
                        <small class="text-muted-foreground">{{ __('member.partials_affiliations_enhanced_js_updated_just_now') }}</small>
                    </div>
                    ${commentHtml}
                </div>`;
            if (row) {
                row.innerHTML = cardHtml;
            } else {
                const wrapper = document.createElement('div');
                wrapper.innerHTML = `<div class="card mb-2" id="review-row-${review.id}">${cardHtml}</div>`;
                row = wrapper.firstElementChild;
                list.prepend(row);
            }
        }

        // Recompute and patch the average rating display for this instructor
        const ratings = list ? Array.from(list.querySelectorAll('.stars-display')).map(d => d.querySelectorAll('.bi-star-fill').length) : [];
        const count = ratings.length;
        const avg = count ? (ratings.reduce((a, b) => a + b, 0) / count) : 0;
        const headerStars = document.getElementById(`avgStars_${instructorId}`);
        if (headerStars) headerStars.innerHTML = starsHtml(Math.round(avg));
        const headerMeta = document.getElementById(`avgMeta_${instructorId}`);
        if (headerMeta) headerMeta.textContent = `(${avg.toFixed(1)} / ${count} ${count === 1 ? '{{ __("member.partials_affiliations_enhanced_review_singular") }}' : '{{ __("member.partials_affiliations_enhanced_review_plural") }}'})`;
    }

    function showAlert(message, type) {
        // Route through the global toast — never render an inline alert on the page.
        window.showToast(type === 'danger' ? 'error' : type, message);
    }

    if (skillFilter) {
        skillFilter.addEventListener('change', function() {
            const selectedSkill = this.value;

            timelineItems.forEach(item => {
                const itemSkills = item.getAttribute('data-skills');

                if (selectedSkill === 'all') {
                    item.classList.remove('filtered-out');
                    item.style.display = '';
                } else {
                    if (itemSkills && itemSkills.includes(selectedSkill)) {
                        item.classList.remove('filtered-out');
                        item.style.display = '';
                    } else {
                        item.classList.add('filtered-out');
                        setTimeout(() => {
                            item.style.display = 'none';
                        }, 300);
                    }
                }
            });
        });
    }

    if (resetButton) {
        resetButton.addEventListener('click', function() {
            if (skillFilter) {
                skillFilter.value = 'all';
                skillFilter.dispatchEvent(new Event('change'));
            }
        });
    }
});
</script>
