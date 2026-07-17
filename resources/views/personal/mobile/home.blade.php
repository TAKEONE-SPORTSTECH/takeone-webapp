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
    @include('partials.news-feed-script')
</div>
@endsection
