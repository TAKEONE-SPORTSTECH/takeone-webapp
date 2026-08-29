{{--
    "Who is in this corner?" — the sheet that IS the feature.

    Three ways in, in the order they are actually reached:

      1. FIND A MEMBER — club-mates by name, anyone else by their exact email or
         phone. Narrow on purpose: a scoreboard must not become a way of
         browsing the platform's people (see OpenMatSession::searchOpponents).
      2. TYPE A NAME — for somebody who is not on TAKEONE at all. Nothing is
         created for them. This is the half of the feature that lets a mat be
         used on a stranger, which is the half that lets it travel.
      3. LET THEM JOIN — six characters and a QR. The consent path, and the only
         one that reaches a member from another club whom nobody can search.

    Header follows the sheet band (Design Rule #8): hex → hex+b0, drag handle,
    icon tile, title, sub-line, round ✕. The colour is the CORNER'S — red or
    blue — so the sheet says which corner it is filling before it is read.
--}}
<template x-teleport="body">
    <div x-show="sheet" class="fixed inset-0 z-[70]" x-cloak>
        <div class="absolute inset-0 bg-black/50" @click="sheet = false" x-transition.opacity></div>

        <div class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">

            {{-- ── Band ─────────────────────────────────────────────────── --}}
            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                 :style="side === 'aka'
                    ? 'background: linear-gradient(150deg, #E11D48, #E11D48b0);'
                    : 'background: linear-gradient(150deg, #2563EB, #2563EBb0);'">
                <div class="absolute -end-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                        <i class="bi bi-person-fill text-xl"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-black leading-tight">{{ __('event-open_mat::messages.fill_title') }}</h3>
                        <p class="text-[12px] text-white/85 mt-0.5"
                           x-text="(side === 'aka' ? '{{ __('event-open_mat::messages.console_red') }}' : '{{ __('event-open_mat::messages.console_blue') }}') + ' · ' + mat"></p>
                    </div>
                    <button type="button" @click="sheet = false" aria-label="{{ __('shared.close') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                {{-- Tabs, on the band so the body scrolls under them --}}
                <div class="relative mt-3.5 flex gap-1.5 bg-black/15 rounded-2xl p-1">
                    <button type="button" @click="tab = 'search'"
                            class="flex-1 h-9 rounded-xl text-[11px] font-bold transition-colors"
                            :class="tab === 'search' ? 'bg-white text-foreground' : 'text-white/85'">
                        <i class="bi bi-search me-1"></i>{{ __('event-open_mat::messages.fill_search_tab') }}
                    </button>
                    <button type="button" @click="tab = 'guest'"
                            class="flex-1 h-9 rounded-xl text-[11px] font-bold transition-colors"
                            :class="tab === 'guest' ? 'bg-white text-foreground' : 'text-white/85'">
                        <i class="bi bi-pencil me-1"></i>{{ __('event-open_mat::messages.fill_guest_tab') }}
                    </button>
                    <button type="button" @click="tab = 'code'"
                            class="flex-1 h-9 rounded-xl text-[11px] font-bold transition-colors"
                            :class="tab === 'code' ? 'bg-white text-foreground' : 'text-white/85'">
                        <i class="bi bi-qr-code me-1"></i>{{ __('event-open_mat::messages.fill_code_tab') }}
                    </button>
                </div>
            </div>

            {{-- ── Body ─────────────────────────────────────────────────── --}}
            <div class="flex-1 overflow-y-auto px-5 py-4">

                {{-- 1. Find a member --}}
                <div x-show="tab === 'search'" class="space-y-3">
                    {{-- The commonest corner of all: the person holding the
                         console. They opened the mat because they are about to
                         fight on it, so this is one tap and no typing. --}}
                    <template x-if="me && ! inACorner(me.id)">
                        <button type="button" @click="placeMember(me)" :disabled="busy"
                                class="w-full flex items-center gap-3 rounded-2xl border-2 p-3 text-start transition-colors"
                                :class="side === 'aka' ? 'border-rose-300 bg-rose-50/60 hover:bg-rose-50' : 'border-blue-300 bg-blue-50/60 hover:bg-blue-50'">
                            <span class="w-10 h-[54px] rounded-lg overflow-hidden bg-muted flex-shrink-0">
                                <img :src="me.photo || me.fallback" :alt="me.name" class="w-full h-full object-cover">
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-black text-foreground">{{ __('event-open_mat::messages.fill_me') }}</span>
                                <span class="block text-[11px] text-muted-foreground truncate" x-text="me.name"></span>
                            </span>
                            <i class="bi bi-box-arrow-in-right text-xl flex-shrink-0"
                               :class="side === 'aka' ? 'text-rose-500' : 'text-blue-500'"></i>
                        </button>
                    </template>

                    <div class="relative">
                        <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-muted-foreground"></i>
                        <input type="search" x-model="q" @input.debounce.350ms="search()"
                               placeholder="{{ __('event-open_mat::messages.fill_search_placeholder') }}"
                               class="w-full ps-10 pe-3 py-3 border border-border rounded-2xl bg-white focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
                    </div>
                    <p class="text-[11px] text-muted-foreground leading-relaxed">{{ __('event-open_mat::messages.fill_search_hint') }}</p>

                    <div class="space-y-2">
                        <template x-for="p in results" :key="p.id">
                            <button type="button" @click="placeMember(p)" :disabled="busy"
                                    class="w-full flex items-center gap-3 rounded-2xl border border-border bg-white p-2.5 text-start hover:bg-muted/40 transition-colors">
                                <span class="w-9 h-12 rounded-lg overflow-hidden bg-muted flex-shrink-0">
                                    <img :src="p.photo || p.fallback" :alt="p.name" class="w-full h-full object-cover">
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-sm font-bold text-foreground truncate">
                                        <span x-text="p.name"></span>
                                        <span x-show="p.is_me" class="ms-1.5 text-[10px] font-black uppercase tracking-wider text-primary">{{ __('event-open_mat::messages.fill_me') }}</span>
                                    </span>
                                    <span class="block text-[11px] text-muted-foreground truncate" x-text="p.club || ''"></span>
                                </span>
                                <i class="bi bi-plus-circle-fill text-primary text-xl flex-shrink-0"></i>
                            </button>
                        </template>
                    </div>

                    <p x-show="! searching && q.trim().length >= 2 && results.length === 0"
                       class="text-sm text-muted-foreground text-center py-6 leading-relaxed">{{ __('event-open_mat::messages.fill_search_empty') }}</p>
                    <p x-show="searching" class="text-center py-6 text-muted-foreground"><i class="bi bi-arrow-repeat animate-spin"></i></p>
                </div>

                {{-- 2. Type a name --}}
                <div x-show="tab === 'guest'" class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('event-open_mat::messages.fill_guest_name') }}</label>
                        <input type="text" x-model="guestName" maxlength="60"
                               @keydown.enter.prevent="placeGuest()"
                               placeholder="{{ __('event-open_mat::messages.fill_guest_placeholder') }}"
                               class="w-full px-3 py-3 border border-border rounded-2xl bg-white focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('event-open_mat::messages.fill_guest_country') }}</label>
                        <input type="text" x-model="guestCountry" maxlength="2"
                               placeholder="bh"
                               class="w-full px-3 py-3 border border-border rounded-2xl bg-white focus:ring-2 focus:ring-primary focus:border-transparent text-sm uppercase">
                    </div>
                    <p class="text-[11px] text-muted-foreground leading-relaxed">{{ __('event-open_mat::messages.fill_guest_hint') }}</p>
                </div>

                {{-- 3. Let them join --}}
                <div x-show="tab === 'code'" class="space-y-3 text-center">
                    <p class="text-[11px] text-muted-foreground leading-relaxed text-start">{{ __('event-open_mat::messages.fill_code_hint') }}</p>

                    <div class="rounded-3xl bg-white border border-border p-5">
                        <p class="text-4xl font-black tracking-[0.35em] text-foreground select-all" x-text="code"></p>
                        <template x-if="qrUrl">
                            {{-- Server-rendered (App\Support\Qr), addressed by MAT so the
                                 page never hands a URL to a QR generator. The code rides
                                 along as the cache-buster: burn it, and this re-fetches. --}}
                            <div class="mt-4 flex justify-center">
                                <img :src="qrUrl" alt="" class="w-44 h-44 rounded-2xl bg-white">
                            </div>
                        </template>
                    </div>

                    <div class="flex gap-2">
                        <button type="button" @click="copyJoin()" class="flex-1 h-11 rounded-2xl bg-muted text-foreground font-bold text-xs inline-flex items-center justify-center gap-1.5">
                            <i class="bi bi-link-45deg"></i>{{ __('event-open_mat::messages.fill_code_copy') }}
                        </button>
                        <button type="button" @click="newCode()" class="flex-1 h-11 rounded-2xl bg-muted text-foreground font-bold text-xs inline-flex items-center justify-center gap-1.5">
                            <i class="bi bi-arrow-repeat"></i>{{ __('event-open_mat::messages.fill_code_new') }}
                        </button>
                    </div>
                </div>

                {{-- Emptying the corner belongs to whatever is in it, so it sits
                     under all three tabs rather than in one of them. --}}
                <button type="button" x-show="corner(side)" @click="clearCorner(side)"
                        class="w-full mt-5 h-11 rounded-2xl border border-red-200 text-red-600 font-bold text-xs inline-flex items-center justify-center gap-1.5 hover:bg-red-50 transition-colors">
                    <i class="bi bi-x-circle"></i>{{ __('event-open_mat::messages.fill_clear') }}
                </button>
            </div>

            {{-- ── Footer — only the tab that SUBMITS gets one ───────────── --}}
            <div x-show="tab === 'guest'" class="flex-shrink-0 px-5 pt-3 border-t border-border"
                 style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                <button type="button" @click="placeGuest()" :disabled="! guestName.trim() || busy"
                        class="w-full h-12 rounded-2xl text-white font-black disabled:opacity-40"
                        :style="side === 'aka'
                            ? 'background: linear-gradient(120deg, #E11D48, #F97316);'
                            : 'background: linear-gradient(120deg, #2563EB, #06B6D4);'">
                    {{ __('event-open_mat::messages.fill_guest_place') }}
                </button>
            </div>
        </div>
    </div>
</template>
