{{-- Achievement detail bottom-sheet (teleported to body). --}}
{{-- Expects Alpine state in the enclosing scope: showAch (bool), ach (object|null), idx (int), medalEmoji(role). --}}
<template x-teleport="body">
    <div x-show="showAch" x-cloak class="fixed inset-0 z-[70] overflow-y-auto" @keydown.escape.window="showAch=false">
        <div x-show="showAch" x-transition.opacity class="fixed inset-0 bg-black/60" @click="showAch=false"></div>
        <div class="flex min-h-full items-end justify-center sm:items-center sm:p-4">
            <div x-show="showAch"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
                 class="relative bg-white rounded-t-3xl sm:rounded-2xl shadow-xl w-full sm:max-w-lg flex flex-col" style="max-height:92vh" @click.stop>
                <template x-if="ach">
                    <div class="flex flex-col overflow-hidden">
                        {{-- Media --}}
                        <div class="relative flex-shrink-0">
                            <div class="flex overflow-x-auto snap-x snap-mandatory scrollbar-hide rounded-t-3xl sm:rounded-t-2xl" x-ref="strip" @scroll.debounce.50ms="idx = Math.round($refs.strip.scrollLeft / $refs.strip.offsetWidth)">
                                <template x-if="ach.images && ach.images.length">
                                    <template x-for="img in ach.images" :key="img">
                                        <img :src="img" class="snap-start flex-shrink-0 w-full h-56 object-cover">
                                    </template>
                                </template>
                                <template x-if="!ach.images || !ach.images.length">
                                    <div class="w-full h-56 flex items-center justify-center" :style="'background:linear-gradient(135deg,'+ach.bg_from+','+ach.bg_to+')'">
                                        <span class="text-6xl opacity-40" x-text="ach.type_icon"></span>
                                    </div>
                                </template>
                            </div>
                            <button type="button" @click="showAch=false" class="absolute top-3 right-3 w-9 h-9 rounded-full bg-black/40 backdrop-blur text-white grid place-items-center"><i class="bi bi-x-lg"></i></button>
                            <div class="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/60 to-transparent pointer-events-none"></div>
                            <div x-show="ach.images && ach.images.length > 1" class="absolute bottom-2.5 left-1/2 -translate-x-1/2 flex gap-1.5">
                                <template x-for="(img,i) in ach.images" :key="i">
                                    <span class="h-1.5 rounded-full transition-all" :class="idx===i ? 'bg-white w-4' : 'bg-white/50 w-1.5'"></span>
                                </template>
                            </div>
                            {{-- The member's own award, front and centre --}}
                            <span class="absolute top-3 left-3 inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold text-white bg-black/45 backdrop-blur">
                                <span class="text-[13px] leading-none" x-text="ach.emoji"></span><span x-text="ach.member_award"></span>
                            </span>
                        </div>

                        {{-- Body --}}
                        <div class="overflow-y-auto p-4 space-y-3" style="max-height:calc(92vh - 14rem)">
                            <div>
                                <h3 class="text-lg font-extrabold text-foreground leading-tight" x-text="ach.title"></h3>
                                <p class="mt-1 text-xs text-muted-foreground flex flex-wrap gap-x-3 gap-y-0.5">
                                    <span x-show="ach.location"><i class="bi bi-geo-alt mr-0.5"></i><span x-text="ach.location"></span></span>
                                    <span x-show="ach.date_label"><i class="bi bi-calendar-event mr-0.5"></i><span x-text="ach.date_label"></span></span>
                                    <span x-show="ach.club"><i class="bi bi-buildings mr-0.5"></i><span x-text="ach.club"></span></span>
                                    <span x-show="window.memberTimeAgo && window.memberTimeAgo(ach.event_date)" class="text-primary/70"><i class="bi bi-clock-history mr-0.5"></i><span x-text="window.memberTimeAgo ? window.memberTimeAgo(ach.event_date) : ''"></span></span>
                                </p>
                            </div>
                            <p x-show="ach.description" class="text-[13px] text-foreground/90 whitespace-pre-line" x-text="ach.description"></p>
                            <div x-show="ach.athletes && ach.athletes.length">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-1.5">{{ __('club.ach_athletes') }}</p>
                                <div class="space-y-1.5">
                                    <template x-for="athl in ach.athletes" :key="athl.name">
                                        <div class="flex items-center gap-2.5 rounded-xl bg-muted/50 px-2.5 py-2">
                                            <span class="w-7 h-7 rounded-full bg-primary text-white text-[11px] font-bold grid place-items-center flex-shrink-0" x-text="athl.name.charAt(0).toUpperCase()"></span>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[13px] font-semibold text-foreground truncate" x-text="athl.name"></p>
                                                <p x-show="athl.role" class="text-[11px] text-muted-foreground flex items-center gap-1">
                                                    <span class="text-[13px] leading-none" x-text="medalEmoji(athl.role)"></span><span x-text="athl.role"></span>
                                                </p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
