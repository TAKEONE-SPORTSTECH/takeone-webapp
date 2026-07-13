@props([
    'subjects' => [],          // [['key','name','relation','initials','color'], ...]
    'facilities' => [],        // [['id','name'], ...] — club classes only
    'instructors' => [],       // [['name'], ...] club instructors — Coach dropdown
])

@php
    // Static option sets for the form — kept in sync with PersonalMobileController.
    $iconChoices = [
        'bi-trophy', 'bi-heart-pulse-fill', 'bi-lightning-charge-fill', 'bi-activity',
        'bi-water', 'bi-stars', 'bi-dribbble', 'bi-bicycle', 'bi-person-arms-up',
        'bi-calendar-check', 'bi-fire', 'bi-bullseye',
    ];
    $colorChoices = ['#7c3aed', '#ec4899', '#0ea5e9', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#14b8a6'];
    // Accept either an array or a Collection.
    $subjectsArr    = collect($subjects)->values()->all();
    $facilitiesArr  = collect($facilities)->values()->all();
    $instructorsArr = collect($instructors)->values()->all();
    $dayChoices = [
        'sunday' => 'Sun', 'monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed',
        'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat',
    ];
@endphp

{{--
    Create / edit a member's PERSONAL training session (weekly recurring).
    Captures every field the schedule card + detail render — incl. the full
    workout builder (warm-up · main lifts with sets×reps · cool-down) — so a
    personal session looks identical to a club-synced one.

    Open from anywhere:
        window.dispatchEvent(new CustomEvent('open-schedule-form'))               // create
        window.dispatchEvent(new CustomEvent('open-schedule-form', {detail: s}))  // edit (card object)
    On success it dispatches:
        window 'schedule-session-saved' { session, mode }
        window 'schedule-session-deleted' { id }
--}}
<div x-data="scheduleSessionForm({
        subjects: {{ Illuminate\Support\Js::from($subjectsArr) }},
        storeUrl: '{{ route('me.schedule.store') }}',
        baseUrl: '{{ url('/me/schedule') }}',
        csrf: '{{ csrf_token() }}',
        icons: {{ Illuminate\Support\Js::from($iconChoices) }},
        colors: {{ Illuminate\Support\Js::from($colorChoices) }},
        facilities: {{ Illuminate\Support\Js::from($facilitiesArr) }},
        instructors: {{ Illuminate\Support\Js::from($instructorsArr) }},
     })"
     @open-schedule-form.window="open($event.detail)"
     x-cloak>

    {{-- Teleport to <body> so the fixed bottom-sheet escapes #shell-content's
         transformed ancestor (.mobile-stagger leaves a transform that would
         otherwise become the positioning context and clip the sheet). --}}
    <template x-teleport="body">
    <div>
    {{-- Backdrop --}}
    <div x-show="show" x-transition.opacity
         class="fixed inset-0 z-[60] bg-black/50 backdrop-blur-sm" @click="close()"></div>

    {{-- Sheet --}}
    <div x-show="show" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full"
         class="fixed inset-x-0 bottom-0 z-[60] max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl">

        {{-- Grab handle + header --}}
        <div class="flex-shrink-0 px-5 pt-3 pb-3 border-b border-border/70 rounded-t-3xl"
             :style="`background: linear-gradient(160deg, ${form.color}, ${form.color}cc)`">
            <div class="w-10 h-1.5 rounded-full bg-white/40 mx-auto"></div>
            <div class="flex items-center justify-between mt-3 text-white">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-10 h-10 rounded-2xl bg-white/20 border border-white/30 grid place-items-center flex-shrink-0">
                        <i class="bi" :class="form.icon || 'bi-calendar-check'"></i>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-base font-black leading-tight truncate" x-text="clubMode ? '{{ __('shared.schedule_session_modal_edit_class') }}' : (mode==='edit' ? '{{ __('shared.schedule_session_modal_edit_session') }}' : '{{ __('shared.schedule_session_modal_new_session') }}')"></h2>
                        <p class="text-[11px] text-white/80 truncate" x-text="form.title || (clubMode ? '{{ __('shared.schedule_session_modal_class_details') }}' : '{{ __('shared.schedule_session_modal_add_to_week') }}')"></p>
                    </div>
                </div>
                <button type="button" @click="close()" class="m-press w-9 h-9 rounded-full bg-white/20 border border-white/30 grid place-items-center text-white flex-shrink-0">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        {{-- Scrollable body --}}
        <form @submit.prevent="save()" class="flex-1 overflow-y-auto px-4 py-4 space-y-4">

            {{-- Editing a club class? Show which club it belongs to. --}}
            <template x-if="clubMode">
                <div class="rounded-xl p-3 flex items-center gap-2.5" :style="`background:${form.color}14`">
                    <i class="bi bi-buildings text-lg" :style="`color:${form.color}`"></i>
                    <p class="text-xs text-muted-foreground">{{ __('shared.schedule_session_modal_editing_club_class') }}<span x-show="clubName"> · <span class="font-semibold text-foreground" x-text="clubName"></span></span>{{ __('shared.schedule_session_modal_changes_apply') }}</p>
                </div>
            </template>

            {{-- Who is this for (personal only) --}}
            <template x-if="!clubMode && subjects.length > 1">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('shared.schedule_session_modal_whos_it_for') }}</label>
                    <div class="flex gap-2 overflow-x-auto pb-1">
                        <template x-for="m in subjects" :key="m.key">
                            <button type="button" @click="form.subject = m.key"
                                    class="m-press flex items-center gap-2 rounded-full border ps-1.5 pe-3 py-1.5 flex-shrink-0 transition-colors"
                                    :class="form.subject===m.key ? 'border-primary bg-accent' : 'border-border bg-white'">
                                <span class="w-7 h-7 rounded-full grid place-items-center text-white text-[10px] font-bold" :style="`background:${m.color}`" x-text="m.initials"></span>
                                <span class="text-xs font-semibold text-foreground" x-text="m.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Title + discipline --}}
            <div class="grid grid-cols-1 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('shared.schedule_session_modal_title') }} <span class="text-red-500">*</span></label>
                    <input type="text" x-model="form.title" maxlength="120" placeholder="{{ __('shared.schedule_session_modal_title_placeholder') }}"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('shared.schedule_session_modal_discipline') }}</label>
                    <input type="text" x-model="form.discipline" maxlength="120" placeholder="{{ __('shared.schedule_session_modal_discipline_placeholder') }}"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
            </div>

            {{-- Day --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('shared.schedule_session_modal_day') }} <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-7 gap-1">
                    @foreach($dayChoices as $val => $short)
                        <button type="button" @click="form.day='{{ $val }}'"
                                class="m-press py-2 rounded-lg text-[11px] font-bold border transition-colors"
                                :class="form.day==='{{ $val }}' ? 'bg-primary border-primary text-white' : 'bg-white border-gray-200 text-foreground'">{{ __('shared.schedule_session_modal_day_'.$val) }}</button>
                    @endforeach
                </div>
            </div>

            {{-- Time --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('shared.schedule_session_modal_start') }}</label>
                    <input type="time" x-model="form.start_time"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('shared.schedule_session_modal_end') }}</label>
                    <input type="time" x-model="form.end_time"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
            </div>

            {{-- Coach — searchable instructor dropdown for club classes, free text otherwise --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('shared.schedule_session_modal_coach') }}</label>

                {{-- Club: pick from the club's instructors --}}
                <template x-if="instructors.length">
                    <div class="relative" @click.outside="coachOpen=false">
                        <button type="button" @click="coachOpen=!coachOpen; coachQuery=''"
                                class="w-full px-3 py-2.5 border border-gray-200 rounded-lg bg-white text-left flex items-center gap-2.5 focus:ring-2 focus:ring-purple-500">
                            <template x-if="form.coach">
                                <span class="w-7 h-7 rounded-full overflow-hidden bg-muted grid place-items-center flex-shrink-0">
                                    <template x-if="coachAvatar(form.coach)"><img :src="coachAvatar(form.coach)" alt="" class="w-7 h-7 object-cover"></template>
                                    <template x-if="!coachAvatar(form.coach)"><span class="text-[10px] font-bold text-muted-foreground" x-text="coachInitials(form.coach)"></span></template>
                                </span>
                            </template>
                            <span class="truncate flex-1" :class="form.coach ? 'text-foreground' : 'text-gray-400'" x-text="form.coach || '{{ __('shared.schedule_session_modal_select_coach') }}'"></span>
                            <i class="bi bi-chevron-down text-gray-400 flex-shrink-0"></i>
                        </button>
                        <div x-show="coachOpen" x-cloak x-transition.opacity
                             class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-hidden flex flex-col">
                            <div class="p-2 border-b border-gray-100">
                                <div class="relative">
                                    <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                    <input type="text" x-model="coachQuery" x-ref="coachSearch" placeholder="{{ __('shared.schedule_session_modal_search_instructors') }}"
                                           class="w-full ps-9 pe-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                </div>
                            </div>
                            <div class="overflow-y-auto">
                                <button type="button" @click="form.coach=''; coachOpen=false"
                                        class="w-full text-start px-3 py-2 text-sm text-muted-foreground hover:bg-muted">{{ __('shared.schedule_session_modal_no_coach') }}</button>
                                <template x-for="ins in filteredCoaches()" :key="ins.name">
                                    <button type="button" @click="form.coach=ins.name; coachOpen=false"
                                            class="w-full text-start px-3 py-2 text-sm hover:bg-muted flex items-center gap-2.5"
                                            :class="form.coach===ins.name ? 'bg-accent' : ''">
                                        <span class="w-8 h-8 rounded-full overflow-hidden bg-muted grid place-items-center flex-shrink-0">
                                            <template x-if="ins.avatar"><img :src="ins.avatar" alt="" class="w-8 h-8 object-cover"></template>
                                            <template x-if="!ins.avatar"><span class="text-[10px] font-bold text-muted-foreground" x-text="ins.initials"></span></template>
                                        </span>
                                        <span class="truncate flex-1" :class="form.coach===ins.name ? 'text-primary font-semibold' : 'text-foreground'" x-text="ins.name"></span>
                                        <i x-show="form.coach===ins.name" class="bi bi-check-lg text-primary flex-shrink-0"></i>
                                    </button>
                                </template>
                                <p x-show="filteredCoaches().length===0" class="px-3 py-3 text-xs text-muted-foreground text-center">{{ __('shared.schedule_session_modal_no_instructor_found') }}</p>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Personal: free text --}}
                <template x-if="!instructors.length">
                    <input type="text" x-model="form.coach" maxlength="120" placeholder="{{ __('shared.schedule_session_modal_coach_placeholder') }}"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </template>
            </div>

            {{-- Location — 3 modes: facility (club only) / map pin / text --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('shared.schedule_session_modal_location') }}</label>
                <div class="flex gap-1 p-1 rounded-xl bg-muted mb-3">
                    <template x-if="facilities.length">
                        <button type="button" @click="setLocType('facility')" class="m-press flex-1 py-1.5 rounded-lg text-xs font-bold transition-colors" :class="form.loc_type==='facility' ? 'bg-white shadow text-foreground' : 'text-muted-foreground'"><i class="bi bi-building"></i> {{ __('shared.schedule_session_modal_loc_facility') }}</button>
                    </template>
                    <button type="button" @click="setLocType('map')" class="m-press flex-1 py-1.5 rounded-lg text-xs font-bold transition-colors" :class="form.loc_type==='map' ? 'bg-white shadow text-foreground' : 'text-muted-foreground'"><i class="bi bi-geo-alt"></i> {{ __('shared.schedule_session_modal_loc_map') }}</button>
                    <button type="button" @click="setLocType('text')" class="m-press flex-1 py-1.5 rounded-lg text-xs font-bold transition-colors" :class="form.loc_type==='text' ? 'bg-white shadow text-foreground' : 'text-muted-foreground'"><i class="bi bi-type"></i> {{ __('shared.schedule_session_modal_loc_text') }}</button>
                </div>

                {{-- Facility dropdown --}}
                <div x-show="form.loc_type==='facility'" x-cloak>
                    <select x-model="form.facility_id" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value="">{{ __('shared.schedule_session_modal_select_facility') }}</option>
                        <template x-for="f in facilities" :key="f.id">
                            <option :value="f.id" x-text="f.name"></option>
                        </template>
                    </select>
                </div>

                {{-- Map pin --}}
                <div x-show="form.loc_type==='map'" x-cloak>
                    <x-location-map
                        :id="'schedFormMap'"
                        lat-name="schedFormMapLat" lng-name="schedFormMapLng" address-name="schedFormMapAddr"
                        height="13rem" />
                </div>

                {{-- Free text --}}
                <div x-show="form.loc_type==='text'" x-cloak>
                    <input type="text" x-model="form.location" maxlength="160" placeholder="{{ __('shared.schedule_session_modal_location_placeholder') }}"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
            </div>

            {{-- Intensity --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('shared.schedule_session_modal_intensity') }}</label>
                <div class="grid grid-cols-3 gap-2">
                    @foreach(['Low','Moderate','High'] as $lvl)
                        <button type="button" @click="form.intensity='{{ $lvl }}'"
                                class="m-press py-2 rounded-lg text-xs font-bold border transition-colors"
                                :class="form.intensity==='{{ $lvl }}' ? 'bg-primary border-primary text-white' : 'bg-white border-gray-200 text-foreground'">{{ __('shared.schedule_session_modal_intensity_'.strtolower($lvl)) }}</button>
                    @endforeach
                </div>
            </div>

            {{-- Icon + colour --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('shared.schedule_session_modal_icon') }}</label>
                <div class="flex flex-wrap gap-2">
                    <template x-for="ic in icons" :key="ic">
                        <button type="button" @click="form.icon=ic"
                                class="m-press w-10 h-10 rounded-xl grid place-items-center border transition-all"
                                :class="form.icon===ic ? 'border-transparent text-white' : 'border-gray-200 bg-white text-foreground'"
                                :style="form.icon===ic ? `background:${form.color}` : ''">
                            <i class="bi" :class="ic"></i>
                        </button>
                    </template>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('shared.schedule_session_modal_colour') }}</label>
                <div class="flex flex-wrap gap-2.5">
                    <template x-for="c in colors" :key="c">
                        <button type="button" @click="form.color=c"
                                class="m-press w-9 h-9 rounded-full border-2 transition-transform"
                                :class="form.color===c ? 'scale-110 border-foreground' : 'border-white'"
                                :style="`background:${c}`"></button>
                    </template>
                </div>
            </div>

            {{-- Focus chips --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('shared.schedule_session_modal_focus') }}</label>
                <div class="flex flex-wrap gap-2 mb-2" x-show="form.focus.length">
                    <template x-for="(f, i) in form.focus" :key="i">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium"
                              :style="`background:${form.color}1a;color:${form.color}`">
                            <span x-text="f"></span>
                            <button type="button" @click="form.focus.splice(i,1)"><i class="bi bi-x-circle-fill opacity-60"></i></button>
                        </span>
                    </template>
                </div>
                <div class="flex gap-2">
                    <input type="text" x-model="focusInput" @keydown.enter.prevent="addFocus()" maxlength="40" placeholder="{{ __('shared.schedule_session_modal_focus_placeholder') }}"
                           class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <button type="button" @click="addFocus()" class="m-press px-3 rounded-lg bg-accent text-primary font-bold"><i class="bi bi-plus-lg"></i></button>
                </div>
            </div>

            {{-- Coach note --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('shared.schedule_session_modal_class_details') }}</label>
                <textarea x-model="form.notes" rows="2" maxlength="2000" placeholder="{{ __('shared.schedule_session_modal_notes_placeholder') }}"
                          class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
            </div>

            {{-- ===== Workout builder ===== --}}
            <div class="rounded-2xl border border-border bg-white p-3.5 space-y-4">
                <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground flex items-center gap-1.5">
                    <i class="bi bi-clipboard-check text-primary"></i> {{ __('shared.schedule_session_modal_workout') }}
                </p>

                {{-- Warm-up --}}
                <div>
                    <p class="text-sm font-bold text-foreground flex items-center gap-2 mb-2"><i class="bi bi-fire text-amber-500"></i> {{ __('shared.schedule_session_modal_warmup') }}</p>
                    <template x-for="(w, i) in form.warmup" :key="'w'+i">
                        <div class="flex gap-2 mb-2">
                            <input type="text" x-model="form.warmup[i]" maxlength="200" placeholder="{{ __('shared.schedule_session_modal_warmup_placeholder') }}"
                                   class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <button type="button" @click="form.warmup.splice(i,1)" class="m-press w-9 rounded-lg bg-red-50 text-red-500 grid place-items-center"><i class="bi bi-trash"></i></button>
                        </div>
                    </template>
                    <button type="button" @click="form.warmup.push('')" class="m-press text-xs font-bold text-primary flex items-center gap-1"><i class="bi bi-plus-circle"></i> {{ __('shared.schedule_session_modal_add_warmup') }}</button>
                </div>

                {{-- Main exercises --}}
                <div class="pt-3 border-t border-border">
                    <p class="text-sm font-bold text-foreground flex items-center gap-2 mb-2"><i class="bi bi-clipboard-check text-primary"></i> {{ __('shared.schedule_session_modal_main') }}</p>
                    <template x-for="(ex, i) in form.main" :key="'m'+i">
                        <div class="rounded-xl border border-gray-100 bg-background p-2.5 mb-2 space-y-2">
                            <div class="flex gap-2">
                                <input type="text" x-model="ex.name" maxlength="120" placeholder="{{ __('shared.schedule_session_modal_exercise_name') }}"
                                       class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <button type="button" @click="form.main.splice(i,1)" class="m-press w-9 rounded-lg bg-red-50 text-red-500 grid place-items-center"><i class="bi bi-trash"></i></button>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <input type="text" x-model="ex.sets" maxlength="20" placeholder="{{ __('shared.schedule_session_modal_sets') }}"
                                       class="px-2.5 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <input type="text" x-model="ex.reps" maxlength="40" placeholder="{{ __('shared.schedule_session_modal_reps') }}"
                                       class="px-2.5 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <input type="text" x-model="ex.note" maxlength="120" placeholder="{{ __('shared.schedule_session_modal_note') }}"
                                       class="px-2.5 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                        </div>
                    </template>
                    <button type="button" @click="form.main.push({name:'',sets:'',reps:'',note:''})" class="m-press text-xs font-bold text-primary flex items-center gap-1"><i class="bi bi-plus-circle"></i> {{ __('shared.schedule_session_modal_add_exercise') }}</button>
                </div>

                {{-- Cool-down --}}
                <div class="pt-3 border-t border-border">
                    <p class="text-sm font-bold text-foreground flex items-center gap-2 mb-2"><i class="bi bi-snow text-sky-500"></i> {{ __('shared.schedule_session_modal_cooldown') }}</p>
                    <template x-for="(c, i) in form.cooldown" :key="'c'+i">
                        <div class="flex gap-2 mb-2">
                            <input type="text" x-model="form.cooldown[i]" maxlength="200" placeholder="{{ __('shared.schedule_session_modal_cooldown_placeholder') }}"
                                   class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <button type="button" @click="form.cooldown.splice(i,1)" class="m-press w-9 rounded-lg bg-red-50 text-red-500 grid place-items-center"><i class="bi bi-trash"></i></button>
                        </div>
                    </template>
                    <button type="button" @click="form.cooldown.push('')" class="m-press text-xs font-bold text-primary flex items-center gap-1"><i class="bi bi-plus-circle"></i> {{ __('shared.schedule_session_modal_add_cooldown') }}</button>
                </div>
            </div>

            <div class="h-2"></div>
        </form>

        {{-- Sticky footer actions --}}
        <div class="flex-shrink-0 px-4 py-3 border-t border-border bg-white flex items-center gap-2"
             style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
            <template x-if="mode==='edit' && !clubMode">
                <button type="button" @click="removeSession()" :disabled="saving"
                        class="m-press w-12 h-12 rounded-xl border border-red-200 text-red-500 grid place-items-center flex-shrink-0">
                    <i class="bi bi-trash text-lg"></i>
                </button>
            </template>
            <button type="button" @click="save()" :disabled="saving"
                    class="m-press flex-1 h-12 rounded-xl text-white font-bold text-sm flex items-center justify-center gap-2 disabled:opacity-60"
                    :style="`background:${form.color}`">
                <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check2-circle'"></i>
                <span x-text="mode==='edit' ? '{{ __('shared.schedule_session_modal_save_changes') }}' : '{{ __('shared.schedule_session_modal_add_session') }}'"></span>
            </button>
        </div>
    </div>
    </div>
    </template>
</div>

<script>
// Personal schedule session create/edit sheet. Self-contained so it survives the
// mobile shell's AJAX content swaps; opened via the 'open-schedule-form' event.
// NOTE: defined inline (inside shell-content), not in a pushed script stack, so the
// shell's runScripts() re-defines it on every AJAX navigation before Alpine inits.
function scheduleSessionForm(cfg) {
    const blank = () => ({
        id: null, subject: (cfg.subjects[0] && cfg.subjects[0].key) || 'me',
        title: '', discipline: '', day: '', start_time: '', end_time: '',
        coach: '', location: '', intensity: '',
        loc_type: 'text', facility_id: '', location_lat: null, location_lng: null, location_address: '',
        icon: 'bi-trophy', color: cfg.colors[0] || '#7c3aed',
        focus: [], notes: [],
        warmup: [], main: [], cooldown: [],
    });
    return {
        show: false, mode: 'create', saving: false, focusInput: '',
        clubMode: false, clubUrl: null, clubName: '',
        subjects: cfg.subjects || [],
        facilities: cfg.facilities || [],
        instructors: cfg.instructors || [],
        coachOpen: false, coachQuery: '',
        icons: cfg.icons || [], colors: cfg.colors || [],
        form: blank(),

        filteredCoaches() {
            const q = (this.coachQuery || '').toLowerCase().trim();
            if (!q) return this.instructors;
            return this.instructors.filter(i => (i.name || '').toLowerCase().includes(q));
        },
        coachAvatar(name) { const i = this.instructors.find(x => x.name === name); return i ? i.avatar : null; },
        coachInitials(name) {
            const i = this.instructors.find(x => x.name === name);
            if (i) return i.initials;
            return (name || '').trim().split(/\s+/).slice(0, 2).map(w => w[0] || '').join('').toUpperCase();
        },

        // Prefill the form from a card object (personal or club class).
        fillFrom(detail) {
            const w = detail.workout || {};
            this.form = {
                id: detail.id,
                subject: detail.who || 'me',
                title: detail.title || '',
                discipline: detail.discipline || '',
                day: detail.day || '',
                start_time: this.to24(detail.start_raw || detail.start),
                end_time: this.to24(detail.end_raw || detail.end),
                coach: detail.coach || '',
                location: detail.location_text || (detail.location_type === 'text' ? (detail.location || '') : ''),
                loc_type: detail.location_type || (detail.location ? 'text' : 'text'),
                facility_id: detail.facility_id || '',
                location_lat: detail.location_lat ?? null,
                location_lng: detail.location_lng ?? null,
                location_address: detail.location_address || '',
                intensity: detail.intensity || '',
                icon: detail.icon || 'bi-trophy',
                color: detail.color || (this.colors[0] || '#7c3aed'),
                focus: Array.isArray(detail.focus) ? [...detail.focus] : [],
                notes: detail.notes || '',
                warmup: (w.warmup || []).slice(),
                main: (w.main || []).map(x => ({name:x.name||'',sets:String(x.sets||''),reps:String(x.reps||''),note:x.note||''})),
                cooldown: (w.cooldown || []).slice(),
            };
            // personal sessions can't use facilities
            if (!this.facilities.length && this.form.loc_type === 'facility') this.form.loc_type = 'text';
        },

        // Switch location mode; lazily init the Leaflet picker when Map is chosen.
        setLocType(t) {
            this.form.loc_type = t;
            if (t === 'map') this.$nextTick(() => this.initLocMap());
        },
        initLocMap() {
            if (!window.LocationMap) return;
            window.LocationMap.create({ id: 'schedFormMap', defaultLat: 26.2235, defaultLng: 50.5876, zoom: 13, draggable: true, readonly: false });
            const lat = this.form.location_lat, lng = this.form.location_lng;
            if (lat != null && lng != null) window.LocationMap.setPosition('schedFormMap', lat, lng);
            const addr = document.getElementById('schedFormMapAddress');
            if (addr && this.form.location_address) addr.value = this.form.location_address;
            window.LocationMap.refresh && window.LocationMap.refresh('schedFormMap');
        },

        open(detail) {
            this.focusInput = '';
            this.coachOpen = false; this.coachQuery = '';
            this.clubMode = false; this.clubUrl = null; this.clubName = '';
            const isClub = detail && (detail.source === 'synced' || detail.source === 'teaching') && detail.update_url;

            if (isClub) {
                // Edit a CLUB class with the full session form (coach/manager).
                this.mode = 'edit';
                this.clubMode = true;
                this.clubUrl = detail.update_url;
                this.clubName = detail.club || '';
                this.fillFrom(detail);
            } else if (detail && detail.id && detail.source === 'personal') {
                // Edit an existing personal session from its card object.
                this.mode = 'edit';
                this.fillFrom(detail);
            } else {
                this.mode = 'create';
                this.form = blank();
                this.form.notes = '';
            }
            this.show = true;
            document.body.style.overflow = 'hidden';
            if (this.form.loc_type === 'map') this.$nextTick(() => this.initLocMap());
        },
        close() {
            this.show = false;
            document.body.style.overflow = '';
        },
        addFocus() {
            const v = (this.focusInput || '').trim();
            if (v) { this.form.focus.push(v); this.focusInput = ''; }
        },
        // Best-effort "6:30 AM" -> "06:30" for the <input type=time> when editing.
        to24(t) {
            if (!t) return '';
            if (/^\d{1,2}:\d{2}$/.test(t)) return t;
            const m = String(t).match(/(\d{1,2}):(\d{2})\s*(AM|PM)?/i);
            if (!m) return '';
            let h = parseInt(m[1], 10); const min = m[2]; const ap = (m[3]||'').toUpperCase();
            if (ap === 'PM' && h < 12) h += 12;
            if (ap === 'AM' && h === 12) h = 0;
            return String(h).padStart(2,'0') + ':' + min;
        },

        async save() {
            if (!this.form.title.trim()) { window.showToast('error', '{{ __('shared.schedule_session_modal_toast_add_title') }}'); return; }
            if (!this.form.day) { window.showToast('error', '{{ __('shared.schedule_session_modal_toast_pick_day') }}'); return; }
            this.saving = true;
            const url = this.clubMode
                ? this.clubUrl
                : (this.mode === 'edit' ? (cfg.baseUrl + '/' + this.form.id) : cfg.storeUrl);
            const method = (this.clubMode || this.mode === 'edit') ? 'PUT' : 'POST';

            // ----- Location: read live values from the map picker when in map mode -----
            let locType = this.form.loc_type || 'text';
            if (!this.facilities.length && locType === 'facility') locType = 'text';
            let lat = null, lng = null, addr = null;
            if (locType === 'map') {
                lat = parseFloat((document.getElementById('schedFormMapLat') || {}).value) || null;
                lng = parseFloat((document.getElementById('schedFormMapLng') || {}).value) || null;
                addr = (document.getElementById('schedFormMapAddress') || {}).value || null;
                if (lat === null || lng === null) { window.showToast('error', '{{ __('shared.schedule_session_modal_toast_drop_pin') }}'); this.saving = false; return; }
            }

            const payload = {
                day: this.form.day,
                start_time: this.form.start_time || null,
                end_time: this.form.end_time || null,
                title: this.form.title.trim(),
                discipline: this.form.discipline || null,
                icon: this.form.icon, color: this.form.color,
                coach: this.form.coach || null,
                location: locType === 'text' ? (this.form.location || null) : null,
                location_type: locType,
                facility_id: locType === 'facility' ? (this.form.facility_id || null) : null,
                location_lat: lat,
                location_lng: lng,
                location_address: addr,
                intensity: this.form.intensity || null,
                focus: this.form.focus,
                notes: this.form.notes || null,
                workout: {
                    warmup: this.form.warmup,
                    main: this.form.main,
                    cooldown: this.form.cooldown,
                },
            };
            // Personal sessions carry a family "subject"; club classes don't.
            if (! this.clubMode) payload.subject = this.form.subject;
            try {
                const res = await fetch(url, {
                    method: method,
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    window.showToast('error', data.message || '{{ __('shared.schedule_session_modal_toast_could_not_save') }}');
                    this.saving = false;
                    return;
                }
                window.showToast('success', data.message || '{{ __('shared.schedule_session_modal_toast_saved') }}');
                window.dispatchEvent(new CustomEvent('schedule-session-saved', { detail: { session: data.session, mode: this.mode } }));
                this.close();
            } catch (e) {
                window.showToast('error', '{{ __('shared.schedule_session_modal_toast_network_error') }}');
            } finally {
                this.saving = false;
            }
        },

        // NB: do NOT name this destroy() — Alpine auto-invokes a component's
        // destroy() method on teardown, which fired this dialog on navigation.
        async removeSession() {
            if (!this.form.id) return;
            const ok = await window.confirmAction({ title: '{{ __('shared.schedule_session_modal_delete_confirm_title') }}', message: '{{ __('shared.schedule_session_modal_delete_confirm_message') }}', type: 'danger', confirmText: '{{ __('shared.delete') }}' });
            if (!ok) return;
            this.saving = true;
            try {
                const res = await fetch(cfg.baseUrl + '/' + this.form.id, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) { window.showToast('error', data.message || '{{ __('shared.schedule_session_modal_toast_could_not_delete') }}'); this.saving = false; return; }
                window.showToast('success', data.message || '{{ __('shared.schedule_session_modal_toast_removed') }}');
                window.dispatchEvent(new CustomEvent('schedule-session-deleted', { detail: { id: this.form.id } }));
                this.close();
            } catch (e) {
                window.showToast('error', '{{ __('shared.schedule_session_modal_toast_network_error') }}');
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
