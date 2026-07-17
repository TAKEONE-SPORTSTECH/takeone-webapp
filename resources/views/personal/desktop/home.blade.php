@extends('layouts.app')

@section('title', 'News Feed')

@php
    $me = $user ?? Auth::user();
    $myAvatar = $me->profile_picture ? asset('storage/'.$me->profile_picture).'?v='.optional($me->updated_at)->timestamp : null;
@endphp

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6" x-data="newsFeed()">

    @include('partials.personal-desktop-subnav')

    <div class="flex gap-6 justify-center items-start">
        {{-- ===== Main feed column ===== --}}
        <div class="w-full max-w-2xl space-y-4">

            {{-- ===== Post composer ===== --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-4">
                <input type="file" x-ref="photo" accept="image/*" multiple class="hidden" @change="pickImages($event)">

                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                        @if($myAvatar)<img src="{{ $myAvatar }}" alt="" class="w-10 h-10 object-cover">@else<i class="bi bi-person text-muted-foreground"></i>@endif
                    </span>
                    <textarea x-model="body" x-ref="ta" rows="1" @input="autoGrow($el)"
                              :placeholder="pollOpen ? @js(__('personal.poll_question')) : @js(__('personal.whats_on_your_mind'))"
                              class="flex-1 resize-none max-h-40 bg-muted rounded-2xl px-4 py-2.5 text-sm text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/40 transition-shadow"></textarea>
                </div>

                {{-- Selected image thumbnails --}}
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

                {{-- Inline poll builder --}}
                <div x-show="pollOpen" x-cloak class="mt-3 space-y-2 ps-[52px]"
                     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="flex items-center gap-2 text-[12px] font-semibold text-primary">
                        <i class="bi bi-bar-chart-fill"></i> {{ __('personal.create_poll') }}
                    </div>
                    <template x-for="(opt, i) in pollOptions" :key="i">
                        <div class="flex items-center gap-2">
                            <input type="text" x-model="pollOptions[i]" maxlength="120"
                                   :placeholder="@js(__('personal.poll_option')).replace(':n', i + 1)"
                                   class="flex-1 bg-muted rounded-xl px-3.5 py-2 text-sm text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/40">
                            <button type="button" x-show="pollOptions.length > 2" @click="removeOption(i)"
                                    class="w-8 h-8 flex-shrink-0 rounded-full flex items-center justify-center text-muted-foreground hover:bg-muted transition-colors"
                                    aria-label="{{ __('personal.remove_option') }}">
                                <i class="bi bi-x-lg text-sm"></i>
                            </button>
                        </div>
                    </template>
                    <button type="button" x-show="pollOptions.length < 6" @click="addOption()"
                            class="flex items-center gap-2 py-1 text-[13px] font-medium text-primary hover:opacity-80 transition-opacity">
                        <i class="bi bi-plus-circle"></i> {{ __('personal.add_option') }}
                    </button>
                </div>

                {{-- Inline highlight builder --}}
                <div x-show="highlightOpen" x-cloak class="mt-3 space-y-2.5 ps-[52px]"
                     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="flex items-center gap-2 text-[12px] font-semibold text-primary">
                        <i class="bi bi-stars"></i> {{ __('personal.create_highlight') }}
                    </div>
                    <div class="relative h-36 rounded-xl overflow-hidden flex flex-col justify-end p-4 text-white"
                         :style="`background: linear-gradient(135deg, ${coverColor}, ${coverColor}bb)`">
                        <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10"></div>
                        <div class="absolute right-6 bottom-8 w-16 h-16 rounded-full bg-white/10"></div>
                        <i class="bi text-5xl opacity-90 absolute top-4 left-4" :class="coverIcon"></i>
                        <span class="relative text-lg font-black drop-shadow" x-text="coverLabel || @js(__('personal.highlight'))"></span>
                    </div>
                    <input type="text" x-model="coverLabel" maxlength="80"
                           placeholder="{{ __('personal.highlight_title') }}"
                           class="w-full bg-muted rounded-xl px-3.5 py-2 text-sm text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/40">
                    <div class="flex items-center gap-2 overflow-x-auto scrollbar-hide pb-0.5">
                        <template x-for="c in coverColors" :key="c">
                            <button type="button" @click="coverColor = c"
                                    class="w-7 h-7 flex-shrink-0 rounded-full border-2 transition-transform"
                                    :class="coverColor === c ? 'border-foreground scale-110' : 'border-transparent'"
                                    :style="`background: ${c}`" aria-label="{{ __('personal.pick_a_color') }}"></button>
                        </template>
                    </div>
                    <div class="flex items-center gap-1.5 overflow-x-auto scrollbar-hide pb-0.5">
                        <template x-for="ic in coverIcons" :key="ic">
                            <button type="button" @click="coverIcon = ic"
                                    class="w-8 h-8 flex-shrink-0 rounded-lg grid place-items-center transition-colors"
                                    :class="coverIcon === ic ? 'bg-accent text-primary' : 'bg-muted text-muted-foreground'">
                                <i class="bi" :class="ic"></i>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Action row --}}
                <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100 ps-[52px]">
                    <div class="flex items-center gap-1">
                        <button type="button" @click="$refs.photo.click()"
                                class="flex items-center gap-2 px-3 py-1.5 rounded-lg hover:bg-muted transition-colors text-sm font-medium text-muted-foreground">
                            <i class="bi bi-image text-green-500 text-lg"></i> {{ __('personal.photo') }}
                        </button>
                        <button type="button" @click="togglePoll()"
                                class="flex items-center gap-2 px-3 py-1.5 rounded-lg hover:bg-muted transition-colors text-sm font-medium"
                                :class="pollOpen ? 'text-primary' : 'text-muted-foreground'">
                            <i class="bi bi-bar-chart text-primary text-lg"></i> {{ __('personal.poll') }}
                        </button>
                        <button type="button" @click="toggleHighlight()"
                                class="flex items-center gap-2 px-3 py-1.5 rounded-lg hover:bg-muted transition-colors text-sm font-medium"
                                :class="highlightOpen ? 'text-primary' : 'text-muted-foreground'">
                            <i class="bi bi-stars text-amber-500 text-lg"></i> {{ __('personal.highlight') }}
                        </button>
                    </div>
                    <button type="button" @click="submitPost()" :disabled="!canPost() || sending"
                            class="px-5 py-2 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2"
                            :class="(canPost() && !sending) ? 'bg-primary text-white hover:bg-primary/90' : 'bg-muted text-muted-foreground cursor-not-allowed'">
                        <i class="bi" :class="sending ? 'bi-arrow-repeat animate-spin' : 'bi-send-fill'"></i> {{ __('personal.send_post') }}
                    </button>
                </div>
            </div>

            {{-- ===== Feed tabs ===== --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-1 flex gap-1">
                <button type="button" @click="tab='all'; seenTab('all')"
                        class="relative flex-1 py-2 rounded-xl text-sm font-semibold transition-colors"
                        :class="tab==='all' ? 'bg-primary text-white' : 'text-muted-foreground hover:bg-muted'">
                    {{ __('personal.all') }}
                    <span x-show="dots.all && tab!=='all'" x-cloak class="absolute top-1.5 right-2 w-2 h-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                </button>
                <button type="button" @click="tab='club'; seenTab('club')"
                        class="relative flex-1 py-2 rounded-xl text-sm font-semibold transition-colors"
                        :class="tab==='club' ? 'bg-primary text-white' : 'text-muted-foreground hover:bg-muted'">
                    {{ __('personal.club') }}
                    <span x-show="dots.club && tab!=='club'" x-cloak class="absolute top-1.5 right-2 w-2 h-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                </button>
                <button type="button" @click="tab='following'; seenTab('following')"
                        class="relative flex-1 py-2 rounded-xl text-sm font-semibold transition-colors"
                        :class="tab==='following' ? 'bg-primary text-white' : 'text-muted-foreground hover:bg-muted'">
                    {{ __('personal.following') }}
                    <span x-show="dots.following && tab!=='following'" x-cloak class="absolute top-1.5 right-2 w-2 h-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                </button>
                <button type="button" @click="tab='mine'; seenTab('mine')"
                        class="relative flex-1 py-2 rounded-xl text-sm font-semibold transition-colors"
                        :class="tab==='mine' ? 'bg-primary text-white' : 'text-muted-foreground hover:bg-muted'">
                    {{ __('personal.my_feeds') }}
                    <span x-show="dots.mine && tab!=='mine'" x-cloak class="absolute top-1.5 right-2 w-2 h-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                </button>
            </div>

            {{-- ===== Feed posts ===== --}}
            <div class="space-y-4">

                <template x-if="tab==='all'">
                    <div class="space-y-4">
                        <template x-for="post in allPosts" :key="post.kind + '-' + post.id">
                            <div>
                                <template x-if="post.kind === 'member'">
                                    @include('personal.partials.post-card')
                                </template>

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
                                                    class="w-8 h-8 -mr-1 flex items-center justify-center rounded-full text-gray-500 hover:bg-muted transition-colors flex-shrink-0">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                        </div>

                                        <p x-show="post.body" class="text-sm text-gray-900 whitespace-pre-line px-4 py-2.5" x-text="post.body"></p>

                                        <template x-if="post.image">
                                            <button type="button" @click="openLightbox([{ url: post.image }], 0)" class="block w-full">
                                                <img :src="post.image" alt="" class="w-full max-h-[28rem] object-cover">
                                            </button>
                                        </template>

                                        <template x-if="post.cover && !post.image">
                                            <div class="relative h-56 overflow-hidden flex flex-col justify-end p-5 text-white mt-1"
                                                 :style="`background: linear-gradient(135deg, ${post.cover.color}, ${post.cover.color}bb)`">
                                                <div class="absolute -right-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
                                                <div class="absolute right-6 bottom-10 w-24 h-24 rounded-full bg-white/10"></div>
                                                <i class="bi text-7xl opacity-90 absolute top-5 left-5" :class="post.cover.icon"></i>
                                                <span class="relative text-xl font-black drop-shadow" x-text="post.cover.label"></span>
                                            </div>
                                        </template>

                                        <div x-show="likes > 0 || cc > 0" x-cloak class="flex items-center justify-between px-4 py-2 text-xs text-gray-500">
                                            <span class="flex items-center gap-1.5" x-show="likes > 0">
                                                <span class="w-4 h-4 rounded-full bg-primary text-white flex items-center justify-center text-[9px]">
                                                    <i class="bi bi-heart-fill"></i>
                                                </span>
                                                <span x-text="likes"></span>
                                            </span>
                                            <button type="button" class="ml-auto hover:underline" x-show="cc > 0" @click="showC = true"
                                                  x-text="cc + ' ' + (cc === 1 ? @js(__('personal.comment_one')) : @js(__('personal.comments_many')))"></button>
                                        </div>

                                        <div class="flex border-t border-gray-100 text-sm font-medium text-gray-600">
                                            <button type="button" @click="liked = !liked; likes += liked ? 1 : -1"
                                                    class="flex-1 flex items-center justify-center gap-2 py-2.5 hover:bg-muted transition-colors"
                                                    :class="liked ? 'text-primary' : ''">
                                                <i class="bi" :class="liked ? 'bi-heart-fill' : 'bi-heart'"></i> {{ __('personal.like') }}
                                            </button>
                                            <button type="button" @click="showC = !showC"
                                                    class="flex-1 flex items-center justify-center gap-2 py-2.5 hover:bg-muted transition-colors">
                                                <i class="bi bi-chat"></i> {{ __('personal.comment') }}
                                            </button>
                                            <button type="button" @click="sharePost({ body: post.body || post.club.name })"
                                                    class="flex-1 flex items-center justify-center gap-2 py-2.5 hover:bg-muted transition-colors">
                                                <i class="bi bi-share"></i> {{ __('personal.share') }}
                                            </button>
                                        </div>

                                        <div x-show="showC" x-cloak class="px-4 pb-3 pt-2 border-t border-gray-100 space-y-2">
                                            <template x-for="c in cmts" :key="c.id">
                                                <div class="flex items-start gap-2">
                                                    <span class="w-7 h-7 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                                                        <template x-if="c.avatar"><img :src="c.avatar" alt="" class="w-7 h-7 object-cover"></template>
                                                        <template x-if="!c.avatar"><i class="bi bi-person text-muted-foreground text-sm"></i></template>
                                                    </span>
                                                    <div class="bg-muted rounded-2xl px-3 py-2 min-w-0">
                                                        <p class="text-xs font-semibold text-foreground" x-text="c.name"></p>
                                                        <p class="text-sm text-gray-900 whitespace-pre-line break-words" x-text="c.body"></p>
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
                                                        class="w-9 h-9 flex-shrink-0 rounded-full flex items-center justify-center transition-colors"
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

                <template x-for="post in currentPosts" :key="post.id">
                    @include('personal.partials.post-card')
                </template>

                <div x-show="tab==='club'" class="space-y-4">
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
                                        class="w-8 h-8 -mr-1 flex items-center justify-center rounded-full text-gray-500 hover:bg-muted transition-colors flex-shrink-0">
                                    <i class="bi bi-three-dots"></i>
                                </button>
                            </div>

                            @if($p->body)
                                <p class="text-sm text-gray-900 whitespace-pre-line px-4 py-2.5">{{ $p->body }}</p>
                            @endif

                            @if($p->image_path)
                                <button type="button" @click="openLightbox([{ url: '{{ asset('storage/'.$p->image_path) }}' }], 0)" class="block w-full">
                                    <img src="{{ asset('storage/'.$p->image_path) }}" alt="" class="w-full max-h-[28rem] object-cover">
                                </button>
                            @elseif($p->cover)
                                <div class="relative h-56 overflow-hidden flex flex-col justify-end p-5 text-white mt-1"
                                     style="background: linear-gradient(135deg, {{ $p->cover['color'] }}, {{ $p->cover['color'] }}bb)">
                                    <div class="absolute -right-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
                                    <div class="absolute right-6 bottom-10 w-24 h-24 rounded-full bg-white/10"></div>
                                    <i class="bi {{ $p->cover['icon'] }} text-7xl opacity-90 absolute top-5 left-5"></i>
                                    <span class="relative text-xl font-black drop-shadow">{{ $p->cover['label'] }}</span>
                                </div>
                            @endif

                            @if($p->likes_count || $p->comments_count)
                                <div class="flex items-center justify-between px-4 py-2 text-xs text-gray-500">
                                    <span class="flex items-center gap-1.5" x-show="likes > 0" x-cloak>
                                        <span class="w-4 h-4 rounded-full bg-primary text-white flex items-center justify-center text-[9px]">
                                            <i class="bi bi-heart-fill"></i>
                                        </span>
                                        <span x-text="likes"></span>
                                    </span>
                                    <span class="ml-auto">{{ $p->comments_count }} {{ \Illuminate\Support\Str::plural('comment', $p->comments_count) }}</span>
                                </div>
                            @endif

                            <div class="flex border-t border-gray-100 text-sm font-medium text-gray-600">
                                <button type="button" @click="liked = !liked; likes += liked ? 1 : -1"
                                        class="flex-1 flex items-center justify-center gap-2 py-2.5 hover:bg-muted transition-colors"
                                        :class="liked ? 'text-primary' : ''">
                                    <i class="bi" :class="liked ? 'bi-heart-fill' : 'bi-heart'"></i> {{ __('personal.like') }}
                                </button>
                                <button type="button"
                                        onclick="window.showToast && window.showToast('info', @js(__('personal.comments_soon')))"
                                        class="flex-1 flex items-center justify-center gap-2 py-2.5 hover:bg-muted transition-colors">
                                    <i class="bi bi-chat"></i> {{ __('personal.comment') }}
                                </button>
                                <button type="button" @click="sharePost({ body: @js($p->body ?? ($p->tenant?->tr('club_name') ?? __('personal.club_update'))) })"
                                        class="flex-1 flex items-center justify-center gap-2 py-2.5 hover:bg-muted transition-colors">
                                    <i class="bi bi-share"></i> {{ __('personal.share') }}
                                </button>
                            </div>
                        </article>
                    @endforeach
                </div>

                {{-- Empty states --}}
                <div x-show="tab==='all' && allPosts.length===0" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-16 text-center">
                    <i class="bi bi-newspaper text-4xl text-gray-300"></i>
                    <p class="text-sm text-muted-foreground mt-3">{{ __('personal.nothing_here_yet') }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ __('personal.share_post_hint') }}</p>
                </div>
                <div x-show="tab==='mine' && personalPosts.length===0" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-16 text-center">
                    <i class="bi bi-newspaper text-4xl text-gray-300"></i>
                    <p class="text-sm text-muted-foreground mt-3">{{ __('personal.nothing_here_yet') }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ __('personal.share_post_hint') }}</p>
                </div>
                @if($posts->isEmpty())
                <div x-show="tab==='club'" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-16 text-center">
                    <i class="bi bi-buildings text-4xl text-gray-300"></i>
                    <p class="text-sm text-muted-foreground mt-3">{{ __('personal.club_empty') }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ __('personal.club_empty_hint') }}</p>
                </div>
                @endif
                <div x-show="tab==='following' && followingPosts.length===0" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-16 text-center">
                    <i class="bi bi-people text-4xl text-gray-300"></i>
                    <p class="text-sm text-muted-foreground mt-3">{{ __('personal.following_empty') }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ __('personal.following_empty_hint') }}</p>
                </div>
            </div>
        </div>

        {{-- ===== Right sidebar ===== --}}
        <aside class="hidden xl:block w-[320px] flex-shrink-0 space-y-4 sticky top-20 self-start">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2 mb-1">
                    <i class="bi bi-people-fill text-primary"></i>{{ __('personal.suggested_for_you') }}
                </h3>
                <template x-if="suggestions.length === 0">
                    <p class="text-xs text-muted-foreground py-2">{{ __('personal.people_no_club_desc') }}</p>
                </template>
                <div class="divide-y divide-gray-50">
                    <template x-for="s in suggestions.slice(0, 6)" :key="s.id">
                        <div class="flex items-center gap-3 py-2.5">
                            <a :href="s.url" class="flex items-center gap-3 min-w-0 flex-1">
                                <span class="w-9 h-9 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                                    <template x-if="s.avatar"><img :src="s.avatar" alt="" class="w-9 h-9 object-cover"></template>
                                    <template x-if="!s.avatar"><i class="bi bi-person-fill text-primary/60"></i></template>
                                </span>
                                <span class="text-sm font-medium text-foreground truncate" x-text="s.name"></span>
                            </a>
                            <button type="button" @click="s.following ? unfollowSuggestion(s) : followSuggestion(s)"
                                    class="flex-shrink-0 text-xs font-semibold px-3 py-1.5 rounded-full transition-colors"
                                    :class="s.following ? 'bg-muted text-muted-foreground' : 'bg-primary text-white hover:bg-primary/90'"
                                    x-text="s.following ? '{{ __('personal.following') }}' : '{{ __('personal.follow') }}'"></button>
                        </div>
                    </template>
                </div>
                <a href="{{ route('me.people') }}" class="block text-center text-xs font-semibold text-primary hover:underline mt-2 pt-2 border-t border-gray-50">
                    {{ __('personal.find_people') }} <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <a href="{{ route('me.schedule') }}" class="block bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:border-primary/30 transition-colors">
                <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2 mb-1">
                    <i class="bi bi-calendar-week text-primary"></i>{{ __('nav.tab_schedule') }}
                </h3>
                <p class="text-xs text-muted-foreground">{{ __('nav.my_schedule') }} <i class="bi bi-arrow-right"></i></p>
            </a>
        </aside>
    </div>

    {{-- ===== Fullscreen image viewer ===== --}}
    <div x-show="lightbox.open" x-cloak class="fixed inset-0 z-[60] bg-black flex flex-col"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @keydown.escape.window="closeLightbox()">
        <div class="flex items-center justify-between px-4 h-14 text-white flex-shrink-0">
            <button type="button" @click="closeLightbox()" class="w-10 h-10 -ml-2 rounded-full flex items-center justify-center hover:bg-white/10" aria-label="{{ __('personal.close') }}">
                <i class="bi bi-x-lg text-xl"></i>
            </button>
            <span class="text-sm font-medium" x-show="lightbox.images.length > 1"
                  x-text="(lightbox.index + 1) + ' / ' + lightbox.images.length"></span>
        </div>
        <div class="flex-1 relative flex items-center justify-center overflow-hidden" @click.self="closeLightbox()">
            <img :src="lightbox.images[lightbox.index]?.url" alt="" class="max-h-full max-w-full object-contain select-none">
            <button type="button" x-show="lightbox.index > 0" @click="lbPrev()"
                    class="absolute left-2 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-black/40 text-white flex items-center justify-center hover:bg-black/60" aria-label="{{ __('personal.previous') }}">
                <i class="bi bi-chevron-left text-xl"></i>
            </button>
            <button type="button" x-show="lightbox.index < lightbox.images.length - 1" @click="lbNext()"
                    class="absolute right-2 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-black/40 text-white flex items-center justify-center hover:bg-black/60" aria-label="{{ __('personal.next') }}">
                <i class="bi bi-chevron-right text-xl"></i>
            </button>
        </div>
    </div>

    @include('personal.partials.post-viewers-modal')

    @include('partials.news-feed-script')
</div>
@endsection
