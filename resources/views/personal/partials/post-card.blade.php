{{-- Alpine post card. Expects `post` in scope (an x-for item) and the parent
     newsFeed()/wall() component for the action methods. Author-aware: shows
     Edit/Delete only on your own posts, otherwise a wall link + Block. --}}
<article x-init="recordPostView(post, $el)" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    {{-- Header --}}
    <div class="flex items-start justify-between px-4 pt-3">
        <div class="flex items-center gap-3 min-w-0">
            <a :href="post.author.url" class="flex-shrink-0">
                <span class="w-10 h-10 rounded-full bg-muted flex items-center justify-center overflow-hidden">
                    <template x-if="post.author.avatar"><img :src="post.author.avatar" alt="" class="w-10 h-10 object-cover"></template>
                    <template x-if="!post.author.avatar"><i class="bi bi-person text-muted-foreground"></i></template>
                </span>
            </a>
            <div class="min-w-0">
                <a :href="post.author.url"><p class="font-semibold text-sm text-foreground truncate hover:underline" x-text="post.author.name"></p></a>
                {{-- Timestamp = permalink to the post's own page --}}
                <button type="button" @click="openPost(post)" class="text-[11px] text-gray-500 flex items-center gap-1 hover:underline">
                    <span x-text="(post.edited ? @js(__('personal.edited_prefix')) : '') + post.time"></span><i class="bi bi-globe2"></i>
                </button>
            </div>
        </div>
        <div class="relative flex-shrink-0" x-data="{ menu:false }" @click.outside="menu=false">
            <button type="button" @click="menu=!menu"
                    class="m-press w-8 h-8 -mr-1 flex items-center justify-center rounded-full text-gray-500 hover:bg-muted transition-colors">
                <i class="bi bi-three-dots"></i>
            </button>
            <div x-show="menu" x-cloak @click="menu=false"
                 x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 class="absolute right-0 top-9 z-30 w-44 bg-white rounded-xl shadow-lg border border-gray-100 py-1"
                 style="max-width: calc(100vw - 1.5rem); transform-origin: top right;">
                <template x-if="post.author.isMe">
                    <div>
                        <button type="button" @click="startEdit(post)" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-foreground hover:bg-muted transition-colors">
                            <i class="bi bi-pencil"></i> {{ __('personal.edit_post') }}
                        </button>
                        <button type="button" @click="deletePost(post)" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                            <i class="bi bi-trash"></i> {{ __('personal.delete_post') }}
                        </button>
                    </div>
                </template>
                <template x-if="!post.author.isMe">
                    <div>
                        <a :href="post.author.url" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-foreground hover:bg-muted transition-colors">
                            <i class="bi bi-person-lines-fill"></i> {{ __('personal.view_wall') }}
                        </a>
                        <button type="button" @click="blockAuthor(post)" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                            <i class="bi bi-slash-circle"></i> {{ __('personal.block') }} <span x-text="post.author.name.split(' ')[0]"></span>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Body (read) — tap to open the post's own page --}}
    <p x-show="post.body && !post.editing" @click="openPost(post)" class="text-[13px] text-gray-900 whitespace-pre-line px-4 py-2.5 cursor-pointer" x-text="post.body"></p>

    {{-- Body (edit) — own posts only --}}
    <div x-show="post.editing" x-cloak class="px-4 py-2.5">
        <textarea x-model="post.draft" rows="3"
                  class="w-full resize-none bg-muted rounded-xl px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
        <div class="flex justify-end gap-2 mt-2">
            <button type="button" @click="post.editing=false" class="m-press px-3 py-1.5 rounded-lg text-sm font-medium text-muted-foreground hover:bg-muted transition-colors">{{ __('shared.cancel') }}</button>
            <button type="button" @click="saveEdit(post)" class="m-press px-4 py-1.5 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary/90 transition-colors">{{ __('shared.save') }}</button>
        </div>
    </div>

    {{-- Highlight cover banner (animated floating icon) — when no uploaded image --}}
    <template x-if="post.cover && !post.images.length">
        <div class="relative h-48 overflow-hidden flex flex-col justify-end p-4 text-white mt-1"
             :style="`background: linear-gradient(135deg, ${post.cover.color}, ${post.cover.color}bb)`">
            <div class="absolute -right-8 -top-8 w-32 h-32 rounded-full bg-white/10"></div>
            <div class="absolute right-6 bottom-10 w-20 h-20 rounded-full bg-white/10"></div>
            <i class="bi text-6xl opacity-90 absolute top-5 left-4 m-float" :class="post.cover.icon"></i>
            <span class="relative text-lg font-black drop-shadow" x-text="post.cover.label"></span>
        </div>
    </template>

    {{-- Poll (vote in place; bars fill once you've voted) --}}
    <template x-if="post.type === 'poll' && post.poll">
        <div class="px-4 pb-1 pt-1 space-y-2">
            <template x-for="(opt, i) in post.poll.options" :key="i">
                <button type="button" @click="votePoll(post, i)"
                        class="m-press relative w-full text-left rounded-xl overflow-hidden border transition-colors"
                        :class="post.poll.myVote === i ? 'border-primary' : 'border-gray-200 hover:border-primary/50'">
                    {{-- fill bar (only after the viewer has voted) --}}
                    <div class="absolute inset-y-0 left-0 transition-all duration-500 rounded-l-xl"
                         :class="post.poll.myVote === i ? 'bg-accent' : 'bg-muted'"
                         :style="`width: ${post.poll.myVote !== null && post.poll.totalVotes ? Math.round(opt.votes / post.poll.totalVotes * 100) : 0}%`"></div>
                    <div class="relative flex items-center justify-between gap-2 px-3.5 py-2.5">
                        <span class="text-[13px] font-medium text-foreground flex items-center gap-1.5 min-w-0">
                            <i class="bi bi-check-circle-fill text-primary flex-shrink-0" x-show="post.poll.myVote === i" x-cloak></i>
                            <span class="truncate" x-text="opt.text"></span>
                        </span>
                        <span class="text-[12px] font-semibold text-muted-foreground flex-shrink-0" x-show="post.poll.myVote !== null" x-cloak
                              x-text="(post.poll.totalVotes ? Math.round(opt.votes / post.poll.totalVotes * 100) : 0) + '%'"></span>
                    </div>
                </button>
            </template>
            <p class="text-[11px] text-muted-foreground px-1 pt-0.5"
               x-text="post.poll.totalVotes === 0 ? @js(__('personal.poll_no_votes'))
                       : (post.poll.totalVotes === 1 ? @js(__('personal.poll_vote_one'))
                       : @js(__('personal.poll_votes')).replace(':count', post.poll.totalVotes))"></p>
        </div>
    </template>

    {{-- Collage (tap to open viewer) --}}
    <div x-show="post.images.length" class="grid gap-0.5" :class="post.images.length === 1 ? 'grid-cols-1' : 'grid-cols-2'">
        <template x-for="(img, idx) in post.images.slice(0, 4)" :key="img.url">
            <button type="button" @click="openLightbox(post.images, idx)" class="relative overflow-hidden block"
                    :class="(post.images.length === 3 && idx === 0) ? 'col-span-2' : ''">
                <img :src="img.url" alt="" class="w-full object-cover" :class="post.images.length === 1 ? 'max-h-96' : 'aspect-square'">
                <div x-show="idx === 3 && post.images.length > 4" class="absolute inset-0 bg-black/50 flex items-center justify-center text-white text-2xl font-bold">
                    <span x-text="'+' + (post.images.length - 4)"></span>
                </div>
            </button>
        </template>
    </div>

    {{-- Meta --}}
    <div x-show="post.likes > 0 || post.comments.length > 0 || post.views > 0" x-cloak class="flex items-center justify-between gap-3 px-4 py-2 text-[12px] text-gray-500">
        <button type="button" x-show="post.likes > 0" @click="openPostLikers(post)" class="flex items-center gap-1.5 hover:underline">
            <span class="w-4 h-4 rounded-full bg-primary text-white flex items-center justify-center text-[9px]"><i class="bi bi-heart-fill"></i></span>
            <span x-text="post.likes"></span>
        </button>
        <div class="ml-auto flex items-center gap-3">
            <button type="button" x-show="post.comments.length > 0" @click="post.showComments = true" class="hover:underline"
                    x-text="post.comments.length + ' ' + (post.comments.length === 1 ? @js(__('personal.comment_one')) : @js(__('personal.comments_many')))"></button>
            {{-- Views — count for everyone; the owner can tap to see who viewed it --}}
            <button type="button" x-show="post.views > 0"
                    @click="post.author.isMe && openPostViewers(post)"
                    class="flex items-center gap-1" :class="post.author.isMe ? 'hover:underline cursor-pointer text-gray-600' : 'cursor-default'">
                <i class="bi bi-eye"></i>
                <span x-text="post.views + ' ' + (post.views === 1 ? @js(__('personal.view_one')) : @js(__('personal.views_many')))"></span>
            </button>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex border-t border-gray-100 text-[13px] font-medium text-gray-600">
        <button type="button" @click="toggleLike(post)"
                class="m-press flex-1 flex items-center justify-center gap-2 py-2.5 hover:bg-muted transition-colors" :class="post.liked ? 'text-primary' : ''">
            <i class="bi" :class="post.liked ? 'bi-heart-fill' : 'bi-heart'"></i> {{ __('personal.like') }}
        </button>
        <button type="button" @click="post.showComments = !post.showComments"
                class="m-press flex-1 flex items-center justify-center gap-2 py-2.5 hover:bg-muted transition-colors">
            <i class="bi bi-chat"></i> {{ __('personal.comment_btn') }}
        </button>
        <button type="button" @click="sharePost(post)"
                class="m-press flex-1 flex items-center justify-center gap-2 py-2.5 hover:bg-muted transition-colors">
            <i class="bi bi-share"></i> {{ __('personal.share') }}
        </button>
    </div>

    {{-- Comments --}}
    <div x-show="post.showComments" x-cloak class="px-4 pb-3 pt-2 border-t border-gray-100 space-y-2">
        <template x-for="c in post.comments" :key="c.id">
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
            <input type="text" x-model="post.commentDraft" @keydown.enter.prevent="addComment(post)"
                   placeholder="{{ __('personal.write_comment') }}"
                   class="flex-1 bg-muted rounded-full px-4 py-2 text-sm text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/40">
            <button type="button" @click="addComment(post)" :disabled="!post.commentDraft.trim()"
                    class="m-press w-9 h-9 flex-shrink-0 rounded-full flex items-center justify-center transition-colors"
                    :class="post.commentDraft.trim() ? 'text-primary hover:bg-accent' : 'text-muted-foreground'" aria-label="{{ __('personal.send_comment') }}">
                <i class="bi bi-send-fill"></i>
            </button>
        </div>
    </div>
</article>
