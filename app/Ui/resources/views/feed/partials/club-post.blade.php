{{-- A club timeline post in the new Feed (Alpine scope: post, from $allPosts).
     Like and comment stay local, exactly as the classic club card behaves —
     club posts have no like/comment endpoint yet. --}}
<article class="t1-post"
         x-data="{ liked:false, likes: post.likes, showC:false, draft:'', cmts: (post.commentList || []), cc: post.comments,
                   addC() { const t=this.draft.trim(); if(!t) return; this.cmts.push({id:Date.now(), name:me.name, avatar:me.avatar, body:t}); this.cc++; this.draft=''; this.showC=true; } }">
    <div class="t1-ph">
        <span class="t1-pv m org" style="--pc:hsl(250 55% 60%)">
            <template x-if="post.club.logo"><img :src="post.club.logo" alt=""></template>
            <template x-if="!post.club.logo"><i class="bi bi-buildings"></i></template>
        </span>
        <span style="min-width:0"><b x-text="post.club.name"></b><small x-text="post.time + ' · ' + @js(__('personal.club'))"></small></span>
        <span class="t1-kind" x-text="post.category || @js(__('personal.club_update'))"></span>
    </div>
    <p class="t1-pt" x-show="post.body" x-text="post.body"></p>
    <template x-if="post.image">
        <button type="button" class="t1-pimg" @click="openLightbox([{ url: post.image }], 0)"><img :src="post.image" alt="" loading="lazy"></button>
    </template>
    <template x-if="post.cover && !post.image">
        <div class="t1-hl" :style="`background: linear-gradient(150deg, ${post.cover.color}, ${post.cover.color}b0)`">
            <i class="bi" :class="post.cover.icon"></i><span x-text="post.cover.label"></span>
        </div>
    </template>
    <div class="t1-pa">
        <button type="button" @click="liked = !liked; likes += liked ? 1 : -1" :class="{ on: liked }"><i class="bi" :class="liked ? 'bi-heart-fill' : 'bi-heart'"></i> <span x-text="likes"></span></button>
        <button type="button" @click="showC = !showC"><i class="bi bi-chat"></i> <span x-text="cc"></span></button>
        <button type="button" @click="sharePost({ body: post.body || post.club.name })"><i class="bi bi-share"></i> {{ __('personal.share') }}</button>
    </div>
    <div class="t1-pc" x-show="showC" x-cloak>
        <template x-for="c in cmts" :key="c.id">
            <div class="t1-cm"><span class="t1-pv xs"><template x-if="c.avatar"><img :src="c.avatar" alt=""></template></span>
                <div><b x-text="c.name"></b><p x-text="c.body"></p></div></div>
        </template>
        <div class="t1-crow">
            <input type="text" x-model="draft" @keydown.enter.prevent="addC()" class="t1-field" placeholder="{{ __('personal.write_comment') }}">
            <button type="button" @click="addC()" :disabled="!draft.trim()" class="t1-send" :class="{ on: draft.trim() }" aria-label="{{ __('personal.send_comment') }}"><i class="bi bi-send-fill"></i></button>
        </div>
    </div>
</article>
