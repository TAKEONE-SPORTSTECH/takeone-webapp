{{-- Shared newsFeed() Alpine component — powers both the mobile and desktop
     feed pages identically. Expects `$me`, `$myAvatar`, `$personalPosts`,
     `$followingPosts`, `$allPosts`, `$suggestions`, `$feedTabDots` in scope. --}}
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
