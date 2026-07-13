@extends('layouts.app')

@section('hide-navbar', true)
@section('title', \Illuminate\Support\Str::limit(strip_tags($post['body'] ?? ''), 40) ?: __('personal.post_title'))

@section('content')
<div x-data="postPage()" class="min-h-screen bg-background pb-12">

    {{-- ===== Hero header ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between gap-3 relative z-10 max-w-xl mx-auto">
            <div class="flex items-center gap-3 min-w-0">
                <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('me.home') }}')"
                        class="m-press w-12 h-12 shrink-0 rounded-2xl bg-white/20 border border-white/30 backdrop-blur grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('shared.back') }}">
                    <i class="bi bi-arrow-left text-xl"></i>
                </button>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ __('nav.news_feed') }}</p>
                    <h1 class="text-2xl font-black mt-0.5">{{ __('personal.post_title') }}</h1>
                </div>
            </div>
            <button type="button" @click="sharePost(post)"
                    class="m-press w-12 h-12 shrink-0 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('personal.share') }}">
                <i class="bi bi-share text-lg m-float"></i>
            </button>
        </div>
    </header>

    {{-- ===== The post ===== --}}
    <div class="max-w-xl mx-auto sm:mt-4">
        <div class="mobile-stagger">
            @include('personal.partials.post-card', ['bare' => true])
        </div>
    </div>

    <script>
        window.postPage = function () {
            return {
                me: { name: @js(Auth::user()->full_name), avatar: @js(Auth::user()->profile_picture ? asset('storage/'.Auth::user()->profile_picture).'?v='.optional(Auth::user()->updated_at)->timestamp : null) },
                isSuperAdmin: @js((bool) (Auth::user()?->hasRole('super-admin'))),
                csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
                postBase: @js(url('/me/posts')),
                base: @js(url('/u')),
                post: @js($post),
                lightbox: { open: false, images: [], index: 0 },

                init() {
                    // Comments are expanded by default on the dedicated post page.
                    this.post.showComments = true;
                    // Live updates for THIS post over MQTT (like / comment / edit / delete).
                    window.addEventListener('realtime:posts', (e) => this.onRealtimePost(e.detail || {}));
                },
                onRealtimePost(d) {
                    if (!d || d.post_id !== this.post.id) return;
                    if (d.action === 'delete') {
                        window.showToast && window.showToast('info', @js(__('personal.post_removed')));
                        setTimeout(() => window.location.href = @js(route('me.home')), 900);
                        return;
                    }
                    // Moderation: super-admins keep viewing the (flagged) post; anyone
                    // else is bounced home the moment it's hidden.
                    if (d.action === 'hide') {
                        if (this.isSuperAdmin) { this.post.hidden = true; }
                        else {
                            window.showToast && window.showToast('info', @js(__('personal.post_removed')));
                            setTimeout(() => window.location.href = @js(route('me.home')), 900);
                        }
                        return;
                    }
                    if (d.action === 'unhide') { this.post.hidden = false; return; }
                    if (d.action === 'like') this.post.likes = d.likes;
                    else if (d.action === 'comment') { if (!this.post.comments.some(c => c.id === d.comment.id)) this.post.comments.push(d.comment); }
                    else if (d.action === 'edit') { this.post.body = d.body; this.post.edited = true; }
                    else if (d.action === 'poll' && this.post.poll && d.poll) {
                        const mine = this.post.poll.myVote;
                        this.post.poll = { ...d.poll, myVote: mine };
                    }
                    else if (d.action === 'view' && typeof d.views === 'number') this.post.views = d.views;
                },

                // Fetch helper — JSON body, returns parsed JSON (throws on failure).
                async send(url, { method = 'POST', body = null } = {}) {
                    const headers = { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' };
                    if (body) headers['Content-Type'] = 'application/json';
                    const res = await fetch(url, {
                        method, headers, credentials: 'same-origin',
                        body: body ? JSON.stringify(body) : null,
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || data.success === false) throw new Error(data.message || @js(__('shared.error')));
                    return data;
                },

                // We're already on the post's page — tapping it again is a no-op.
                openPost() {},

                async votePoll(post, i) {
                    if (!post.poll || post.poll.myVote === i) return;
                    const prev = post.poll.myVote;
                    post.poll.options[i].votes++;
                    if (prev !== null && post.poll.options[prev]) post.poll.options[prev].votes--;
                    else post.poll.totalVotes++;
                    post.poll.myVote = i;
                    try {
                        const data = await this.send(`${this.postBase}/${post.id}/vote`, { method: 'POST', body: { option: i } });
                        post.poll = data.poll;
                    } catch (e) {
                        post.poll.myVote = prev;
                        window.showToast && window.showToast('error', e.message);
                    }
                },

                async toggleLike(post) {
                    post.liked = !post.liked; post.likes += post.liked ? 1 : -1;
                    try {
                        const data = await this.send(`${this.postBase}/${post.id}/like`);
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
                        const data = await this.send(`${this.postBase}/${post.id}/comment`, { method: 'POST', body: { body: text } });
                        post.comments.push(data.comment); post.commentDraft = ''; post.showComments = true;
                    } catch (e) { window.showToast && window.showToast('error', e.message); }
                },

                // ----- Edit / delete (own post only) -----
                startEdit(post) { post.draft = post.body; post.editing = true; },
                async saveEdit(post) {
                    try {
                        const data = await this.send(`${this.postBase}/${post.id}`, { method: 'PUT', body: { body: (post.draft || '').trim() } });
                        Object.assign(post, data.post);
                        post.editing = false;
                        window.showToast && window.showToast('success', data.message || @js(__('personal.post_updated')));
                    } catch (e) { window.showToast && window.showToast('error', e.message); }
                },
                async deletePost(post) {
                    const ok = await window.confirmAction({ title: @js(__('personal.delete_post')), message: @js(__('personal.delete_post_confirm')), type: 'danger', confirmText: @js(__('personal.delete')) });
                    if (!ok) return;
                    try {
                        await this.send(`${this.postBase}/${post.id}`, { method: 'DELETE' });
                        window.showToast && window.showToast('success', @js(__('personal.post_deleted')));
                        setTimeout(() => window.location.href = @js(route('me.home')), 600);
                    } catch (e) { window.showToast && window.showToast('error', e.message); }
                },

                // Super-admin moderation: remove any member's post for everyone.
                async adminDeletePost(post) {
                    const ok = await window.confirmAction({ title: @js(__('personal.admin_remove_post')), message: @js(__('personal.admin_remove_post_confirm')), type: 'danger', confirmText: @js(__('personal.delete')) });
                    if (!ok) return;
                    try {
                        await this.send(`${this.postBase}/${post.id}`, { method: 'DELETE' });
                        window.showToast && window.showToast('success', @js(__('personal.post_deleted')));
                        setTimeout(() => window.location.href = @js(route('me.home')), 600);
                    } catch (e) { window.showToast && window.showToast('error', e.message); }
                },

                // Super-admin moderation: hide (reversible) / unhide this post.
                async hidePost(post) {
                    const ok = await window.confirmAction({ title: @js(__('personal.hide_post')), message: @js(__('personal.hide_post_confirm')), type: 'warning', confirmText: @js(__('personal.hide_confirm_btn')) });
                    if (!ok) return;
                    try {
                        await this.send(`${this.postBase}/${post.id}/hide`, { method: 'POST' });
                        this.post.hidden = true;
                        window.showToast && window.showToast('success', @js(__('personal.post_hidden')));
                    } catch (e) { window.showToast && window.showToast('error', e.message); }
                },
                async unhidePost(post) {
                    try {
                        await this.send(`${this.postBase}/${post.id}/unhide`, { method: 'POST' });
                        this.post.hidden = false;
                        window.showToast && window.showToast('success', @js(__('personal.post_unhidden')));
                    } catch (e) { window.showToast && window.showToast('error', e.message); }
                },

                // ----- Block author (then leave — the post is no longer visible) -----
                async blockAuthor(post) {
                    const name = post.author.name;
                    const ok = await window.confirmAction({ title: @js(__('personal.block_x')).replace(':name', name), message: @js(__('personal.block_confirm')), type: 'danger', confirmText: @js(__('personal.block')) });
                    if (!ok) return;
                    try {
                        await this.send(`${this.base}/${post.author.slug}/block`);
                        window.showToast && window.showToast('success', @js(__('personal.blocked')).replace(':name', name));
                        setTimeout(() => window.location.href = @js(route('me.home')), 600);
                    } catch (e) { window.showToast && window.showToast('error', e.message); }
                },

                // ----- Share the post's permalink -----
                sharePost(post) {
                    const url = post.url;
                    if (navigator.share) {
                        navigator.share({ text: (post.body || '').trim() || @js(__('personal.check_out_post')), url }).catch(() => {});
                    } else if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(url)
                            .then(() => window.showToast && window.showToast('success', @js(__('personal.link_copied'))))
                            .catch(() => window.showToast && window.showToast('info', @js(__('personal.could_not_share'))));
                    } else {
                        window.showToast && window.showToast('info', @js(__('personal.share_unsupported')));
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

    {{-- "Seen by" viewers modal (owner taps the view count) --}}
    @include('personal.partials.post-viewers-modal')
</div>
@endsection
