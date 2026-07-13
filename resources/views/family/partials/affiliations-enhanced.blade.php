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

        return implode(' ', $parts) ?: 'Same day';
    }
    }
@endphp

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <!-- Header with Filter -->
        <div class="flex justify-between items-center mb-4">
            <div>
                <h5 class="font-bold mb-1"><i class="bi bi-diagram-3 me-2"></i>{{ __('member.partials_affiliations_enhanced_title') }}</h5>
                <p class="text-muted-foreground text-sm mb-0">{{ __('member.partials_affiliations_enhanced_subtitle') }}</p>
            </div>
            <div class="flex gap-2">
                <select class="form-select form-select-sm" id="skillFilter" style="width: 200px;">
                    <option value="all">{{ __('member.partials_affiliations_enhanced_all_skills') }}</option>
                    @foreach($allSkills ?? [] as $skill)
                        <option value="{{ $skill }}">{{ $skill }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline-secondary" id="resetFilters">
                    <i class="bi bi-arrow-clockwise"></i> {{ __('member.partials_affiliations_enhanced_reset') }}
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
                            <h3 class="font-bold mb-1">{{ floor($totalMembershipDuration / 12) }}{{ __('member.partials_affiliations_enhanced_years_abbr') }} {{ $totalMembershipDuration % 12 }}{{ __('member.partials_affiliations_enhanced_months_abbr') }}</h3>
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
                            <div class="timeline-enhanced">
                                @foreach($clubAffiliations as $index => $affiliation)
                                    @php
                                        $ageAtStart = calculateAgeAtDate($user->birthdate, $affiliation->start_date);
                                        $ageAtEnd = $affiliation->end_date ? calculateAgeAtDate($user->birthdate, $affiliation->end_date) : null;
                                        $isOngoing = !$affiliation->end_date;

                                        // Get all skills for this affiliation
                                        $affiliationSkills = $affiliation->skillAcquisitions ?? collect();
                                        $skillNames = $affiliationSkills->pluck('skill_name')->unique()->implode(',');
                                    @endphp

                                    <div class="timeline-item-enhanced mb-4" data-affiliation-id="{{ $affiliation->id }}" data-skills="{{ $skillNames }}">
                                        <!-- Timeline Marker -->
                                        <div class="timeline-marker-enhanced {{ $isOngoing ? 'pulse' : '' }}"></div>

                                        <!-- Affiliation Card -->
                                        <div class="affiliation-card-enhanced card border-0 shadow-sm">
                                            <!-- Card Header with Gradient -->
                                            <div class="card-header border-0 p-3" style="background: linear-gradient(135deg, {{ $index % 4 == 0 ? '#667eea 0%, #764ba2' : ($index % 4 == 1 ? '#f093fb 0%, #f5576c' : ($index % 4 == 2 ? '#4facfe 0%, #00f2fe' : '#fa709a 0%, #fee140')) }} 100%);">
                                                <div class="flex items-center">
                                                    @if($affiliation->logo)
                                                        <img src="{{ asset('storage/' . $affiliation->logo) }}" alt="{{ $affiliation->club_name }}" class="rounded-full me-3" style="width: 50px; height: 50px; object-fit: cover; border: 3px solid white;">
                                                    @else
                                                        <div class="rounded-full bg-white flex items-center justify-center me-3" style="width: 50px; height: 50px;">
                                                            <i class="bi bi-building" style="font-size: 1.5rem; color: #667eea;"></i>
                                                        </div>
                                                    @endif
                                                    <div class="grow text-white">
                                                        <h5 class="mb-1 font-bold">{{ $affiliation->club_name }}</h5>
                                                        <div class="flex gap-3 flex-wrap">
                                                            @if($affiliation->start_date)
                                                                <small class="opacity-90">
                                                                    <i class="bi bi-calendar-event me-1"></i>{{ $affiliation->start_date->format('M Y') }} - {{ $isOngoing ? __('member.partials_affiliations_enhanced_present') : ($affiliation->end_date ? $affiliation->end_date->format('M Y') : __('member.partials_affiliations_enhanced_na')) }}
                                                                </small>
                                                            @endif
                                                            @if($affiliation->formatted_duration)
                                                                <small class="opacity-90">
                                                                    <i class="bi bi-hourglass-split me-1"></i>{{ $affiliation->formatted_duration }}
                                                                </small>
                                                            @endif
                                                            @if($ageAtStart)
                                                                <small class="opacity-90">
                                                                    <i class="bi bi-person me-1"></i>{{ __('member.partials_affiliations_enhanced_age_label') }} {{ $ageAtStart }}{{ $ageAtEnd && $ageAtEnd != $ageAtStart ? __('member.partials_affiliations_enhanced_age_to', ['age' => $ageAtEnd]) : '' }}
                                                                </small>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @if($isOngoing)
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>{{ __('member.partials_affiliations_enhanced_active') }}
                                                        </span>
                                                    @endif
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
                                                @if($affiliationSkills->count() > 0)
                                                    <div class="mb-3">
                                                        <h6 class="font-bold mb-2">
                                                            <i class="bi bi-star-fill me-2 text-warning"></i>{{ __('member.partials_affiliations_enhanced_skills_acquired') }} ({{ $affiliationSkills->count() }})
                                                        </h6>
                                                        <div class="flex gap-2 flex-wrap">
                                                            @foreach($affiliationSkills as $skill)
                                                                <span class="badge skill-badge bg-{{ $skill->proficiency_level == 'expert' ? 'danger' : ($skill->proficiency_level == 'advanced' ? 'warning' : ($skill->proficiency_level == 'intermediate' ? 'info' : 'secondary')) }}"
                                                                      data-bs-toggle="tooltip"
                                                                      data-bs-placement="top"
                                                                      data-bs-html="true"
                                                                      title="<strong>{{ $skill->skill_name }}</strong><br>
                                                                             {{ __('member.partials_affiliations_enhanced_proficiency_label') }} {{ ucfirst($skill->proficiency_level) }}<br>
                                                                             {{ __('member.partials_affiliations_enhanced_duration_label') }} {{ $skill->formatted_duration }}<br>
                                                                             @if($skill->instructor){{ __('member.partials_affiliations_enhanced_instructor_label') }} {{ $skill->instructor->user->full_name ?? __('member.partials_affiliations_enhanced_unknown') }}<br>@endif
                                                                             @if($skill->start_date){{ __('member.partials_affiliations_enhanced_started_label') }} {{ $skill->start_date->format('M Y') }}@endif">
                                                                    <i class="bi bi-star-fill me-1"></i>{{ $skill->skill_name }}
                                                                    <span class="badge bg-white text-dark ms-1" style="font-size: 0.65rem;">{{ ucfirst($skill->proficiency_level) }}</span>
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif

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
                                                    <div class="mb-2">
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
                                                                            <div class="text-muted-foreground" style="font-size: 0.7rem;">{{ $instructor->role ?? __('member.partials_affiliations_enhanced_instructor_role_default') }}</div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Instructor Modals -->
                            @php
                                $allInstructors = collect();
                                foreach($clubAffiliations as $aff) {
                                    $affInstructors = $aff->skillAcquisitions->pluck('instructor')->filter()->unique('id');
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
                                                    <p class="text-muted-foreground mb-2">{{ $instructor->role ?? __('member.partials_affiliations_enhanced_instructor_role_default') }}</p>

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

                                                @if($instructor->user->mobile)
                                                    <div class="mb-3 text-start">
                                                        <small class="text-muted-foreground">
                                                            <i class="bi bi-phone me-1"></i>{{ $instructor->user->mobile }}
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
                                                                    <textarea name="comment" class="form-control form-control-sm" rows="3" placeholder="{{ __('member.partials_affiliations_enhanced_share_experience_placeholder') }}">{{ $userReview->comment ?? '' }}</textarea>
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
                                                                            {{ $review->wasUpdated() ? __('member.partials_affiliations_enhanced_updated_prefix') : '' }}{{ $review->wasUpdated() ? $review->updated_at->diffForHumans() : $review->reviewed_at->diffForHumans() }}
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
                                                                $durationText = 'N/A';
                                                                if ($subscription->start_date && $subscription->end_date) {
                                                                    $duration = $subscription->start_date->diff($subscription->end_date);
                                                                    $durationParts = [];
                                                                    if ($duration->y > 0) $durationParts[] = $duration->y . ' year' . ($duration->y > 1 ? 's' : '');
                                                                    if ($duration->m > 0) $durationParts[] = $duration->m . ' month' . ($duration->m > 1 ? 's' : '');
                                                                    if ($duration->d > 0) $durationParts[] = $duration->d . ' day' . ($duration->d > 1 ? 's' : '');
                                                                    $durationText = implode(' ', $durationParts) ?: 'Same day';
                                                                }
                                                            @endphp
                                                            <small class="text-muted-foreground">
                                                                <i class="bi bi-hourglass-split me-1"></i>{{ __('member.partials_affiliations_enhanced_duration_label') }} {{ $durationText }}
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
                                                                            {{ $subscription->start_date ? $subscription->start_date->format('M d, Y') : __('member.partials_affiliations_enhanced_na') }} - {{ $subscription->end_date ? $subscription->end_date->format('M d, Y') : __('member.partials_affiliations_enhanced_na') }} {{ __('member.partials_affiliations_enhanced_current_parenthetical') }}
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
                                                                                    <small class="text-muted-foreground">({{ abs($gap) }} {{ __('member.partials_affiliations_enhanced_months') }} {{ $subscription->start_date->gt($otherSub->start_date) ? __('member.partials_affiliations_enhanced_before') : __('member.partials_affiliations_enhanced_after') }} {{ __('member.partials_affiliations_enhanced_current_lower') }})</small>
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
                                                                    {{ __('member.partials_affiliations_enhanced_payment_label') }} {{ ucfirst($subscription->payment_status) }}
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
                <p class="text-muted-foreground mb-0">{{ __('member.partials_affiliations_enhanced_no_affiliations_desc') }}</p>
            </div>
        @endif
    </div>
</div>

{{-- Styles moved to app.css (Phase 6) --}}
<style>
/* Page-specific modal overrides (kept inline to avoid global impact) */
.modal-dialog { max-width: 600px; }
.modal-body { max-height: 60vh; overflow-y: auto; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const skillFilter = document.getElementById('skillFilter');
    const resetButton = document.getElementById('resetFilters');
    const timelineItems = document.querySelectorAll('.timeline-item-enhanced');

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
                window.showToast('error', '{{ __("member.partials_affiliations_enhanced_please_select_rating") }}');
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
                    patchInstructorReview(this, instructorId, result.review);
                    showAlert(result.message, 'success');
                } else if (result.success) {
                    showAlert(result.message, 'success');
                    setTimeout(() => { window.location.reload(); }, 1000);
                } else {
                    showAlert(result.message || '{{ __("member.partials_affiliations_enhanced_error_submitting_review") }}', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('{{ __("member.partials_affiliations_enhanced_error_submitting_review_retry") }}', 'danger');
            });
        });
    });

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
    }

    function starsHtml(rating) {
        let html = '';
        for (let i = 1; i <= 5; i++) {
            html += `<i class="bi bi-star${i <= rating ? '-fill' : ''} text-warning"></i>`;
        }
        return html;
    }

    function patchInstructorReview(form, instructorId, review) {
        form.setAttribute('data-review-id', review.id);
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.innerHTML = `<i class="bi bi-pencil me-1"></i>{{ __("member.partials_affiliations_enhanced_update_review") }}`;

        const list = document.getElementById(`reviewsList_${instructorId}`);
        if (list) {
            let row = document.getElementById(`review-row-${review.id}`);
            const reviewerName = (review.reviewer && (review.reviewer.full_name || review.reviewer.name)) || '{{ __("member.partials_affiliations_enhanced_reviewer_you") }}';
            const commentHtml = review.comment ? `<p class="mb-0 text-sm text-muted-foreground">${escapeHtml(review.comment)}</p>` : '';
            const cardHtml = `
                <div class="card-body p-3">
                    <div class="flex items-start mb-2">
                        <div class="grow">
                            <div class="font-semibold text-sm">${escapeHtml(reviewerName)}</div>
                            <div class="stars-display text-sm">${starsHtml(review.rating)}</div>
                        </div>
                        <small class="text-muted-foreground">{{ __("member.partials_affiliations_enhanced_updated_just_now") }}</small>
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
