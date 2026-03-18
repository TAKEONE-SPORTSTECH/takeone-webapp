@php
    // Helper function to calculate age at a specific date with detailed format
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
@endphp

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <!-- Header with Filter -->
        <div class="flex justify-between items-center mb-4">
            <div>
                <h5 class="font-bold mb-1"><i class="bi bi-diagram-3 mr-2"></i>Club Affiliations & Skills Journey</h5>
                <p class="text-muted-foreground text-sm mb-0">Complete history of club memberships, skills acquired, and instructors</p>
            </div>
            <div class="flex gap-2">
                <select class="form-select form-select-sm" id="skillFilter" style="width: 200px;">
                    <option value="all">All Skills</option>
                    @foreach($allSkills ?? [] as $skill)
                        <option value="{{ $skill }}">{{ $skill }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline-secondary" id="resetFilters">
                    <i class="bi bi-arrow-clockwise"></i> Reset
                </button>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAffiliationModal">
                    <i class="bi bi-plus-circle mr-1"></i> Add Affiliation
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
                            <small class="opacity-75">Total Clubs</small>
                        </div>
                    </div>
                </div>
                <div class="col-span-12 md:col-span-3">
                    <div class="card shadow-sm h-full" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">
                        <div class="card-body text-center text-white p-3">
                            <i class="bi bi-star-fill text-5xl mb-2"></i>
                            <h3 class="font-bold mb-1">{{ $distinctSkills }}</h3>
                            <small class="opacity-75">Unique Skills</small>
                        </div>
                    </div>
                </div>
                <div class="col-span-12 md:col-span-3">
                    <div class="card shadow-sm h-full" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                        <div class="card-body text-center text-white p-3">
                            <i class="bi bi-calendar-check text-5xl mb-2"></i>
                            <h3 class="font-bold mb-1">{{ floor($totalMembershipDuration / 12) }}y {{ $totalMembershipDuration % 12 }}m</h3>
                            <small class="opacity-75">Total Training</small>
                        </div>
                    </div>
                </div>
                <div class="col-span-12 md:col-span-3">
                    <div class="card shadow-sm h-full" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); border: none;">
                        <div class="card-body text-center text-white p-3">
                            <i class="bi bi-people-fill text-5xl mb-2"></i>
                            <h3 class="font-bold mb-1">{{ $totalInstructors ?? 0 }}</h3>
                            <small class="opacity-75">Instructors</small>
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
                                <i class="bi bi-clock-history mr-2"></i>Membership Timeline
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
                                                        <img src="{{ $logoUrl }}" alt="{{ $affiliation->club_name }}" class="rounded-full mr-3" style="width: 50px; height: 50px; object-fit: cover; border: 3px solid white;">
                                                    @else
                                                        <div class="rounded-full bg-white flex items-center justify-center mr-3" style="width: 50px; height: 50px;">
                                                            <i class="bi bi-building" style="font-size: 1.5rem; color: #667eea;"></i>
                                                        </div>
                                                    @endif
                                                    <div class="grow text-white">
                                                        <h5 class="mb-1 font-bold">{{ $affiliation->club_name }}</h5>
                                                        <div class="flex gap-3 flex-wrap">
                                                            @if($affiliation->start_date)
                                                                <small class="opacity-90">
                                                                    <i class="bi bi-calendar-event mr-1"></i>{{ $affiliation->start_date->format('M Y') }} - {{ $isOngoing ? 'Present' : ($affiliation->end_date ? $affiliation->end_date->format('M Y') : 'N/A') }}
                                                                </small>
                                                            @endif
                                                            @if($affiliation->formatted_duration)
                                                                <small class="opacity-90">
                                                                    <i class="bi bi-hourglass-split mr-1"></i>{{ $affiliation->formatted_duration }}
                                                                </small>
                                                            @endif
                                                            @if($ageAtStart)
                                                                <small class="opacity-90">
                                                                    <i class="bi bi-person mr-1"></i>Age: {{ $ageAtStart }}{{ $ageAtEnd && $ageAtEnd != $ageAtStart ? " to $ageAtEnd" : '' }}
                                                                </small>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @if($isOngoing)
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-circle-fill mr-1" style="font-size: 0.5rem;"></i>Active
                                                        </span>
                                                    @endif
                                                    <div class="flex gap-1 ml-2">
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
                                                                title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button"
                                                                class="btn btn-sm btn-delete-affiliation"
                                                                style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 2px 8px;"
                                                                data-affiliation-id="{{ $affiliation->id }}"
                                                                data-club-name="{{ $affiliation->club_name }}"
                                                                data-member-id="{{ $user->id }}"
                                                                title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Card Body -->
                                            <div class="card-body p-3">
                                                @if($affiliation->location)
                                                    <div class="mb-3">
                                                        <i class="bi bi-geo-alt text-primary mr-2"></i>
                                                        <span class="text-muted-foreground">{{ $affiliation->location }}</span>
                                                    </div>
                                                @endif

                                                <!-- Skills Acquired as Badges -->
                                                <div class="mb-3">
                                                    <div class="flex justify-between items-center mb-2">
                                                        <h6 class="font-bold mb-0">
                                                            <i class="bi bi-star-fill mr-2 text-warning"></i>Skills Acquired ({{ $affiliationSkills->count() }})
                                                        </h6>
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-warning btn-add-skill"
                                                                data-affiliation-id="{{ $affiliation->id }}"
                                                                data-member-id="{{ $user->id }}"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#addSkillModal">
                                                            <i class="bi bi-plus-circle mr-1"></i> Add Skill
                                                        </button>
                                                    </div>
                                                    @if($affiliationSkills->count() > 0)
                                                        <div class="flex gap-2 flex-wrap">
                                                            @foreach($affiliationSkills as $skill)
                                                                <div class="d-inline-flex align-items-center gap-1">
                                                                    <span class="badge skill-badge bg-{{ $skill->proficiency_level == 'expert' ? 'danger' : ($skill->proficiency_level == 'advanced' ? 'warning' : ($skill->proficiency_level == 'intermediate' ? 'info' : 'secondary')) }}"
                                                                          data-bs-toggle="tooltip"
                                                                          data-bs-placement="top"
                                                                          data-bs-html="true"
                                                                          title="<strong>{{ $skill->skill_name }}</strong><br>
                                                                                 Proficiency: {{ ucfirst($skill->proficiency_level) }}<br>
                                                                                 Duration: {{ $skill->formatted_duration }}<br>
                                                                                 @if($skill->instructor)Instructor: {{ $skill->instructor->user->full_name ?? 'Unknown' }}<br>@endif
                                                                                 @if($skill->start_date)Started: {{ $skill->start_date->format('M Y') }}@endif">
                                                                        <i class="bi bi-star-fill mr-1"></i>{{ $skill->skill_name }}
                                                                        <span class="badge bg-white text-dark ml-1" style="font-size: 0.65rem;">{{ ucfirst($skill->proficiency_level) }}</span>
                                                                    </span>
                                                                    <button type="button"
                                                                            class="btn-delete-skill"
                                                                            style="background: none; border: none; color: #dc3545; padding: 0 2px; font-size: 0.8rem; line-height: 1;"
                                                                            data-skill-id="{{ $skill->id }}"
                                                                            data-member-id="{{ $user->id }}"
                                                                            data-affiliation-id="{{ $affiliation->id }}"
                                                                            title="Remove skill">
                                                                        <i class="bi bi-x-circle"></i>
                                                                    </button>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>

                                                <!-- Training Packages -->
                                                @if($affiliation->subscriptions && $affiliation->subscriptions->count() > 0)
                                                    <div class="mb-3">
                                                        <h6 class="font-bold mb-2">
                                                            <i class="bi bi-box-seam mr-2 text-primary"></i>Training Packages ({{ $affiliation->subscriptions->count() }})
                                                        </h6>
                                                        <div class="flex gap-2 flex-wrap">
                                                            @foreach($affiliation->subscriptions as $subIndex => $subscription)
                                                                @if($subscription->package)
                                                                    <button type="button" class="btn btn-sm btn-outline-primary package-card-btn"
                                                                            data-bs-toggle="modal"
                                                                            data-bs-target="#packageModal_{{ $affiliation->id }}_{{ $subscription->id }}">
                                                                        <i class="bi bi-box mr-1"></i>{{ $subscription->package->name }}
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
                                                            <i class="bi bi-people-fill mr-2 text-success"></i>Instructors ({{ $instructors->count() }})
                                                        </h6>
                                                        <div class="flex gap-2 flex-wrap">
                                                            @foreach($instructors as $instructor)
                                                                <div class="instructor-badge" role="button"
                                                                     data-bs-toggle="modal"
                                                                     data-bs-target="#instructorModal_{{ $instructor->id }}">
                                                                    <div class="flex items-center gap-2 p-2 bg-muted rounded">
                                                                        <div class="rounded-full bg-success text-white flex items-center justify-center" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                                            {{ strtoupper(substr($instructor->user->full_name ?? 'I', 0, 1)) }}
                                                                        </div>
                                                                        <div>
                                                                            <div class="font-semibold text-sm">{{ $instructor->user->full_name ?? 'Unknown' }}</div>
                                                                            <div class="text-muted-foreground" style="font-size: 0.7rem;">{{ $instructor->role ?? 'Instructor' }}</div>
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
                                                            <i class="bi bi-paperclip mr-2 text-info"></i>Media & Certificates
                                                            @if($affiliation->affiliationMedia->count() > 0)
                                                                ({{ $affiliation->affiliationMedia->count() }})
                                                            @endif
                                                        </h6>
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-info btn-add-media"
                                                                data-affiliation-id="{{ $affiliation->id }}"
                                                                data-member-id="{{ $user->id }}"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#addMediaModal">
                                                            <i class="bi bi-plus-circle mr-1"></i> Add Media
                                                        </button>
                                                    </div>
                                                    @if($affiliation->affiliationMedia->count() > 0)
                                                        <div class="flex gap-2 flex-wrap mt-2">
                                                            @foreach($affiliation->affiliationMedia as $media)
                                                                <div class="d-inline-flex align-items-center gap-1 border rounded px-2 py-1 bg-muted" style="font-size: 0.85rem;">
                                                                    <i class="bi {{ $media->icon_class }} text-info"></i>
                                                                    <a href="{{ $media->full_url }}" target="_blank" class="text-decoration-none text-dark">{{ $media->title }}</a>
                                                                    <button type="button"
                                                                            class="btn-delete-media"
                                                                            style="background: none; border: none; color: #dc3545; padding: 0 2px; font-size: 0.8rem; line-height: 1;"
                                                                            data-media-id="{{ $media->id }}"
                                                                            data-member-id="{{ $user->id }}"
                                                                            data-affiliation-id="{{ $affiliation->id }}"
                                                                            title="Remove">
                                                                        <i class="bi bi-x-circle"></i>
                                                                    </button>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif
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
                                                    <i class="bi bi-person-badge mr-2"></i>Instructor Profile
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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
                                                                {{ strtoupper(substr($instructor->user->full_name ?? 'I', 0, 1)) }}
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <!-- Name & Role -->
                                                    <h5 class="font-bold mb-1">{{ $instructor->user->full_name ?? 'Unknown Instructor' }}</h5>
                                                    <p class="text-muted-foreground mb-2">{{ $instructor->role ?? 'Instructor' }}</p>

                                                    <!-- Average Rating -->
                                                    @php
                                                        $avgRating = $instructor->reviews()->avg('rating') ?? 0;
                                                        $reviewCount = $instructor->reviews()->count();
                                                    @endphp
                                                    <div class="mb-3">
                                                        <div class="flex justify-center items-center gap-2">
                                                            <div class="stars-display">
                                                                @for($i = 1; $i <= 5; $i++)
                                                                    <i class="bi bi-star{{ $i <= round($avgRating) ? '-fill' : '' }} text-warning"></i>
                                                                @endfor
                                                            </div>
                                                            <span class="text-muted-foreground text-sm">({{ number_format($avgRating, 1) }} / {{ $reviewCount }} {{ $reviewCount == 1 ? 'review' : 'reviews' }})</span>
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
                                                                <small class="text-muted-foreground">Students</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-span-6">
                                                        <div class="card bg-muted border-0">
                                                            <div class="card-body p-2">
                                                                <div class="text-2xl mb-0 text-success">{{ $skillsTaught->count() }}</div>
                                                                <small class="text-muted-foreground">Skills</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Skills Taught -->
                                                @if($skillsTaught->count() > 0)
                                                    <div class="mb-3 text-left">
                                                        <label class="text-muted-foreground text-sm font-semibold mb-2">Specializes In:</label>
                                                        <div class="flex gap-1 flex-wrap">
                                                            @foreach($skillsTaught as $skill)
                                                                <span class="badge bg-success">{{ $skill }}</span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif

                                                <!-- Contact Info -->
                                                @if($instructor->user->email)
                                                    <div class="mb-2 text-left">
                                                        <small class="text-muted-foreground">
                                                            <i class="bi bi-envelope mr-1"></i>{{ $instructor->user->email }}
                                                        </small>
                                                    </div>
                                                @endif

                                                @if($instructor->user->mobile_formatted)
                                                    <div class="mb-3 text-left">
                                                        <small class="text-muted-foreground">
                                                            <i class="bi bi-phone mr-1"></i>{{ $instructor->user->mobile_formatted }}
                                                        </small>
                                                    </div>
                                                @endif

                                                <!-- Reviews Section -->
                                                <div class="mt-4">
                                                    <h6 class="font-bold mb-3">
                                                        <i class="bi bi-chat-left-text mr-2"></i>Reviews
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
                                                                    <label class="form-label text-sm font-semibold">Your Rating</label>
                                                                    <div class="star-rating" data-rating="{{ $userReview->rating ?? 0 }}">
                                                                        @for($i = 1; $i <= 5; $i++)
                                                                            <i class="bi bi-star{{ $userReview && $i <= $userReview->rating ? '-fill' : '' }} star-input" data-value="{{ $i }}"></i>
                                                                        @endfor
                                                                    </div>
                                                                    <input type="hidden" name="rating" value="{{ $userReview->rating ?? 0 }}" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label text-sm font-semibold">Your Review</label>
                                                                    <textarea name="comment" class="form-control form-control-sm" rows="3" placeholder="Share your experience...">{{ $userReview->comment ?? '' }}</textarea>
                                                                </div>
                                                                <button type="submit" class="btn btn-success btn-sm w-full">
                                                                    <i class="bi bi-{{ $userReview ? 'pencil' : 'plus-circle' }} mr-1"></i>
                                                                    {{ $userReview ? 'Update Review' : 'Submit Review' }}
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>

                                                    <!-- Reviews List -->
                                                    <div class="reviews-list" id="reviewsList_{{ $instructor->id }}">
                                                        @foreach($instructor->reviews()->with('reviewer')->latest()->get() as $review)
                                                            <div class="card mb-2">
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
                                                                            {{ $review->wasUpdated() ? 'Updated ' : '' }}{{ $review->wasUpdated() ? $review->updated_at->diffForHumans() : $review->reviewed_at->diffForHumans() }}
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
                                                    <i class="bi bi-person-lines-fill mr-1"></i>View Full Profile
                                                </a>
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
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
                                                            <i class="bi bi-box-seam mr-2"></i>{{ $subscription->package->name }}
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="text-muted-foreground text-sm font-semibold">Subscription Period</label>
                                                            <div>
                                                                <i class="bi bi-calendar-range mr-2 text-primary"></i>
                                                                {{ $subscription->start_date ? $subscription->start_date->format('M d, Y') : 'N/A' }} - {{ $subscription->end_date ? $subscription->end_date->format('M d, Y') : 'N/A' }}
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
                                                                <i class="bi bi-hourglass-split mr-1"></i>Duration: {{ $durationText }}
                                                            </small>
                                                        </div>

                                                        @if($subscription->package->description)
                                                            <div class="mb-3">
                                                                <label class="text-muted-foreground text-sm font-semibold">Description</label>
                                                                <p class="mb-0">{{ $subscription->package->description }}</p>
                                                            </div>
                                                        @endif

                                                        @if($subscription->package->price)
                                                            <div class="mb-3">
                                                                <label class="text-muted-foreground text-sm font-semibold">Price</label>
                                                                <div class="text-xl mb-0 text-success">
                                                                    <i class="bi bi-currency-dollar"></i>{{ number_format($subscription->package->price, 2) }}
                                                                </div>
                                                            </div>
                                                        @endif

                                                        @if($subscription->package->packageActivities && $subscription->package->packageActivities->count() > 0)
                                                            <div class="mb-3">
                                                                <label class="text-muted-foreground text-sm font-semibold">Activities & Skills Included</label>
                                                                <div class="list-group">
                                                                    @foreach($subscription->package->packageActivities as $pkgActivity)
                                                                        @if($pkgActivity->activity)
                                                                            <div class="list-group-item">
                                                                                <div class="flex items-start mb-2">
                                                                                    <i class="bi bi-check-circle-fill text-success mr-2 mt-1"></i>
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
                                                                                                <small class="text-muted-foreground block mb-1">Skills Practiced:</small>
                                                                                                <div class="flex gap-1 flex-wrap">
                                                                                                    @foreach($activitySkills as $actSkill)
                                                                                                        <span class="badge bg-{{ $actSkill->proficiency_level == 'expert' ? 'danger' : ($actSkill->proficiency_level == 'advanced' ? 'warning' : ($actSkill->proficiency_level == 'intermediate' ? 'info' : 'secondary')) }}" style="font-size: 0.7rem;">
                                                                                                            <i class="bi bi-star-fill mr-1"></i>{{ $actSkill->skill_name }}
                                                                                                        </span>
                                                                                                    @endforeach
                                                                                                </div>
                                                                                            </div>
                                                                                        @endif
                                                                                    </div>
                                                                                    @if($pkgActivity->instructor && $pkgActivity->instructor->user)
                                                                                        <div class="text-right">
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
                                                                    <i class="bi bi-arrow-repeat mr-1"></i>Other Subscriptions to This Package
                                                                </label>
                                                                <div class="alert alert-info mb-0" style="font-size: 0.85rem;">
                                                                    <div class="font-semibold mb-1">You subscribed to this package {{ $samePackageSubscriptions->count() + 1 }} times:</div>
                                                                    <ul class="mb-0 pl-3">
                                                                        <li class="text-primary font-semibold">
                                                                            {{ $subscription->start_date ? $subscription->start_date->format('M d, Y') : 'N/A' }} - {{ $subscription->end_date ? $subscription->end_date->format('M d, Y') : 'N/A' }} (Current)
                                                                        </li>
                                                                        @foreach($samePackageSubscriptions as $otherSub)
                                                                            <li>
                                                                                {{ $otherSub->start_date ? $otherSub->start_date->format('M d, Y') : 'N/A' }} - {{ $otherSub->end_date ? $otherSub->end_date->format('M d, Y') : 'N/A' }}
                                                                                @php
                                                                                    $gap = 0;
                                                                                    if ($subscription->start_date && $otherSub->start_date) {
                                                                                        $gap = $subscription->start_date->diffInMonths($otherSub->start_date);
                                                                                    }
                                                                                @endphp
                                                                                @if($gap > 0)
                                                                                    <small class="text-muted-foreground">({{ abs($gap) }} months {{ $subscription->start_date->gt($otherSub->start_date) ? 'before' : 'after' }} current)</small>
                                                                                @endif
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                        @endif

                                                        <div class="mb-0">
                                                            <label class="text-muted-foreground text-sm font-semibold">Status</label>
                                                            <div>
                                                                <span class="badge bg-{{ $subscription->status == 'active' ? 'success' : 'secondary' }}">
                                                                    {{ ucfirst($subscription->status) }}
                                                                </span>
                                                                <span class="badge bg-{{ $subscription->payment_status == 'paid' ? 'success' : 'warning' }} ml-2">
                                                                    Payment: {{ ucfirst($subscription->payment_status) }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
                <h5 class="text-muted-foreground mt-3 mb-2">No Affiliations Yet</h5>
                <p class="text-muted-foreground mb-3">Club affiliations and skills will appear here once added</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAffiliationModal">
                    <i class="bi bi-plus-circle mr-2"></i>Add Your First Affiliation
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
                <h5 class="modal-title text-white"><i class="bi bi-plus-circle mr-2"></i>Add Club Affiliation</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addAffiliationForm">
                @csrf
                <div class="modal-body">
                    <!-- Club source toggle -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Club</label>
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-primary" id="togglePlatformClub">
                                <i class="bi bi-building mr-1"></i> Select from Platform
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleExternalClub">
                                <i class="bi bi-pencil mr-1"></i> Enter Manually
                            </button>
                        </div>
                        <!-- Platform club selector -->
                        <div id="platformClubSection">
                            <select name="tenant_id" id="addTenantSelect" class="form-select">
                                <option value="">— Select a club on this platform —</option>
                                @foreach($allClubs ?? [] as $club)
                                    <option value="{{ $club->id }}" data-location="{{ $club->address }}">{{ $club->club_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <!-- External / free-text club -->
                        <div id="externalClubSection" style="display:none;">
                            <input type="text" name="club_name" id="addClubNameInput" class="form-control" placeholder="e.g. Elite Boxing Club (external)">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">End Date <small class="text-muted">(leave blank if ongoing)</small></label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Location</label>
                        <input type="text" name="location" id="addLocationInput" class="form-control" placeholder="e.g. Manama, Bahrain">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Coaches <small class="text-muted">(comma-separated)</small></label>
                        <input type="text" name="coaches" class="form-control" placeholder="e.g. Coach Ahmed, Master Ali">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief description of your time at this club..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle mr-1"></i>Save Affiliation</button>
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
                <h5 class="modal-title text-white"><i class="bi bi-pencil mr-2"></i>Edit Club Affiliation</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editAffiliationForm">
                @csrf
                <input type="hidden" id="editAffiliationId">
                <input type="hidden" id="editAffiliationMemberId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Club Name <span class="text-danger">*</span></label>
                        <input type="text" id="editClubName" name="club_name" class="form-control" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" id="editStartDate" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">End Date <small class="text-muted">(leave blank if ongoing)</small></label>
                            <input type="date" id="editEndDate" name="end_date" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Location</label>
                        <input type="text" id="editLocation" name="location" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Coaches <small class="text-muted">(comma-separated)</small></label>
                        <input type="text" id="editCoaches" name="coaches" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea id="editDescription" name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle mr-1"></i>Update Affiliation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Add Skill Modal ────────────────────────────────────────────────────── -->
<div class="modal fade" id="addSkillModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <h5 class="modal-title text-white"><i class="bi bi-star-fill mr-2"></i>Add Skill</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSkillForm">
                @csrf
                <input type="hidden" id="addSkillAffiliationId">
                <input type="hidden" id="addSkillMemberId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Skill Name <span class="text-danger">*</span></label>
                        <input type="text" name="skill_name" class="form-control" placeholder="e.g. Taekwondo, Boxing, Jiu-Jitsu" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Proficiency Level <span class="text-danger">*</span></label>
                        <select name="proficiency_level" class="form-select" required>
                            <option value="">Select level...</option>
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                            <option value="expert">Expert</option>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Start Date</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Duration (months)</label>
                            <input type="number" name="duration_months" class="form-control" min="1" placeholder="e.g. 12">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-white"><i class="bi bi-plus-circle mr-1"></i>Add Skill</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Add Media Modal ────────────────────────────────────────────────────── -->
<div class="modal fade" id="addMediaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <h5 class="modal-title text-white"><i class="bi bi-paperclip mr-2"></i>Add Media / Certificate</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addMediaForm">
                @csrf
                <input type="hidden" id="addMediaAffiliationId">
                <input type="hidden" id="addMediaMemberId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                        <select name="media_type" class="form-select" required>
                            <option value="">Select type...</option>
                            <option value="certificate">Certificate</option>
                            <option value="photo">Photo</option>
                            <option value="video">Video</option>
                            <option value="document">Document</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Black Belt Certificate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">URL / Link <span class="text-danger">*</span></label>
                        <input type="text" name="media_url" class="form-control" placeholder="https://..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Optional description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white"><i class="bi bi-plus-circle mr-1"></i>Add Media</button>
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
        const el = document.createElement('div');
        el.className = `alert alert-${type} alert-dismissible fade show`;
        el.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:300px;';
        el.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        document.body.appendChild(el);
        setTimeout(() => el.parentNode && el.remove(), 5000);
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

    // ── Add Affiliation ───────────────────────────────────────────────────────
    document.getElementById('addAffiliationForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('[type=submit]');
        btn.disabled = true;
        affFetch(`/member/${memberId}/affiliations`, 'POST', formToObject(this))
            .then(res => {
                if (res.success) { showAlert(res.message, 'success'); setTimeout(() => location.reload(), 800); }
                else { showAlert(res.message || 'Error saving affiliation', 'danger'); btn.disabled = false; }
            }).catch(() => { showAlert('An error occurred', 'danger'); btn.disabled = false; });
    });

    // ── Edit Affiliation — populate modal ─────────────────────────────────────
    document.querySelectorAll('.btn-edit-affiliation').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('editAffiliationId').value = this.dataset.affiliationId;
            document.getElementById('editAffiliationMemberId').value = this.dataset.memberId;
            document.getElementById('editClubName').value = this.dataset.clubName;
            document.getElementById('editStartDate').value = this.dataset.startDate;
            document.getElementById('editEndDate').value = this.dataset.endDate;
            document.getElementById('editLocation').value = this.dataset.location;
            document.getElementById('editDescription').value = this.dataset.description;
            document.getElementById('editCoaches').value = this.dataset.coaches;
        });
    });

    document.getElementById('editAffiliationForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const affiliationId = document.getElementById('editAffiliationId').value;
        const mid = document.getElementById('editAffiliationMemberId').value;
        const btn = this.querySelector('[type=submit]');
        btn.disabled = true;
        affFetch(`/member/${mid}/affiliations/${affiliationId}`, 'PUT', formToObject(this))
            .then(res => {
                if (res.success) { showAlert(res.message, 'success'); setTimeout(() => location.reload(), 800); }
                else { showAlert(res.message || 'Error updating affiliation', 'danger'); btn.disabled = false; }
            }).catch(() => { showAlert('An error occurred', 'danger'); btn.disabled = false; });
    });

    // ── Delete Affiliation ────────────────────────────────────────────────────
    document.querySelectorAll('.btn-delete-affiliation').forEach(btn => {
        btn.addEventListener('click', function() {
            const name = this.dataset.clubName;
            const affiliationId = this.dataset.affiliationId;
            const mid = this.dataset.memberId;
            if (!confirm(`Delete affiliation with "${name}"? This will also remove all skills and media linked to it.`)) return;
            affFetch(`/member/${mid}/affiliations/${affiliationId}`, 'DELETE')
                .then(res => {
                    if (res.success) { showAlert(res.message, 'success'); setTimeout(() => location.reload(), 800); }
                    else { showAlert(res.message || 'Error deleting affiliation', 'danger'); }
                }).catch(() => showAlert('An error occurred', 'danger'));
        });
    });

    // ── Add Skill — set IDs when modal trigger is clicked ────────────────────
    document.querySelectorAll('.btn-add-skill').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('addSkillAffiliationId').value = this.dataset.affiliationId;
            document.getElementById('addSkillMemberId').value = this.dataset.memberId;
        });
    });

    document.getElementById('addSkillForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const affiliationId = document.getElementById('addSkillAffiliationId').value;
        const mid = document.getElementById('addSkillMemberId').value;
        const btn = this.querySelector('[type=submit]');
        btn.disabled = true;
        affFetch(`/member/${mid}/affiliations/${affiliationId}/skills`, 'POST', formToObject(this))
            .then(res => {
                if (res.success) { showAlert(res.message, 'success'); setTimeout(() => location.reload(), 800); }
                else { showAlert(res.message || 'Error adding skill', 'danger'); btn.disabled = false; }
            }).catch(() => { showAlert('An error occurred', 'danger'); btn.disabled = false; });
    });

    // ── Delete Skill ─────────────────────────────────────────────────────────
    document.querySelectorAll('.btn-delete-skill').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!confirm('Remove this skill?')) return;
            const skillId = this.dataset.skillId;
            const mid = this.dataset.memberId;
            const affiliationId = this.dataset.affiliationId;
            affFetch(`/member/${mid}/affiliations/${affiliationId}/skills/${skillId}`, 'DELETE')
                .then(res => {
                    if (res.success) { showAlert(res.message, 'success'); setTimeout(() => location.reload(), 800); }
                    else { showAlert(res.message || 'Error removing skill', 'danger'); }
                }).catch(() => showAlert('An error occurred', 'danger'));
        });
    });

    // ── Add Media — set IDs when modal trigger is clicked ────────────────────
    document.querySelectorAll('.btn-add-media').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('addMediaAffiliationId').value = this.dataset.affiliationId;
            document.getElementById('addMediaMemberId').value = this.dataset.memberId;
        });
    });

    document.getElementById('addMediaForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const affiliationId = document.getElementById('addMediaAffiliationId').value;
        const mid = document.getElementById('addMediaMemberId').value;
        const btn = this.querySelector('[type=submit]');
        btn.disabled = true;
        affFetch(`/member/${mid}/affiliations/${affiliationId}/media`, 'POST', formToObject(this))
            .then(res => {
                if (res.success) { showAlert(res.message, 'success'); setTimeout(() => location.reload(), 800); }
                else { showAlert(res.message || 'Error adding media', 'danger'); btn.disabled = false; }
            }).catch(() => { showAlert('An error occurred', 'danger'); btn.disabled = false; });
    });

    // ── Delete Media ─────────────────────────────────────────────────────────
    document.querySelectorAll('.btn-delete-media').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!confirm('Remove this media item?')) return;
            const mediaId = this.dataset.mediaId;
            const mid = this.dataset.memberId;
            const affiliationId = this.dataset.affiliationId;
            affFetch(`/member/${mid}/affiliations/${affiliationId}/media/${mediaId}`, 'DELETE')
                .then(res => {
                    if (res.success) { showAlert(res.message, 'success'); setTimeout(() => location.reload(), 800); }
                    else { showAlert(res.message || 'Error removing media', 'danger'); }
                }).catch(() => showAlert('An error occurred', 'danger'));
        });
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
                alert('Please select a rating');
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
                if (result.success) {
                    // Show success message
                    showAlert(result.message, 'success');

                    // Reload the page to show updated reviews
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showAlert(result.message || 'Error submitting review', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error submitting review. Please try again.', 'danger');
            });
        });
    });

    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.body.appendChild(alertDiv);

        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
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
