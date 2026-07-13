{{-- Generic "people" modal — shows who VIEWED ("Seen by") or LIKED ("Liked by")
     a post. Listens for the global `open-post-people` event. Include once per
     page. The modal markup is teleported to <body> so it's never trapped inside
     a transformed/animated ancestor (which would break position:fixed). --}}
<div x-data="postPeople()">
    <template x-teleport="body">
        <div x-show="open" x-cloak
             class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             @keydown.escape.window="close()">
            <div class="absolute inset-0 bg-black/50" @click="close()"></div>

            <div class="relative w-full sm:max-w-md bg-white rounded-t-3xl sm:rounded-3xl shadow-xl max-h-[75vh] flex flex-col"
                 @click.stop
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full sm:translate-y-8 opacity-0" x-transition:enter-end="translate-y-0 opacity-100">
                {{-- grab handle (mobile) --}}
                <div class="sm:hidden w-10 h-1 rounded-full bg-gray-300 mx-auto mt-2.5"></div>

                {{-- Header --}}
                <div class="flex items-center justify-between px-5 pt-3 pb-3 border-b border-gray-100">
                    <div class="flex items-center gap-2">
                        <i class="bi text-lg" :class="kind === 'likes' ? 'bi-heart-fill text-primary' : 'bi-eye text-primary'"></i>
                        <p class="text-sm font-bold text-foreground" x-text="kind === 'likes' ? @js(__('personal.likers_title')) : @js(__('personal.viewers_title'))"></p>
                        <span x-show="!loading" x-cloak class="text-[12px] text-muted-foreground" x-text="'· ' + people.length"></span>
                    </div>
                    <button type="button" @click="close()" class="m-press w-9 h-9 -me-1.5 rounded-full grid place-items-center text-gray-500 hover:bg-muted transition-colors" aria-label="{{ __('personal.close') }}">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                {{-- Body --}}
                <div class="flex-1 overflow-y-auto px-2 py-2 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                    <div x-show="loading" class="py-10 text-center">
                        <i class="bi bi-arrow-repeat animate-spin text-2xl text-gray-300"></i>
                    </div>
                    <div x-show="!loading && people.length === 0" x-cloak class="py-10 text-center">
                        <i class="bi text-3xl text-gray-300 block mb-2" :class="kind === 'likes' ? 'bi-heart' : 'bi-eye-slash'"></i>
                        <p class="text-sm text-muted-foreground" x-text="kind === 'likes' ? @js(__('personal.no_likers')) : @js(__('personal.no_viewers'))"></p>
                    </div>
                    <template x-for="v in people" :key="v.id">
                        <a :href="v.url" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-muted transition-colors no-underline">
                            <span class="w-10 h-10 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                                <template x-if="v.avatar"><img :src="v.avatar" alt="" class="w-10 h-10 object-cover"></template>
                                <template x-if="!v.avatar"><i class="bi bi-person text-muted-foreground"></i></template>
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-semibold text-foreground truncate" x-text="v.name"></span>
                                <span class="block text-[11px] text-muted-foreground" x-text="v.time"></span>
                            </span>
                            <i class="bi bi-chevron-right text-gray-300"></i>
                        </a>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
    // ----- View tracking helper (top-level, NOT inside a <template>, so it runs;
    // post-card calls it via x-init). -----
    window.recordPostView = window.recordPostView || function (post, el) {
        if (!post || !post.id || (post.author && post.author.isMe)) return;
        window.__viewedPosts = window.__viewedPosts || new Set();
        if (window.__viewedPosts.has(post.id)) return;

        const fire = () => {
            if (window.__viewedPosts.has(post.id)) return;
            window.__viewedPosts.add(post.id);
            const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
            fetch(`/me/posts/${post.id}/view`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                credentials: 'same-origin', keepalive: true,
            }).then(r => r.json()).then(d => {
                if (d && typeof d.views === 'number') post.views = d.views;
            }).catch(() => {});
        };

        if (el && 'IntersectionObserver' in window) {
            const io = new IntersectionObserver((entries) => {
                entries.forEach(e => { if (e.isIntersecting) { fire(); io.disconnect(); } });
            }, { threshold: 0.6 });
            io.observe(el);
        } else {
            fire();
        }
    };

    // Triggers — owner taps the view count, anyone taps the like count.
    window.openPostViewers = window.openPostViewers || function (post) {
        window.dispatchEvent(new CustomEvent('open-post-people', { detail: { post, kind: 'views' } }));
    };
    window.openPostLikers = window.openPostLikers || function (post) {
        window.dispatchEvent(new CustomEvent('open-post-people', { detail: { post, kind: 'likes' } }));
    };

    window.postPeople = function () {
        return {
            open: false,
            loading: false,
            kind: 'views',
            people: [],
            init() {
                window.addEventListener('open-post-people', (e) => this.show(e.detail || {}));
            },
            async show({ post, kind }) {
                if (!post || !post.id) return;
                this.kind = kind || 'views';
                this.open = true;
                this.loading = true;
                this.people = [];
                const path = this.kind === 'likes' ? 'likers' : 'viewers';
                try {
                    const res = await fetch(`/me/posts/${post.id}/${path}`, {
                        headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
                    });
                    const data = await res.json();
                    this.people = data.people || data.viewers || [];
                } catch (e) {
                    window.showToast && window.showToast('error', @js(__('shared.error')));
                } finally {
                    this.loading = false;
                }
            },
            close() { this.open = false; },
        };
    };
</script>
