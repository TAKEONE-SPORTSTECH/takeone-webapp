@extends('layouts.app')

@section('hide-navbar', true)
@section('title', $profile->full_name)

@section('content')
@php
    $firstName = \Illuminate\Support\Str::of($profile->full_name)->explode(' ')->first();
@endphp

<div x-data="wall()" class="min-h-screen bg-background pb-12">

    {{-- ===== Header with back button ===== --}}
    <header class="sticky top-0 z-40 bg-white border-b border-border">
        <div class="flex items-center gap-2 px-3 h-14">
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('me.home') }}')"
                    class="m-press w-10 h-10 -ml-1 rounded-xl flex items-center justify-center text-foreground" aria-label="{{ __('shared.back') }}">
                <i class="bi bi-arrow-left text-xl"></i>
            </button>
            <p class="flex-1 min-w-0 text-base font-bold text-primary truncate">{{ $profile->full_name }}</p>

            <div class="relative" x-data="{ menu:false }" @click.outside="menu=false">
                <button type="button" @click="menu=!menu" class="m-press w-9 h-9 rounded-xl bg-muted flex items-center justify-center text-muted-foreground" aria-label="{{ __('personal.more') }}">
                    <i class="bi bi-three-dots"></i>
                </button>
                <div x-show="menu" x-cloak @click="menu=false"
                     x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     class="absolute right-0 top-11 z-20 w-44 bg-white rounded-xl shadow-lg border border-gray-100 py-1">
                    <button type="button" x-show="!rel.blocked" @click="block()" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                        <i class="bi bi-slash-circle"></i> {{ __('personal.block') }} {{ $firstName }}
                    </button>
                    <button type="button" x-show="rel.blocked" @click="unblock()" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-foreground hover:bg-muted transition-colors">
                        <i class="bi bi-arrow-counterclockwise"></i> {{ __('personal.unblock') }}
                    </button>
                    <button type="button" onclick="window.showToast && window.showToast('info', @js(__('personal.report_sent')))" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-foreground hover:bg-muted transition-colors">
                        <i class="bi bi-flag"></i> {{ __('personal.report') }}
                    </button>
                </div>
            </div>
        </div>
    </header>

    {{-- ===== Profile header (avatar left · name + clubs right) ===== --}}
    <div class="bg-white">
        <div class="px-4 pt-4 pb-4">
            <div class="flex items-center gap-4">
                {{-- Avatar (left) --}}
                <span class="w-20 h-20 rounded-full bg-muted shadow flex items-center justify-center overflow-hidden flex-shrink-0">
                    @if($avatar)
                        <img src="{{ $avatar }}" alt="" class="w-20 h-20 object-cover">
                    @else
                        <i class="bi bi-person text-3xl text-muted-foreground"></i>
                    @endif
                </span>

                {{-- Name + clubs (right) --}}
                <div class="min-w-0 flex-1">
                    <h1 class="text-xl font-bold text-gray-900 truncate">{{ $profile->full_name }}</h1>
                    @if(!empty($clubs))
                        <p class="text-[13px] text-muted-foreground flex items-center gap-1.5 mt-0.5">
                            <i class="bi bi-buildings text-primary flex-shrink-0"></i>
                            <span class="truncate">{{ implode(' · ', $clubs) }}</span>
                        </p>
                    @endif
                    <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1 mt-1.5 text-[11px]">
                        @if(!empty($sharedClubs))
                            <span class="inline-flex items-center gap-1 text-accent-foreground font-medium">
                                <i class="bi bi-people-fill"></i> {{ __('personal.club_mate') }}
                            </span>
                        @endif
                        @if($profile->age !== null)
                            <span class="inline-flex items-center gap-1 text-muted-foreground">
                                <i class="bi bi-calendar3"></i> {{ $profile->age }} {{ __('personal.yrs') }}
                            </span>
                        @endif
                        @if($profile->gender)
                            <span class="inline-flex items-center gap-1 text-muted-foreground">
                                <i class="bi {{ strtolower($profile->gender) === 'male' ? 'bi-gender-male' : (strtolower($profile->gender) === 'female' ? 'bi-gender-female' : 'bi-person') }}"></i> {{ ucfirst($profile->gender) }}
                            </span>
                        @endif
                        @if($profile->horoscope)
                            <span class="inline-flex items-center gap-1 text-muted-foreground">
                                <i class="bi bi-stars"></i> {{ $profile->horoscope }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-5 gap-1 mt-4 text-center">
                <div><p class="text-base font-bold text-gray-900">{{ $stats['posts'] }}</p><p class="text-[11px] text-muted-foreground leading-tight">{{ __('personal.stat_posts') }}</p></div>
                <div><p class="text-base font-bold text-gray-900">{{ $stats['followers'] }}</p><p class="text-[11px] text-muted-foreground leading-tight">{{ __('personal.stat_followers') }}</p></div>
                <div><p class="text-base font-bold text-gray-900">{{ $stats['following'] }}</p><p class="text-[11px] text-muted-foreground leading-tight">{{ __('personal.following') }}</p></div>
                <div><p class="text-base font-bold text-gray-900">{{ $stats['participations'] }}</p><p class="text-[11px] text-muted-foreground leading-tight">{{ __('personal.stat_events') }}</p></div>
                <div><p class="text-base font-bold text-gray-900">{{ $stats['achievements'] }}</p><p class="text-[11px] text-muted-foreground leading-tight">{{ __('personal.stat_medals') }}</p></div>
            </div>

            {{-- ===== Relationship actions ===== --}}
            <div class="flex items-center gap-2 mt-4" x-show="!rel.blocked && !rel.blockedBy">
                {{-- Follow / Following --}}
                <button type="button" @click="rel.following ? unfollow() : follow()" :disabled="busy"
                        class="m-press flex-1 py-2.5 rounded-lg text-sm font-semibold transition-colors flex items-center justify-center gap-2"
                        :class="rel.following ? 'bg-muted text-foreground' : 'bg-primary text-white hover:bg-primary/90'">
                    <i class="bi" :class="rel.following ? 'bi-check-lg' : 'bi-person-plus'"></i>
                    <span x-text="rel.following ? @js(__('personal.following')) : @js(__('personal.follow'))"></span>
                </button>

                {{-- Message — POSTs via fetch, then navigates to the DM thread --}}
                <button type="button" @click="message()" :disabled="busy"
                        class="m-press w-11 py-2.5 rounded-lg bg-muted text-foreground flex items-center justify-center" aria-label="{{ __('personal.message') }}">
                    <i class="bi bi-chat-dots"></i>
                </button>
            </div>

            <p class="text-[11px] text-muted-foreground mt-2" x-show="rel.followsYou && !rel.blocked && !rel.blockedBy" x-cloak>
                <i class="bi bi-arrow-left-right"></i> {{ __('personal.follows_you', ['name' => $firstName]) }}
            </p>
        </div>
    </div>

    {{-- ===== Posts / locked / blocked states ===== --}}
    <div class="mt-2">
        @if($blocked)
            <div class="bg-white px-6 py-14 text-center">
                <i class="bi bi-slash-circle text-4xl text-gray-300"></i>
                <p class="text-sm text-muted-foreground mt-3" x-show="rel.blocked">{{ __('personal.you_blocked', ['name' => $firstName]) }}</p>
                <p class="text-sm text-muted-foreground mt-3" x-show="rel.blockedBy" x-cloak>{{ __('personal.wall_unavailable') }}</p>
                <button type="button" x-show="rel.blocked" @click="unblock()" class="m-press mt-4 px-5 py-2 rounded-lg border border-primary text-primary text-sm font-semibold hover:bg-primary hover:text-white transition-colors">{{ __('personal.unblock') }}</button>
            </div>
        @elseif(!$canView)
            <div class="bg-white px-6 py-14 text-center">
                <i class="bi bi-lock text-4xl text-gray-300 m-float inline-block"></i>
                <p class="text-sm text-muted-foreground mt-3">{{ __('personal.posts_private', ['name' => $firstName]) }}</p>
                <p class="text-[12px] text-gray-400 mt-1">{{ __('personal.follow_to_see') }}</p>
            </div>
        @else
            <div class="mobile-stagger space-y-2">
                <template x-for="post in posts" :key="post.id">
                    @include('personal.partials.post-card')
                </template>
                <div x-show="posts.length === 0" class="bg-white px-6 py-14 text-center">
                    <i class="bi bi-journal-text text-4xl text-gray-300 m-float inline-block"></i>
                    <p class="text-sm text-muted-foreground mt-3">{{ __('personal.no_posts_yet', ['name' => $firstName]) }}</p>
                </div>
            </div>
        @endif
    </div>

    <script>
        window.wall = function () {
            return {
                me: { name: @js(Auth::user()->full_name), avatar: @js(Auth::user()->profile_picture ? asset('storage/'.Auth::user()->profile_picture).'?v='.optional(Auth::user()->updated_at)->timestamp : null) },
                csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
                profileId: {{ $profile->id }},
                profileSlug: @js($profile->slug),
                base: @js(url('/u')),
                postBase: @js(url('/me/posts')),
                rel: @js($relationship),
                canView: @js($canView),
                busy: false,
                posts: @js($posts),
                lightbox: { open: false, images: [], index: 0 },

                init() {
                    // Live updates for this wall's posts over MQTT.
                    window.addEventListener('realtime:posts', (e) => this.onRealtimePost(e.detail || {}));
                },
                onRealtimePost(d) {
                    if (d.action === 'new' && d.post) {
                        if (d.post.author && d.post.author.id === this.profileId && !this.posts.some(p => p.id === d.post.id)) {
                            this.posts.unshift(d.post);
                        }
                        return;
                    }
                    const id = d.post_id;
                    if (d.action === 'delete') { this.posts = this.posts.filter(p => p.id !== id); return; }
                    const p = this.posts.find(x => x.id === id);
                    if (!p) return;
                    if (d.action === 'like') p.likes = d.likes;
                    else if (d.action === 'comment') { if (!p.comments.some(c => c.id === d.comment.id)) p.comments.push(d.comment); }
                    else if (d.action === 'edit') { p.body = d.body; p.edited = true; }
                    else if (d.action === 'poll' && p.poll && d.poll) {
                        const mine = p.poll.myVote;
                        p.poll = { ...d.poll, myVote: mine };
                    }
                },

                async send(url, method = 'POST') {
                    const res = await fetch(url, {
                        method,
                        headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || data.success === false) throw new Error(data.message || @js(__('shared.error')));
                    return data;
                },

                async act(url, method, successMsg) {
                    if (this.busy) return;
                    this.busy = true;
                    const couldView = this.canView;
                    try {
                        const data = await this.send(`${this.base}/${this.profileSlug}${url}`, method);
                        this.rel = data.relationship;
                        this.canView = data.canView;
                        if (data.message) window.showToast && window.showToast('success', data.message);
                        // Visibility just opened up → reload to fetch the now-visible posts.
                        if (!couldView && this.canView) window.location.reload();
                    } catch (e) {
                        window.showToast && window.showToast('error', e.message);
                    } finally {
                        this.busy = false;
                    }
                },

                follow()       { return this.act('/follow', 'POST'); },
                unfollow()     { return this.act('/follow', 'DELETE'); },
                async block() {
                    const ok = await window.confirmAction({ title: @js(__('personal.block_x', ['name' => $firstName])), message: @js(__('personal.block_confirm')), type: 'danger', confirmText: @js(__('personal.block')) });
                    if (!ok) return;
                    return this.act('/block', 'POST');
                },
                unblock()      { return this.act('/block', 'DELETE'); },

                // Open (or create) a direct conversation, then go to the thread.
                async message() {
                    if (this.busy) return;
                    this.busy = true;
                    try {
                        const res = await fetch(@js(url('/messages/start')) + '/' + this.profileId, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        const data = await res.json().catch(() => ({}));
                        if (res.ok && data.conversation_id) {
                            window.location.href = @js(url('/messages')) + '/' + data.conversation_id;
                        } else {
                            throw new Error(data.message || @js(__('personal.could_not_open_chat')));
                        }
                    } catch (e) {
                        window.showToast && window.showToast('error', e.message);
                    } finally {
                        this.busy = false;
                    }
                },

                // ----- Post interactions (shared with the feed card) -----
                async sendPost(url, body) {
                    const res = await fetch(url, {
                        method: body ? 'POST' : 'POST',
                        headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: body ? JSON.stringify(body) : null,
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || data.success === false) throw new Error(data.message || 'Something went wrong');
                    return data;
                },
                async toggleLike(post) {
                    post.liked = !post.liked; post.likes += post.liked ? 1 : -1;
                    try {
                        const data = await this.sendPost(`${this.postBase}/${post.id}/like`);
                        post.liked = data.liked; post.likes = data.likes;
                    } catch (e) {
                        post.liked = !post.liked; post.likes += post.liked ? 1 : -1;
                        window.showToast && window.showToast('error', e.message);
                    }
                },
                async addComment(post) {
                    const text = (post.commentDraft || '').trim();
                    if (!text) return;
                    try {
                        const data = await this.sendPost(`${this.postBase}/${post.id}/comment`, { body: text });
                        post.comments.push(data.comment); post.commentDraft = ''; post.showComments = true;
                    } catch (e) { window.showToast && window.showToast('error', e.message); }
                },
                async votePoll(post, i) {
                    if (!post.poll || post.poll.myVote === i) return;
                    const prev = post.poll.myVote;
                    post.poll.options[i].votes++;
                    if (prev !== null && post.poll.options[prev]) post.poll.options[prev].votes--;
                    else post.poll.totalVotes++;
                    post.poll.myVote = i;
                    try {
                        const data = await this.sendPost(`${this.postBase}/${post.id}/vote`, { option: i });
                        post.poll = data.poll;
                    } catch (e) {
                        post.poll.myVote = prev;
                        window.showToast && window.showToast('error', e.message);
                    }
                },
                openPost(post) {
                    if (post && post.url) window.location.href = post.url;
                },
                sharePost(post) {
                    const url = post.url;
                    if (navigator.share) navigator.share({ text: (post.body || '').trim() || @js(__('personal.check_out_post')), url }).catch(() => {});
                    else if (navigator.clipboard) navigator.clipboard.writeText(url).then(() => window.showToast && window.showToast('success', @js(__('personal.link_copied'))));
                },
                // Not applicable on someone else's wall, but referenced by the shared card template.
                startEdit() {}, saveEdit() {}, deletePost() {},
                blockAuthor() { return this.block(); },

                openLightbox(images, index) { this.lightbox = { open: true, images: images, index: index || 0 }; },
                closeLightbox() { this.lightbox.open = false; },
                lbNext() { if (this.lightbox.index < this.lightbox.images.length - 1) this.lightbox.index++; },
                lbPrev() { if (this.lightbox.index > 0) this.lightbox.index--; },
            };
        };
    </script>

    {{-- Fullscreen image viewer --}}
    <div x-show="lightbox.open" x-cloak class="fixed inset-0 z-[60] bg-black flex flex-col"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         @keydown.escape.window="closeLightbox()">
        <div class="flex items-center justify-between px-4 h-14 text-white flex-shrink-0">
            <button type="button" @click="closeLightbox()" class="m-press w-10 h-10 -ml-2 rounded-full flex items-center justify-center hover:bg-white/10" aria-label="{{ __('personal.close') }}"><i class="bi bi-x-lg text-xl"></i></button>
            <span class="text-sm font-medium" x-show="lightbox.images.length > 1" x-text="(lightbox.index + 1) + ' / ' + lightbox.images.length"></span>
        </div>
        <div class="flex-1 relative flex items-center justify-center overflow-hidden" @click.self="closeLightbox()">
            <img :src="lightbox.images[lightbox.index]?.url" alt="" class="max-h-full max-w-full object-contain select-none">
            <button type="button" x-show="lightbox.index > 0" @click="lbPrev()" class="m-press absolute left-2 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-black/40 text-white flex items-center justify-center" aria-label="{{ __('personal.previous') }}"><i class="bi bi-chevron-left text-xl"></i></button>
            <button type="button" x-show="lightbox.index < lightbox.images.length - 1" @click="lbNext()" class="m-press absolute right-2 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-black/40 text-white flex items-center justify-center" aria-label="{{ __('personal.next') }}"><i class="bi bi-chevron-right text-xl"></i></button>
        </div>
    </div>

    {{-- Provides recordPostView() for the cards (and the "Seen by" modal, unused
         here since your own posts aren't shown on someone else's wall). --}}
    @include('personal.partials.post-viewers-modal')
</div>
@endsection
