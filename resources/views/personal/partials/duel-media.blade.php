{{-- Result media block (gallery + uploader). Expects $d in scope + the duel-show Alpine state
     (media, mediaBusy, showLink, linkUrl, uploadMedia, addLink, removeMedia). --}}
<div class="flex items-start justify-between gap-2">
    <div>
        <h3 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-camera-reels-fill text-primary"></i> Challenge media</h3>
        <p class="text-[11px] text-muted-foreground mt-0.5">Players &amp; witnesses can add photos, clips or a video link.</p>
    </div>
    <span class="text-[11px] font-semibold text-muted-foreground flex-shrink-0 mt-0.5" x-show="media.length" x-cloak x-text="media.length + (media.length === 1 ? ' item' : ' items')"></span>
</div>

{{-- gallery --}}
<div class="grid grid-cols-3 gap-2 mt-3" x-show="media.length" x-cloak>
    <template x-for="m in media" :key="m.id">
        <div class="relative group aspect-square rounded-xl overflow-hidden bg-muted border border-border">
            <template x-if="m.type === 'image'">
                <a :href="m.url" target="_blank" rel="noopener"><img :src="m.url" :alt="m.caption || ''" class="w-full h-full object-cover"></a>
            </template>
            <template x-if="m.type === 'video'">
                <video :src="m.url" controls class="w-full h-full object-cover bg-black"></video>
            </template>
            <template x-if="m.type === 'link'">
                <a :href="m.url" target="_blank" rel="noopener" class="w-full h-full grid place-items-center text-center p-2 bg-accent">
                    <span><i class="bi bi-play-btn-fill text-2xl text-primary"></i><span class="block text-[10px] font-semibold text-primary mt-1 truncate" x-text="m.caption || 'Video link'"></span></span>
                </a>
            </template>
            <button type="button" x-show="m.mine" @click.prevent="removeMedia(m)"
                    class="absolute top-1 right-1 w-6 h-6 rounded-full bg-black/55 text-white grid place-items-center text-xs"><i class="bi bi-x-lg"></i></button>
        </div>
    </template>
</div>
<div x-show="!media.length" x-cloak class="mt-3 rounded-xl border border-dashed border-border bg-muted/30 py-4 text-center">
    <i class="bi bi-camera-reels text-xl text-muted-foreground"></i>
    <p class="text-[11px] text-muted-foreground mt-1">No result media yet — add a photo, clip, or video link.</p>
</div>

{{-- add controls --}}
<div class="mt-3">
    <div class="grid grid-cols-3 gap-2">
        <label class="m-press cursor-pointer rounded-xl border-2 border-dashed border-border hover:border-primary hover:bg-accent/40 transition-colors flex flex-col items-center justify-center py-3 gap-1"
               :class="mediaBusy ? 'opacity-50 pointer-events-none' : ''">
            <i class="bi bi-image text-lg text-primary"></i>
            <span class="text-[11px] font-bold text-foreground">Photo</span>
            <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" @change="uploadMedia('image', $event)">
        </label>
        <label class="m-press cursor-pointer rounded-xl border-2 border-dashed border-border hover:border-primary hover:bg-accent/40 transition-colors flex flex-col items-center justify-center py-3 gap-1"
               :class="mediaBusy ? 'opacity-50 pointer-events-none' : ''">
            <i class="bi bi-camera-video text-lg text-primary"></i>
            <span class="text-[11px] font-bold text-foreground">Video</span>
            <input type="file" accept="video/mp4,video/quicktime,video/webm" class="hidden" @change="uploadMedia('video', $event)">
        </label>
        <button type="button" @click="showLink = !showLink"
                class="m-press rounded-xl border-2 border-dashed transition-colors flex flex-col items-center justify-center py-3 gap-1"
                :class="showLink ? 'border-primary bg-accent/40' : 'border-border hover:border-primary hover:bg-accent/40'">
            <i class="bi bi-link-45deg text-lg text-primary"></i>
            <span class="text-[11px] font-bold text-foreground">Link</span>
        </button>
    </div>

    <div x-show="showLink" x-cloak
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         class="flex items-center gap-2 mt-2">
        <input x-model="linkUrl" type="url" placeholder="Paste a video link (YouTube, etc.)"
               class="flex-1 px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
        <button type="button" @click="addLink()" :disabled="mediaBusy"
                class="m-press px-4 py-2.5 rounded-xl text-white text-sm font-bold disabled:opacity-50 flex-shrink-0" style="background: {{ $d['color'] }};">Add</button>
    </div>

    <div x-show="mediaBusy" x-cloak class="flex items-center justify-center gap-2 mt-2 text-[11px] font-semibold text-primary">
        <i class="bi bi-arrow-repeat animate-spin"></i> Uploading…
    </div>
</div>
