{{-- Tournament / championship detail bottom-sheet (teleported to body).
     Expects Alpine state from window.tournamentSheet in the enclosing scope:
     showDetail (bool), detail (object|null), medalChip(medal). --}}
<template x-teleport="body">
    <div x-show="showDetail" x-cloak class="fixed inset-0 z-[70] flex flex-col justify-end"
         @keydown.escape.window="showDetail = false">
        <div x-show="showDetail" x-transition.opacity class="absolute inset-0 bg-black/60" @click="showDetail = false"></div>

        <div x-show="showDetail"
             x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
             class="relative bg-background rounded-t-3xl shadow-2xl w-full flex flex-col" style="max-height:92vh">
            <template x-if="detail">
                <div class="flex flex-col min-h-0">
                    {{-- Header: the medal is the headline, the event beneath it --}}
                    <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                         style="background: linear-gradient(150deg, #b45309, #d97706b0);">
                        <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                        <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                        <div class="relative flex items-start gap-3">
                            <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                                <i class="bi bi-trophy-fill text-xl"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-lg font-black leading-tight" x-text="detail.title"></h3>
                                {{-- Who they were on the day: the classification, plus the age
                                     and the weight they carried into it — a medal won at 12 in
                                     -45 kg is not the same medal as one won today. --}}
                                <p class="text-[12px] text-white/85 mt-0.5"
                                   x-text="[detail.type, detail.sport,
                                            detail.age ? detail.age + ' ' + @js(__('member.unit_yrs')) : null,
                                            detail.weight].filter(Boolean).join(' · ')"></p>
                            </div>
                            <button type="button" @click="showDetail = false" aria-label="{{ __('shared.close') }}"
                                    class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>

                        {{-- Medals won, right under the title --}}
                        <div class="relative mt-3 flex flex-wrap gap-1.5" x-show="detail.results && detail.results.length">
                            <template x-for="(r, n) in detail.results" :key="n">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold">
                                    <i class="bi bi-award-fill"></i><span x-text="r.label"></span>
                                    <span x-show="r.points" class="opacity-80">· <span x-text="r.points"></span> {{ __('member.templates_member_show_pts') }}</span>
                                </span>
                            </template>
                        </div>

                    </div>

                    {{-- Body --}}
                    <div class="overflow-y-auto p-4 space-y-3"
                         style="max-height:calc(92vh - 8rem); padding-bottom: calc(1rem + env(safe-area-inset-bottom));">

                        {{-- The competition on the platform, where there is one to open. The
                             claim is free text; this is the event with the real draw. --}}
                        <a x-show="safeLink(detail.platform?.event?.url)" :href="safeLink(detail.platform?.event?.url)"
                           class="m-press flex items-center gap-3 bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                            <span class="w-10 h-10 rounded-xl bg-accent grid place-items-center text-primary flex-shrink-0">
                                <i class="bi bi-calendar2-event"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ __('member.tab_tournaments') }}</p>
                                <p class="text-sm font-bold text-foreground truncate" x-text="detail.platform?.event?.title"></p>
                            </div>
                            <i class="bi bi-chevron-right text-muted-foreground/50 flex-shrink-0"></i>
                        </a>

                        {{-- What actually happened: one card per bout, from this athlete's
                             corner — the opponent's face, the score, and the way in to the
                             bout sheet and its video. Drawn from the event's own draw, so
                             nobody has to re-type it into the claim. --}}
                        <template x-if="detail.platform?.bouts?.length">
                            <div class="space-y-2.5">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80">
                                    {{ __('personal.competition_record') }}
                                </p>

                                <template x-for="(b, n) in detail.platform.bouts" :key="n">
                                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden grid"
                                         style="grid-template-columns:88px 1fr; min-height:117px">
                                        {{-- Opponent's face, with the outcome pinned to it --}}
                                        <div class="relative bg-muted">
                                            <template x-if="b.opponent_photo">
                                                <img :src="b.opponent_photo" :alt="b.opponent" class="absolute inset-0 w-full h-full object-cover">
                                            </template>
                                            <template x-if="! b.opponent_photo">
                                                <div class="absolute inset-0 grid place-items-center text-muted-foreground/70 text-lg font-black"
                                                     x-text="initialsOf(b.opponent)"></div>
                                            </template>
                                            <span x-show="b.decided"
                                                  class="absolute bottom-2 -end-2 z-10 w-5 h-5 rounded-full border-2 border-white grid place-items-center text-[10px] text-white"
                                                  :class="b.won ? 'bg-green-500' : 'bg-red-600'">
                                                <i class="bi" :class="b.won ? 'bi-check-lg' : 'bi-x-lg'"></i>
                                            </span>
                                        </div>

                                        <div class="min-w-0 flex flex-col justify-between gap-2.5 px-3 py-3">
                                            <div class="flex items-start gap-2.5">
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm font-bold text-foreground truncate">
                                                        {{ __('events.bout_vs') }} <span x-text="b.opponent"></span>
                                                    </p>
                                                    <p class="text-[11px] text-muted-foreground leading-snug line-clamp-2"
                                                       x-text="[detail.platform?.event?.title, b.division, b.round].filter(Boolean).join(' · ')"></p>
                                                </div>
                                                <div class="text-end flex-shrink-0">
                                                    <p x-show="b.my_score !== null || b.their_score !== null"
                                                       class="text-[15px] font-extrabold tabular-nums leading-none">
                                                        <span x-text="b.my_score ?? '—'"></span>–<span x-text="b.their_score ?? '—'"></span>
                                                    </p>
                                                    <span class="inline-flex items-center justify-center mt-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold"
                                                          :class="! b.decided ? 'bg-gray-100 text-gray-600' : (b.won ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700')"
                                                          x-text="! b.decided ? @js(__('personal.awaiting_result')) : (b.won ? @js(__('personal.challenge_win')) : @js(__('personal.challenge_loss')))"></span>
                                                </div>
                                            </div>

                                            <div class="flex gap-1.5" x-show="safeLink(b.bout_url) || safeLink(b.video_url)">
                                                <a x-show="safeLink(b.bout_url)" :href="safeLink(b.bout_url)"
                                                   class="m-press flex-1 h-8 px-2.5 rounded-xl border border-gray-200 bg-white text-primary text-[12px] font-bold inline-flex items-center justify-center gap-1.5">
                                                    <i class="bi bi-list-ul"></i>{{ __('personal.match_details') }}
                                                </a>
                                                <a x-show="safeLink(b.video_url)" :href="safeLink(b.video_url)" target="_blank" rel="noopener noreferrer"
                                                   class="m-press flex-1 h-8 px-2.5 rounded-xl bg-primary text-white text-[12px] font-bold inline-flex items-center justify-center gap-1.5">
                                                    <i class="bi bi-play-fill text-[15px]"></i>{{ __('personal.watch') }}
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- When & where, and where the claim stands — one card: they are
                             all facts ABOUT the record, and splitting them cost a whole
                             second surface to say one line. --}}
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                            <div class="grid grid-cols-2 gap-3">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ __('member.templates_member_show_label_date') }}</p>
                                <p class="text-sm font-semibold text-foreground mt-0.5">
                                    <span x-text="detail.date"></span><span x-show="detail.time" class="text-muted-foreground font-medium"> · <span x-text="detail.time"></span></span>
                                </p>
                            </div>
                            <div x-show="detail.location">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ __('member.templates_member_show_label_location') }}</p>
                                <p class="text-sm font-semibold text-foreground mt-0.5 truncate" x-text="detail.location"></p>
                            </div>
                            <div x-show="detail.participants">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ __('member.templates_member_show_participants') }}</p>
                                <p class="text-sm font-semibold text-foreground mt-0.5 tabular-nums" x-text="detail.participants"></p>
                            </div>
                            {{-- Who they competed for, with the club's own mark. The logo is
                                 the bare image on a sizing box — never a white tile behind it
                                 (Design Rule #5). Full width, so a long club name has room. --}}
                            <div class="col-span-2 flex items-center gap-2.5">
                                <span x-show="safeLink(detail.club_logo)" class="w-9 h-9 flex-shrink-0">
                                    <img :src="safeLink(detail.club_logo)" :alt="detail.club" class="w-full h-full object-contain">
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ __('member.representing') }}</p>
                                    <p class="text-sm font-semibold text-foreground mt-0.5 truncate"
                                       x-text="detail.club || @js(__('member.templates_member_show_individual'))"></p>
                                    <p x-show="detail.club_location" class="text-[11px] text-muted-foreground truncate" x-text="detail.club_location"></p>
                                </div>
                            </div>
                            </div>

                            {{-- Provenance, under a divider in the same card --}}
                            <div class="border-t border-gray-100 mt-3.5 pt-3.5">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-2">{{ __('Verification') }}</p>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold"
                                          :class="verifyChip(detail.verification?.status)">
                                        <i class="bi" :class="detail.verification?.status === 'verified' ? 'bi-patch-check-fill' : (detail.verification?.status === 'pending' ? 'bi-hourglass-split' : (detail.verification?.status === 'rejected' ? 'bi-x-circle' : 'bi-person-badge'))"></i>
                                        <span x-text="verifyLabel(detail.verification?.status)"></span>
                                    </span>
                                    <span x-show="detail.verification?.club" class="text-[11px] text-muted-foreground">
                                        <i class="bi bi-buildings me-0.5"></i><span x-text="detail.verification?.club"></span>
                                        <span x-show="detail.verification?.at"> · <span x-text="detail.verification?.at"></span></span>
                                    </span>
                                    <a x-show="safeLink(detail.evidence_url)" :href="safeLink(detail.evidence_url)" target="_blank" rel="noopener noreferrer"
                                       class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary">
                                        <i class="bi bi-paperclip"></i>{{ __('Evidence') }}
                                    </a>
                                </div>
                                <p x-show="detail.verification?.note" class="text-[11px] text-red-500 italic mt-1.5" x-text="detail.verification?.note"></p>
                            </div>
                        </div>

                        {{-- Results, with whatever was written about each --}}
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4"
                             x-show="detail.results && detail.results.length">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-2">{{ __('member.templates_member_show_th_performance_result') }}</p>
                            <div class="space-y-2">
                                <template x-for="(r, n) in detail.results" :key="n">
                                    <div class="flex items-start gap-2.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold flex-shrink-0" :class="medalChip(r.medal)">
                                            <span x-text="r.label"></span>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p x-show="r.description" class="text-[13px] text-foreground/90 leading-snug" x-text="r.description"></p>
                                            <p x-show="r.points" class="text-[11px] text-muted-foreground tabular-nums"><span x-text="r.points"></span> {{ __('member.templates_member_show_pts') }}</p>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Notes & media the member attached --}}
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4"
                             x-show="detail.notes && detail.notes.length">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-2">{{ __('member.templates_member_show_th_notes_media') }}</p>
                            <div class="space-y-2">
                                <template x-for="(nt, n) in detail.notes" :key="n">
                                    <div>
                                        <p x-show="nt.text" class="text-[13px] text-foreground/90 leading-snug whitespace-pre-line" x-text="nt.text"></p>
                                        <a x-show="safeLink(nt.link)" :href="safeLink(nt.link)" target="_blank" rel="noopener noreferrer"
                                           class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary mt-0.5">
                                            <i class="bi bi-image"></i>{{ __('member.templates_member_show_view_media') }}
                                        </a>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                </div>
            </template>
        </div>
    </div>
</template>
