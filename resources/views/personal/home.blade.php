@extends('layouts.personal-mobile')

@section('title', 'News Feed')

{{--
    Facebook-style mobile news feed — rendered inside the shared personal-mobile
    shell (top bar, switcher, bottom tabs & drawer come from the shell).

    Full-bleed layout: the shell's <main> has `px-4 py-4`, cancelled with
    `-mx-4 -mt-4`; each section is a full-width white block separated by gray
    gutters. Three feed tabs: "All" (everything — your posts + following + clubs),
    "Following" (posts from people you follow / are connected with), and
    "My Feeds" (your own posts + your clubs' timeline posts).

    Design system: purple primary, Bootstrap Icons, m-* motion. All notices via
    window.showToast — no native dialogs.
--}}

@php
    $me = $user ?? Auth::user();
    $myAvatar = $me->profile_picture ? asset('storage/'.$me->profile_picture).'?v='.optional($me->updated_at)->timestamp : null;
    $storyClubs = $posts->pluck('tenant')->filter()->unique('id')->values();
@endphp

@section('personal-content')
<div x-data="newsFeed()" class="-mx-4 -mt-4 px-3 pt-3 pb-6 space-y-3 min-h-screen" style="overflow-x: clip; background: hsl(220 15% 96%);">

    {{-- ===== Post composer (inline — type, attach & post all in place) ===== --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-4 py-3">
        {{-- Hidden file input drives photo selection (multiple = collage). --}}
        <input type="file" x-ref="photo" accept="image/*" multiple class="hidden" @change="pickImages($event)">

        <div class="flex items-end gap-2">
            {{-- Real text box (auto-grows as you type). In poll mode this is the question. --}}
            <textarea x-model="body" x-ref="ta" rows="1" @input="autoGrow($el)"
                      :placeholder="pollOpen ? @js(__('personal.poll_question')) : @js(__('personal.whats_on_your_mind'))"
                      class="flex-1 resize-none max-h-40 bg-muted rounded-2xl px-4 py-2.5 text-sm text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/40 transition-shadow"></textarea>

            {{-- Attachment icon --}}
            <button type="button" @click="attachOpen = !attachOpen"
                    class="m-press w-10 h-10 flex-shrink-0 rounded-full flex items-center justify-center transition-colors"
                    :class="attachOpen ? 'bg-accent text-primary' : 'text-muted-foreground hover:bg-muted'"
                    aria-label="{{ __('personal.add_photo_video_link') }}">
                <i class="bi bi-paperclip text-lg"></i>
            </button>

            {{-- Send button — posts what you've typed/attached --}}
            <button type="button" @click="submitPost()" :disabled="!canPost() || sending"
                    class="m-press w-10 h-10 flex-shrink-0 rounded-full flex items-center justify-center transition-colors"
                    :class="(canPost() && !sending) ? 'bg-primary text-white hover:bg-primary/90' : 'bg-muted text-muted-foreground cursor-not-allowed'"
                    aria-label="{{ __('personal.send_post') }}">
                <i class="bi text-base" :class="sending ? 'bi-arrow-repeat animate-spin' : 'bi-send-fill'"></i>
            </button>
        </div>

        {{-- Selected image thumbnails (preview before sending) --}}
        <div x-show="images.length" x-cloak class="flex gap-2 mt-3 overflow-x-auto scrollbar-hide">
            <template x-for="(img, i) in images" :key="img.url">
                <div class="relative flex-shrink-0">
                    <img :src="img.url" alt="" class="w-16 h-16 rounded-lg object-cover">
                    <button type="button" @click="removeImage(i)"
                            class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-black/60 text-white flex items-center justify-center"
                            aria-label="{{ __('personal.remove_image') }}">
                        <i class="bi bi-x text-sm"></i>
                    </button>
                </div>
            </template>
        </div>

        {{-- Inline poll builder (revealed when "Poll" is chosen) --}}
        <div x-show="pollOpen" x-cloak class="mt-3 space-y-2"
             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="flex items-center gap-2 text-[12px] font-semibold text-primary px-1">
                <i class="bi bi-bar-chart-fill"></i> {{ __('personal.create_poll') }}
            </div>
            <template x-for="(opt, i) in pollOptions" :key="i">
                <div class="flex items-center gap-2">
                    <input type="text" x-model="pollOptions[i]" maxlength="120"
                           :placeholder="@js(__('personal.poll_option')).replace(':n', i + 1)"
                           class="flex-1 bg-muted rounded-xl px-3.5 py-2 text-sm text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/40">
                    <button type="button" x-show="pollOptions.length > 2" @click="removeOption(i)"
                            class="m-press w-8 h-8 flex-shrink-0 rounded-full flex items-center justify-center text-muted-foreground hover:bg-muted transition-colors"
                            aria-label="{{ __('personal.remove_option') }}">
                        <i class="bi bi-x-lg text-sm"></i>
                    </button>
                </div>
            </template>
            <button type="button" x-show="pollOptions.length < 6" @click="addOption()"
                    class="m-press flex items-center gap-2 px-1 py-1 text-[13px] font-medium text-primary hover:opacity-80 transition-opacity">
                <i class="bi bi-plus-circle"></i> {{ __('personal.add_option') }}
            </button>
        </div>

        {{-- Inline highlight builder (gradient cover banner, just like club cards) --}}
        <div x-show="highlightOpen" x-cloak class="mt-3 space-y-2.5"
             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="flex items-center gap-2 text-[12px] font-semibold text-primary px-1">
                <i class="bi bi-stars"></i> {{ __('personal.create_highlight') }}
            </div>
            {{-- Live preview — exactly how the posted card will look --}}
            <div class="relative h-36 rounded-xl overflow-hidden flex flex-col justify-end p-4 text-white"
                 :style="`background: linear-gradient(135deg, ${coverColor}, ${coverColor}bb)`">
                <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10"></div>
                <div class="absolute right-6 bottom-8 w-16 h-16 rounded-full bg-white/10"></div>
                <i class="bi text-5xl opacity-90 absolute top-4 left-4 m-float" :class="coverIcon"></i>
                <span class="relative text-lg font-black drop-shadow" x-text="coverLabel || @js(__('personal.highlight'))"></span>
            </div>
            <input type="text" x-model="coverLabel" maxlength="80"
                   placeholder="{{ __('personal.highlight_title') }}"
                   class="w-full bg-muted rounded-xl px-3.5 py-2 text-sm text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/40">
            {{-- Colour --}}
            <div class="flex items-center gap-2 overflow-x-auto scrollbar-hide pb-0.5">
                <template x-for="c in coverColors" :key="c">
                    <button type="button" @click="coverColor = c"
                            class="m-press w-7 h-7 flex-shrink-0 rounded-full border-2 transition-transform"
                            :class="coverColor === c ? 'border-foreground scale-110' : 'border-transparent'"
                            :style="`background: ${c}`" aria-label="{{ __('personal.pick_a_color') }}"></button>
                </template>
            </div>
            {{-- Icon --}}
            <div class="flex items-center gap-1.5 overflow-x-auto scrollbar-hide pb-0.5">
                <template x-for="ic in coverIcons" :key="ic">
                    <button type="button" @click="coverIcon = ic"
                            class="m-press w-8 h-8 flex-shrink-0 rounded-lg grid place-items-center transition-colors"
                            :class="coverIcon === ic ? 'bg-accent text-primary' : 'bg-muted text-muted-foreground'">
                        <i class="bi" :class="ic"></i>
                    </button>
                </template>
            </div>
        </div>

        {{-- Inline attachment options — revealed in place, no popup --}}
        <div x-show="attachOpen" x-cloak class="flex items-center gap-1 mt-2"
             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
            <button type="button" @click="$refs.photo.click()"
                    class="m-press flex-1 flex items-center justify-center gap-2 py-2 rounded-lg hover:bg-muted transition-colors text-sm font-medium text-muted-foreground">
                <i class="bi bi-image text-green-500 text-lg"></i> {{ __('personal.photo') }}
            </button>
            <button type="button" @click="togglePoll()"
                    class="m-press flex-1 flex items-center justify-center gap-2 py-2 rounded-lg hover:bg-muted transition-colors text-sm font-medium"
                    :class="pollOpen ? 'text-primary' : 'text-muted-foreground'">
                <i class="bi bi-bar-chart text-primary text-lg"></i> {{ __('personal.poll') }}
            </button>
            <button type="button" onclick="window.showToast && window.showToast('info', @js(__('personal.video_soon')))"
                    class="m-press flex-1 flex items-center justify-center gap-2 py-2 rounded-lg hover:bg-muted transition-colors text-sm font-medium text-muted-foreground">
                <i class="bi bi-camera-video text-red-500 text-lg"></i> {{ __('personal.video') }}
            </button>
            <button type="button" @click="toggleHighlight()"
                    class="m-press flex-1 flex items-center justify-center gap-2 py-2 rounded-lg hover:bg-muted transition-colors text-sm font-medium"
                    :class="highlightOpen ? 'text-primary' : 'text-muted-foreground'">
                <i class="bi bi-stars text-amber-500 text-lg"></i> {{ __('personal.highlight') }}
            </button>
        </div>
    </div>

    {{-- ===== Feed tabs: All · Club · Following · Mine (segmented pill) ===== --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-1 flex gap-1">
        <button type="button" @click="tab='all'; seenTab('all')"
                class="relative m-press flex-1 py-2 rounded-xl text-sm font-semibold transition-colors"
                :class="tab==='all' ? 'bg-primary text-white' : 'text-muted-foreground'">
            {{ __('personal.all') }}
            <span x-show="dots.all && tab!=='all'" x-cloak class="absolute top-1.5 right-2 rtl:right-auto rtl:left-2 w-2 h-2 rounded-full bg-red-500 ring-2 ring-white"></span>
        </button>
        <button type="button" @click="tab='club'; seenTab('club')"
                class="relative m-press flex-1 py-2 rounded-xl text-sm font-semibold transition-colors"
                :class="tab==='club' ? 'bg-primary text-white' : 'text-muted-foreground'">
            {{ __('personal.club') }}
            <span x-show="dots.club && tab!=='club'" x-cloak class="absolute top-1.5 right-2 rtl:right-auto rtl:left-2 w-2 h-2 rounded-full bg-red-500 ring-2 ring-white"></span>
        </button>
        <button type="button" @click="tab='following'; seenTab('following')"
                class="relative m-press flex-1 py-2 rounded-xl text-sm font-semibold transition-colors"
                :class="tab==='following' ? 'bg-primary text-white' : 'text-muted-foreground'">
            {{ __('personal.following') }}
            <span x-show="dots.following && tab!=='following'" x-cloak class="absolute top-1.5 right-2 rtl:right-auto rtl:left-2 w-2 h-2 rounded-full bg-red-500 ring-2 ring-white"></span>
        </button>
        <button type="button" @click="tab='mine'; seenTab('mine')"
                class="relative m-press flex-1 py-2 rounded-xl text-sm font-semibold transition-colors"
                :class="tab==='mine' ? 'bg-primary text-white' : 'text-muted-foreground'">
            {{ __('personal.my_feeds') }}
            <span x-show="dots.mine && tab!=='mine'" x-cloak class="absolute top-1.5 right-2 rtl:right-auto rtl:left-2 w-2 h-2 rounded-full bg-red-500 ring-2 ring-white"></span>
        </button>
    </div>

    {{-- ===== Feed posts (each a floating rounded card) ===== --}}
    <div class="mobile-stagger space-y-3">

        {{-- ALL tab — one stream blending club timeline, club-mates & your own
             posts, newest first (sorted server-side, see $allPosts). Each item
             carries `type` ('member' | 'club') so we render the right card. --}}
        <template x-if="tab==='all'">
            <div class="space-y-2">
                <template x-for="post in allPosts" :key="post.kind + '-' + post.id">
                    <div>
                        {{-- Member post (your own or a club-mate's) --}}
                        <template x-if="post.kind === 'member'">
                            @include('personal.partials.post-card')
                        </template>

                        {{-- Club timeline post --}}
                        <template x-if="post.kind === 'club'">
                            <article class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden"
                                     x-data="{ liked:false, likes: post.likes, showC:false, draft:'', cmts: (post.commentList || []), cc: post.comments,
                                               addC() { const t=draft.trim(); if(!t) return; this.cmts.push({id:Date.now(), name:me.name, avatar:me.avatar, body:t}); this.cc++; this.draft=''; this.showC=true; } }">
                                <div class="flex items-start justify-between px-4 pt-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="w-10 h-10 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                                            <template x-if="post.club.logo"><img :src="post.club.logo" alt="" class="w-10 h-10 object-cover"></template>
                                            <template x-if="!post.club.logo"><i class="bi bi-buildings text-muted-foreground"></i></template>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-sm text-foreground truncate" x-text="post.club.name"></p>
                                            <p class="text-[11px] text-gray-500 flex items-center gap-1">
                                                <span class="truncate" x-text="post.category + ' · ' + post.time"></span>
                                                <i class="bi bi-globe2"></i>
                                            </p>
                                        </div>
                                    </div>
                                    <button type="button"
                                            onclick="window.showToast && window.showToast('info', @js(__('personal.post_options_soon')))"
                                            class="m-press w-8 h-8 -mr-1 flex items-center justify-center rounded-full text-gray-500 hover:bg-muted transition-colors flex-shrink-0">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                </div>

                                <p x-show="post.body" class="text-[13px] text-gray-900 whitespace-pre-line px-4 py-2.5" x-text="post.body"></p>

                                <template x-if="post.image">
                                    <button type="button" @click="openLightbox([{ url: post.image }], 0)" class="block w-full">
                                        <img :src="post.image" alt="" class="w-full max-h-96 object-cover">
                                    </button>
                                </template>

                                {{-- Gradient cover (demo posts only — no external image) --}}
                                <template x-if="post.cover && !post.image">
                                    <div class="relative h-48 overflow-hidden flex flex-col justify-end p-4 text-white mt-1"
                                         :style="`background: linear-gradient(135deg, ${post.cover.color}, ${post.cover.color}bb)`">
                                        <div class="absolute -right-8 -top-8 w-32 h-32 rounded-full bg-white/10"></div>
                                        <div class="absolute right-6 bottom-10 w-20 h-20 rounded-full bg-white/10"></div>
                                        <i class="bi text-6xl opacity-90 absolute top-5 left-4 m-float" :class="post.cover.icon"></i>
                                        <span class="relative text-lg font-black drop-shadow" x-text="post.cover.label"></span>
                                    </div>
                                </template>

                                <div x-show="likes > 0 || cc > 0" x-cloak class="flex items-center justify-between px-4 py-2 text-[12px] text-gray-500">
                                    <span class="flex items-center gap-1.5" x-show="likes > 0">
                                        <span class="w-4 h-4 rounded-full bg-primary text-white flex items-center justify-center text-[9px]">
                                            <i class="bi bi-heart-fill"></i>
                                        </span>
                                        <span x-text="likes"></span>
                                    </span>
                                    <button type="button" class="ml-auto hover:underline" x-show="cc > 0" @click="showC = true"
                                          x-text="cc + ' ' + (cc === 1 ? @js(__('personal.comment_one')) : @js(__('personal.comments_many')))"></button>
                                </div>

                                <div class="flex border-t border-gray-100 text-[13px] font-medium text-gray-600">
                                    <button type="button"
                                            @click="liked = !liked; likes += liked ? 1 : -1"
                                            class="m-press flex-1 flex items-center justify-center gap-2 py-2.5 hover:bg-muted transition-colors"
                                            :class="liked ? 'text-primary' : ''">
                                        <i class="bi" :class="liked ? 'bi-heart-fill' : 'bi-heart'"></i> {{ __('personal.like') }}
                                    </button>
                                    <button type="button"
                                            @click="showC = !showC"
                                            class="m-press flex-1 flex items-center justify-center gap-2 py-2.5 hover:bg-muted transition-colors">
                                        <i class="bi bi-chat"></i> {{ __('personal.comment') }}
                                    </button>
                                    <button type="button"
                                            @click="sharePost({ body: post.body || post.club.name })"
                                            class="m-press flex-1 flex items-center justify-center gap-2 py-2.5 hover:bg-muted transition-colors">
                                        <i class="bi bi-share"></i> {{ __('personal.share') }}
                                    </button>
                                </div>

                                {{-- Comments (functional, local) --}}
                                <div x-show="showC" x-cloak class="px-4 pb-3 pt-2 border-t border-gray-100 space-y-2">
                                    <template x-for="c in cmts" :key="c.id">
                                        <div class="flex items-start gap-2">
                                            <span class="w-7 h-7 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                                                <template x-if="c.avatar"><img :src="c.avatar" alt="" class="w-7 h-7 object-cover"></template>
                                                <template x-if="!c.avatar"><i class="bi bi-person text-muted-foreground text-sm"></i></template>
                                            </span>
                                            <div class="bg-muted rounded-2xl px-3 py-2 min-w-0">
                                                <p class="text-[12px] font-semibold text-foreground" x-text="c.name"></p>
                                                <p class="text-[13px] text-gray-900 whitespace-pre-line break-words" x-text="c.body"></p>
                                            </div>
                                        </div>
                                    </template>
                                    <div class="flex items-center gap-2 pt-1">
                                        <span class="w-7 h-7 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                                            <template x-if="me.avatar"><img :src="me.avatar" alt="" class="w-7 h-7 object-cover"></template>
                                            <template x-if="!me.avatar"><i class="bi bi-person text-muted-foreground text-sm"></i></template>
                                        </span>
                                        <input type="text" x-model="draft" @keydown.enter.prevent="addC()"
                                               placeholder="{{ __('personal.write_comment') }}"
                                               class="flex-1 bg-muted rounded-full px-4 py-2 text-sm text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/40">
                                        <button type="button" @click="addC()" :disabled="!draft.trim()"
                                                class="m-press w-9 h-9 flex-shrink-0 rounded-full flex items-center justify-center transition-colors"
                                                :class="draft.trim() ? 'text-primary hover:bg-accent' : 'text-muted-foreground'" aria-label="{{ __('personal.send_comment') }}">
                                            <i class="bi bi-send-fill"></i>
                                        </button>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </div>
                </template>
            </div>
        </template>

        {{-- Member posts — Following or My Feeds tab (currentPosts is [] on All). --}}
        <template x-for="post in currentPosts" :key="post.id">
            @include('personal.partials.post-card')
        </template>

        {{-- Club timeline posts — Club tab only (All shows them in the stream above) --}}
        <div x-show="tab==='club'" class="space-y-3">
            @foreach($posts as $p)
                <article x-data="{ liked:false, likes:{{ (int) $p->likes_count }} }" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="flex items-start justify-between px-4 pt-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-10 h-10 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                                @if($p->tenant && $p->tenant->logo)
                                    <img src="{{ asset('storage/'.$p->tenant->logo) }}" alt="" class="w-10 h-10 object-cover">
                                @else
                                    <i class="bi bi-buildings text-muted-foreground"></i>
                                @endif
                            </span>
                            <div class="min-w-0">
                                <p class="font-semibold text-sm text-foreground truncate">{{ $p->tenant?->tr('club_name') ?? __('personal.club') }}</p>
                                <p class="text-[11px] text-gray-500 flex items-center gap-1">
                                    <span class="truncate">{{ $p->category ?? __('personal.update') }} · {{ optional($p->posted_at)->diffForHumans() }}</span>
                                    <i class="bi bi-globe2"></i>
                                </p>
                            </div>
                        </div>
                        <button type="button"
                                onclick="window.showToast && window.showToast('info', @js(__('personal.post_options_soon')))"
                                class="m-press w-8 h-8 -mr-1 flex items-center justify-center rounded-full text-gray-500 hover:bg-muted transition-colors flex-shrink-0">
                            <i class="bi bi-three-dots"></i>
                        </button>
                    </div>

                    @if($p->body)
                        <p class="text-[13px] text-gray-900 whitespace-pre-line px-4 py-2.5">{{ $p->body }}</p>
                    @endif

                    @if($p->image_path)
                        <button type="button" @click="openLightbox([{ url: '{{ asset('storage/'.$p->image_path) }}' }], 0)" class="block w-full">
                            <img src="{{ asset('storage/'.$p->image_path) }}" alt="" class="w-full max-h-96 object-cover">
                        </button>
                    @elseif($p->cover)
                        {{-- Gradient cover banner (animated floating icon) --}}
                        <div class="relative h-48 overflow-hidden flex flex-col justify-end p-4 text-white mt-1"
                             style="background: linear-gradient(135deg, {{ $p->cover['color'] }}, {{ $p->cover['color'] }}bb)">
                            <div class="absolute -right-8 -top-8 w-32 h-32 rounded-full bg-white/10"></div>
                            <div class="absolute right-6 bottom-10 w-20 h-20 rounded-full bg-white/10"></div>
                            <i class="bi {{ $p->cover['icon'] }} text-6xl opacity-90 absolute top-5 left-4 m-float"></i>
                            <span class="relative text-lg font-black drop-shadow">{{ $p->cover['label'] }}</span>
                        </div>
                    @endif

                    @if($p->likes_count || $p->comments_count)
                        <div class="flex items-center justify-between px-4 py-2 text-[12px] text-gray-500">
                            <span class="flex items-center gap-1.5" x-show="likes > 0" x-cloak>
                                <span class="w-4 h-4 rounded-full bg-primary text-white flex items-center justify-center text-[9px]">
                                    <i class="bi bi-heart-fill"></i>
                                </span>
                                <span x-text="likes"></span>
                            </span>
                            <span class="ml-auto">{{ $p->comments_count }} {{ \Illuminate\Support\Str::plural('comment', $p->comments_count) }}</span>
                        </div>
                    @endif

                    <div class="flex border-t border-gray-100 text-[13px] font-medium text-gray-600">
                        <button type="button"
                                @click="liked = !liked; likes += liked ? 1 : -1"
                                class="m-press flex-1 flex items-center justify-center gap-2 py-2.5 hover:bg-muted transition-colors"
                                :class="liked ? 'text-primary' : ''">
                            <i class="bi" :class="liked ? 'bi-heart-fill' : 'bi-heart'"></i> {{ __('personal.like') }}
                        </button>
                        <button type="button"
                                onclick="window.showToast && window.showToast('info', @js(__('personal.comments_soon')))"
                                class="m-press flex-1 flex items-center justify-center gap-2 py-2.5 hover:bg-muted transition-colors">
                            <i class="bi bi-chat"></i> {{ __('personal.comment') }}
                        </button>
                        <button type="button"
                                @click="sharePost({ body: @js($p->body ?? ($p->tenant?->tr('club_name') ?? __('personal.club_update'))) })"
                                class="m-press flex-1 flex items-center justify-center gap-2 py-2.5 hover:bg-muted transition-colors">
                            <i class="bi bi-share"></i> {{ __('personal.share') }}
                        </button>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Empty states --}}
        <div x-show="tab==='all' && allPosts.length===0" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-12 text-center">
            <i class="bi bi-newspaper text-4xl text-gray-300 m-float inline-block"></i>
            <p class="text-sm text-muted-foreground mt-3">{{ __('personal.nothing_here_yet') }}</p>
            <p class="text-[12px] text-gray-400 mt-1">{{ __('personal.share_post_hint') }}</p>
        </div>
        <div x-show="tab==='mine' && personalPosts.length===0" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-12 text-center">
            <i class="bi bi-newspaper text-4xl text-gray-300 m-float inline-block"></i>
            <p class="text-sm text-muted-foreground mt-3">{{ __('personal.nothing_here_yet') }}</p>
            <p class="text-[12px] text-gray-400 mt-1">{{ __('personal.share_post_hint') }}</p>
        </div>
        @if($posts->isEmpty())
        <div x-show="tab==='club'" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-12 text-center">
            <i class="bi bi-buildings text-4xl text-gray-300 m-float inline-block"></i>
            <p class="text-sm text-muted-foreground mt-3">{{ __('personal.club_empty') }}</p>
            <p class="text-[12px] text-gray-400 mt-1">{{ __('personal.club_empty_hint') }}</p>
        </div>
        @endif
        <div x-show="tab==='following' && followingPosts.length===0" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-12 text-center">
            <i class="bi bi-people text-4xl text-gray-300 m-float inline-block"></i>
            <p class="text-sm text-muted-foreground mt-3">{{ __('personal.following_empty') }}</p>
            <p class="text-[12px] text-gray-400 mt-1">{{ __('personal.following_empty_hint') }}</p>
        </div>
    </div>

    {{-- ===== Fullscreen image viewer (Facebook-style lightbox) ===== --}}
    <div x-show="lightbox.open" x-cloak class="fixed inset-0 z-[60] bg-black flex flex-col"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @keydown.escape.window="closeLightbox()">
        <div class="flex items-center justify-between px-4 h-14 text-white flex-shrink-0">
            <button type="button" @click="closeLightbox()" class="m-press w-10 h-10 -ml-2 rounded-full flex items-center justify-center hover:bg-white/10" aria-label="{{ __('personal.close') }}">
                <i class="bi bi-x-lg text-xl"></i>
            </button>
            <span class="text-sm font-medium" x-show="lightbox.images.length > 1"
                  x-text="(lightbox.index + 1) + ' / ' + lightbox.images.length"></span>
        </div>
        <div class="flex-1 relative flex items-center justify-center overflow-hidden" @click.self="closeLightbox()">
            <img :src="lightbox.images[lightbox.index]?.url" alt="" class="max-h-full max-w-full object-contain select-none">
            <button type="button" x-show="lightbox.index > 0" @click="lbPrev()"
                    class="m-press absolute left-2 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-black/40 text-white flex items-center justify-center hover:bg-black/60" aria-label="{{ __('personal.previous') }}">
                <i class="bi bi-chevron-left text-xl"></i>
            </button>
            <button type="button" x-show="lightbox.index < lightbox.images.length - 1" @click="lbNext()"
                    class="m-press absolute right-2 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-black/40 text-white flex items-center justify-center hover:bg-black/60" aria-label="{{ __('personal.next') }}">
                <i class="bi bi-chevron-right text-xl"></i>
            </button>
        </div>
    </div>

    {{-- "Seen by" viewers modal (owner taps a post's view count) --}}
    @include('personal.partials.post-viewers-modal')

    {{-- Script lives INSIDE #shell-content so the mobile shell's runScripts()
         re-executes it on each in-place AJAX navigation. --}}
    <script>
        window.newsFeed = function () {
            return {
                me: { name: @js($me->full_name), avatar: @js($myAvatar) },
                isSuperAdmin: @js((bool) (auth()->user()?->hasRole('super-admin'))),
                csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
                routes: {
                    store: @js(route('me.posts.store')),
                    base:  @js(url('/me/posts')),
                    wall:  @js(url('/u')),
                },
                tab: 'all',
                dots: @js($feedTabDots ?? ['all' => false, 'club' => false, 'following' => false, 'mine' => false]),
                seenTab(t) {
                    if (!this.dots[t]) return;
                    this.dots[t] = false;
                    fetch(@js(route('me.seen')), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ section: 'feed:' + t }) }).catch(() => {});
                },
                body: '',
                attachOpen: false,
                sending: false,
                images: [],            // { url, file } previews for the next post
                pollOpen: false,       // poll builder visible?
                pollOptions: ['', ''], // poll choices (2..6)
                highlightOpen: false,  // highlight (gradient cover) builder visible?
                coverLabel: '',
                coverColor: '#7c3aed',
                coverIcon: 'bi-stars',
                coverColors: ['#7c3aed', '#ec4899', '#0ea5e9', '#10b981', '#ef4444', '#f59e0b', '#6d28d9'],
                coverIcons: ['bi-stars', 'bi-trophy-fill', 'bi-fire', 'bi-heart-fill', 'bi-lightning-charge-fill', 'bi-flower1', 'bi-cup-straw', 'bi-award-fill', 'bi-balloon-heart-fill', 'bi-emoji-sunglasses'],
                personalPosts:  @js($personalPosts ?? []),   // My Feeds (seeded, persists)
                followingPosts: @js($followingPosts ?? []),  // Following feed
                allPosts:       @js($allPosts ?? []),        // All — unified, date-sorted (club + club-mates + you)
                suggestions:    @js($suggestions ?? []),     // club-mates to follow
                lightbox: { open: false, images: [], index: 0 },

                get currentPosts() {
                    // The All tab renders from its own unified `allPosts` stream
                    // (mixed member/club cards), so this only serves Mine/Following.
                    if (this.tab === 'mine') return this.personalPosts;
                    if (this.tab === 'following') return this.followingPosts;
                    return [];
                },

                init() {
                    // Live feed updates over MQTT (realtime.js → 'realtime:posts').
                    window.addEventListener('realtime:posts', (e) => this.onRealtimePost(e.detail || {}));
                },
                onRealtimePost(d) {
                    if (d.action === 'new' && d.post) {
                        d.post.author.isMe = false;
                        // Server tags which feeds this recipient should patch
                        // (followers → following+all, club-mates → all only).
                        const feeds = d.feeds || ['following', 'all'];
                        if (feeds.includes('following') && !this.followingPosts.some(p => p.id === d.post.id)) {
                            this.followingPosts.unshift(d.post);
                        }
                        if (feeds.includes('all') && !this.allPosts.some(p => p.kind === 'member' && p.id === d.post.id)) {
                            this.allPosts.unshift({ ...d.post, kind: 'member' });
                        }
                        return;
                    }
                    const id = d.post_id;
                    if (d.action === 'delete') {
                        this.personalPosts  = this.personalPosts.filter(p => p.id !== id);
                        this.followingPosts = this.followingPosts.filter(p => p.id !== id);
                        this.allPosts       = this.allPosts.filter(p => !(p.kind === 'member' && p.id === id));
                        return;
                    }
                    // Moderation: a super-admin keeps a hidden post (flagged); everyone
                    // else drops it from their feeds live.
                    if (d.action === 'hide') {
                        if (this.isSuperAdmin) { this.patchHidden(id, true); }
                        else {
                            this.personalPosts  = this.personalPosts.filter(p => p.id !== id);
                            this.followingPosts = this.followingPosts.filter(p => p.id !== id);
                            this.allPosts       = this.allPosts.filter(p => !(p.kind === 'member' && p.id === id));
                        }
                        return;
                    }
                    // Unhide: super-admins just clear the flag; everyone else gets the
                    // post re-inserted (the server sends the card).
                    if (d.action === 'unhide') {
                        if (this.isSuperAdmin) { this.patchHidden(id, false); return; }
                        if (d.post) {
                            d.post.author.isMe = false; d.post.hidden = false;
                            if (!this.followingPosts.some(p => p.id === id)) this.followingPosts.unshift(d.post);
                            if (!this.allPosts.some(p => p.kind === 'member' && p.id === id)) this.allPosts.unshift({ ...d.post, kind: 'member' });
                        }
                        return;
                    }
                    // Patch member posts across every array; never touch club items
                    // (a club post can share an id with a member post — different tables).
                    [this.personalPosts, this.followingPosts, this.allPosts].forEach(arr => {
                        const p = arr.find(x => x.id === id && x.kind !== 'club');
                        if (!p) return;
                        if (d.action === 'like') p.likes = d.likes;
                        else if (d.action === 'comment') { if (!p.comments.some(c => c.id === d.comment.id)) p.comments.push(d.comment); }
                        else if (d.action === 'edit') { p.body = d.body; p.edited = true; }
                        else if (d.action === 'poll' && p.poll && d.poll) {
                            // Update shared tallies; keep my own vote.
                            const mine = p.poll.myVote;
                            p.poll = { ...d.poll, myVote: mine };
                        }
                        else if (d.action === 'view' && typeof d.views === 'number') p.views = d.views;
                    });
                },

                autoGrow(el) {
                    el.style.height = 'auto';
                    el.style.height = el.scrollHeight + 'px';
                },

                pickImages(event) {
                    const files = Array.from(event.target.files || []);
                    files.forEach(f => {
                        if (f.type.startsWith('image/')) {
                            this.images.push({ url: URL.createObjectURL(f), file: f });
                        }
                    });
                    event.target.value = '';
                    this.attachOpen = false;
                },

                removeImage(i) {
                    const [removed] = this.images.splice(i, 1);
                    if (removed) URL.revokeObjectURL(removed.url);
                },

                canPost() {
                    if (this.pollOpen) {
                        return this.body.trim().length > 0
                            && this.pollOptions.filter(o => o.trim()).length >= 2;
                    }
                    if (this.highlightOpen) {
                        return this.coverLabel.trim().length > 0;
                    }
                    return this.body.trim().length > 0 || this.images.length > 0;
                },

                // ----- Highlight (gradient cover) builder -----
                toggleHighlight() {
                    this.highlightOpen = !this.highlightOpen;
                    if (this.highlightOpen) {
                        this.attachOpen = false;
                        this.pollOpen = false;
                        // Highlight & photos are mutually exclusive.
                        this.images.forEach(img => URL.revokeObjectURL(img.url));
                        this.images = [];
                    }
                },

                // ----- Poll builder -----
                togglePoll() {
                    this.pollOpen = !this.pollOpen;
                    if (this.pollOpen) {
                        this.attachOpen = false;
                        this.highlightOpen = false;
                        // Poll & photos are mutually exclusive — clear any picked images.
                        this.images.forEach(img => URL.revokeObjectURL(img.url));
                        this.images = [];
                        if (this.pollOptions.length < 2) this.pollOptions = ['', ''];
                    }
                },
                addOption() { if (this.pollOptions.length < 6) this.pollOptions.push(''); },
                removeOption(i) { if (this.pollOptions.length > 2) this.pollOptions.splice(i, 1); },

                // Fetch helper — JSON body, returns parsed JSON (throws on failure).
                async send(url, { method = 'POST', body = null, json = true } = {}) {
                    const headers = { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' };
                    if (json && body) headers['Content-Type'] = 'application/json';
                    const res = await fetch(url, {
                        method, headers, credentials: 'same-origin',
                        body: json && body ? JSON.stringify(body) : body,
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || data.success === false) {
                        throw new Error(data.message || @js(__('shared.error')));
                    }
                    return data;
                },

                async submitPost() {
                    if (!this.canPost() || this.sending) return;
                    this.sending = true;
                    try {
                        const fd = new FormData();
                        fd.append('body', this.body.trim());
                        if (this.pollOpen) {
                            fd.append('type', 'poll');
                            this.pollOptions.map(o => o.trim()).filter(Boolean)
                                .forEach(o => fd.append('poll_options[]', o));
                        } else if (this.highlightOpen) {
                            fd.append('type', 'highlight');
                            fd.append('cover_label', this.coverLabel.trim());
                            fd.append('cover_color', this.coverColor);
                            fd.append('cover_icon', this.coverIcon);
                        } else {
                            this.images.forEach(img => fd.append('images[]', img.file));
                        }
                        const data = await this.send(this.routes.store, { method: 'POST', body: fd, json: false });
                        this.tab = 'mine';
                        this.personalPosts.unshift(data.post);
                        this.allPosts.unshift({ ...data.post, kind: 'member' });
                        this.images.forEach(img => URL.revokeObjectURL(img.url));
                        this.body = '';
                        this.images = [];
                        this.attachOpen = false;
                        this.pollOpen = false;
                        this.pollOptions = ['', ''];
                        this.highlightOpen = false;
                        this.coverLabel = '';
                        this.coverColor = '#7c3aed';
                        this.coverIcon = 'bi-stars';
                        if (this.$refs.ta) this.$refs.ta.style.height = 'auto';
                        window.showToast && window.showToast('success', data.message || @js(__('personal.post_shared')));
                    } catch (e) {
                        window.showToast && window.showToast('error', e.message);
                    } finally {
                        this.sending = false;
                    }
                },

                // ----- Poll voting -----
                async votePoll(post, i) {
                    if (!post.poll || post.demo) {   // demo polls: vote locally
                        if (post.poll && post.poll.myVote === null) {
                            post.poll.options[i].votes++;
                            post.poll.totalVotes++;
                            post.poll.myVote = i;
                        }
                        return;
                    }
                    const prev = post.poll.myVote;
                    if (prev === i) return;
                    // Optimistic update
                    post.poll.options[i].votes++;
                    if (prev !== null && post.poll.options[prev]) post.poll.options[prev].votes--;
                    else post.poll.totalVotes++;
                    post.poll.myVote = i;
                    try {
                        const data = await this.send(`${this.routes.base}/${post.id}/vote`, {
                            method: 'POST', body: { option: i },
                        });
                        post.poll = data.poll;
                    } catch (e) {
                        post.poll.myVote = prev;   // revert on failure
                        window.showToast && window.showToast('error', e.message);
                    }
                },

                // ----- Edit / delete (own posts only) -----
                startEdit(post) {
                    post.draft = post.body;
                    post.editing = true;
                },
                async saveEdit(post) {
                    if (post.demo) {   // demo posts: apply edit locally
                        post.body = (post.draft || '').trim();
                        post.edited = true;
                        post.editing = false;
                        window.showToast && window.showToast('success', @js(__('personal.post_updated')));
                        return;
                    }
                    try {
                        const data = await this.send(`${this.routes.base}/${post.id}`, {
                            method: 'PUT', body: { body: (post.draft || '').trim() },
                        });
                        Object.assign(post, data.post);
                        post.editing = false;
                        window.showToast && window.showToast('success', data.message || @js(__('personal.post_updated')));
                    } catch (e) {
                        window.showToast && window.showToast('error', e.message);
                    }
                },
                async deletePost(post) {
                    const ok = await window.confirmAction({
                        title: @js(__('personal.delete_post')),
                        message: @js(__('personal.delete_post_confirm')),
                        type: 'danger', confirmText: @js(__('personal.delete')),
                    });
                    if (!ok) return;
                    const removeLocal = () => {
                        this.personalPosts  = this.personalPosts.filter(p => p.id !== post.id);
                        this.followingPosts = this.followingPosts.filter(p => p.id !== post.id);
                        this.allPosts       = this.allPosts.filter(p => !(p.kind === 'member' && p.id === post.id));
                    };
                    if (post.demo) {   // demo posts: remove locally
                        removeLocal();
                        window.showToast && window.showToast('success', @js(__('personal.post_deleted')));
                        return;
                    }
                    try {
                        await this.send(`${this.routes.base}/${post.id}`, { method: 'DELETE' });
                        this.personalPosts = this.personalPosts.filter(p => p.id !== post.id);
                        this.allPosts      = this.allPosts.filter(p => !(p.kind === 'member' && p.id === post.id));
                        window.showToast && window.showToast('success', @js(__('personal.post_deleted')));
                    } catch (e) {
                        window.showToast && window.showToast('error', e.message);
                    }
                },

                // Super-admin moderation: remove any member's post for everyone.
                async adminDeletePost(post) {
                    const ok = await window.confirmAction({
                        title: @js(__('personal.admin_remove_post')),
                        message: @js(__('personal.admin_remove_post_confirm')),
                        type: 'danger', confirmText: @js(__('personal.delete')),
                    });
                    if (!ok) return;
                    try {
                        await this.send(`${this.routes.base}/${post.id}`, { method: 'DELETE' });
                        this.personalPosts  = this.personalPosts.filter(p => p.id !== post.id);
                        this.followingPosts = this.followingPosts.filter(p => p.id !== post.id);
                        this.allPosts       = this.allPosts.filter(p => !(p.kind === 'member' && p.id === post.id));
                        window.showToast && window.showToast('success', @js(__('personal.post_deleted')));
                    } catch (e) {
                        window.showToast && window.showToast('error', e.message);
                    }
                },

                // Super-admin moderation: hide a post from everyone (reversible).
                async hidePost(post) {
                    const ok = await window.confirmAction({
                        title: @js(__('personal.hide_post')),
                        message: @js(__('personal.hide_post_confirm')),
                        type: 'warning', confirmText: @js(__('personal.hide_confirm_btn')),
                    });
                    if (!ok) return;
                    try {
                        await this.send(`${this.routes.base}/${post.id}/hide`, { method: 'POST' });
                        this.patchHidden(post.id, true);
                        window.showToast && window.showToast('success', @js(__('personal.post_hidden')));
                    } catch (e) {
                        window.showToast && window.showToast('error', e.message);
                    }
                },

                // Super-admin moderation: restore a hidden post for everyone.
                async unhidePost(post) {
                    try {
                        await this.send(`${this.routes.base}/${post.id}/unhide`, { method: 'POST' });
                        this.patchHidden(post.id, false);
                        window.showToast && window.showToast('success', @js(__('personal.post_unhidden')));
                    } catch (e) {
                        window.showToast && window.showToast('error', e.message);
                    }
                },

                // Flip the moderation flag on a member post across every feed array.
                patchHidden(id, hidden) {
                    [this.personalPosts, this.followingPosts, this.allPosts].forEach(arr => {
                        const p = arr.find(x => x.id === id && x.kind !== 'club');
                        if (p) p.hidden = hidden;
                    });
                },

                // ----- Likes -----
                async toggleLike(post) {
                    post.liked = !post.liked;
                    post.likes += post.liked ? 1 : -1;
                    if (post.demo) return;   // demo posts: local-only, no server call
                    try {
                        const data = await this.send(`${this.routes.base}/${post.id}/like`);
                        post.liked = data.liked;
                        post.likes = data.likes;
                    } catch (e) {
                        post.liked = !post.liked;
                        post.likes += post.liked ? 1 : -1;
                        window.showToast && window.showToast('error', e.message);
                    }
                },

                // ----- Comments -----
                async addComment(post) {
                    const text = (post.commentDraft || '').trim();
                    if (!text) return;
                    if (post.demo) {   // demo posts: append comment locally
                        post.comments.push({ id: Date.now(), name: this.me.name, avatar: this.me.avatar, body: text });
                        post.commentDraft = '';
                        post.showComments = true;
                        return;
                    }
                    try {
                        const data = await this.send(`${this.routes.base}/${post.id}/comment`, {
                            method: 'POST', body: { body: text },
                        });
                        post.comments.push(data.comment);
                        post.commentDraft = '';
                        post.showComments = true;
                    } catch (e) {
                        window.showToast && window.showToast('error', e.message);
                    }
                },

                // ----- Discovery: follow / unfollow from the rail -----
                async followSuggestion(s) {
                    try {
                        await this.send(`${this.routes.wall}/${s.slug}/follow`);
                        s.following = true;
                        window.showToast && window.showToast('success', @js(__('personal.following_name')).replace(':name', s.name.split(' ')[0]));
                    } catch (e) { window.showToast && window.showToast('error', e.message); }
                },
                async unfollowSuggestion(s) {
                    try {
                        await this.send(`${this.routes.wall}/${s.slug}/follow`, { method: 'DELETE' });
                        s.following = false;
                    } catch (e) { window.showToast && window.showToast('error', e.message); }
                },

                // ----- Block an author straight from the feed -----
                async blockAuthor(post) {
                    const name = post.author.name;
                    if (post.demo) {   // demo posts: hide locally
                        this.followingPosts = this.followingPosts.filter(p => p.author.id !== post.author.id);
                        this.allPosts       = this.allPosts.filter(p => !(p.kind === 'member' && p.author.id === post.author.id));
                        window.showToast && window.showToast('success', @js(__('personal.blocked')).replace(':name', name));
                        return;
                    }
                    const ok = await window.confirmAction({
                        title: @js(__('personal.block_x')).replace(':name', name),
                        message: @js(__('personal.block_confirm')),
                        type: 'danger', confirmText: @js(__('personal.block')),
                    });
                    if (!ok) return;
                    try {
                        await this.send(`${this.routes.wall}/${post.author.slug}/block`);
                        const id = post.author.id;
                        this.personalPosts  = this.personalPosts.filter(p => p.author.id !== id);
                        this.followingPosts = this.followingPosts.filter(p => p.author.id !== id);
                        this.allPosts       = this.allPosts.filter(p => !(p.kind === 'member' && p.author.id === id));
                        window.showToast && window.showToast('success', @js(__('personal.blocked')).replace(':name', name));
                    } catch (e) {
                        window.showToast && window.showToast('error', e.message);
                    }
                },

                // ----- Open the post's own page (permalink) -----
                openPost(post) {
                    if (post && post.demo) return;   // demo posts have no permalink
                    if (post && post.url) window.location.href = post.url;
                },

                // ----- Share the post's permalink (native share sheet, else copy) -----
                sharePost(post) {
                    const url = (post.url && post.url !== '#') ? post.url : window.location.href;
                    const text = (post.body || '').trim() || @js(__('personal.check_out_post'));
                    if (navigator.share) {
                        navigator.share({ text, url }).catch(() => {});
                    } else if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(url)
                            .then(() => window.showToast && window.showToast('success', @js(__('personal.link_copied'))))
                            .catch(() => window.showToast && window.showToast('info', @js(__('personal.could_not_share'))));
                    } else {
                        window.showToast && window.showToast('info', @js(__('personal.sharing_not_supported')));
                    }
                },

                // ----- Lightbox -----
                openLightbox(images, index) { this.lightbox = { open: true, images: images, index: index || 0 }; },
                closeLightbox() { this.lightbox.open = false; },
                lbNext() { if (this.lightbox.index < this.lightbox.images.length - 1) this.lightbox.index++; },
                lbPrev() { if (this.lightbox.index > 0) this.lightbox.index--; },
            };
        };
    </script>
</div>
@endsection
