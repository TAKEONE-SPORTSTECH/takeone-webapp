{{-- New UI Feed body — shared by feed/desktop and feed/mobile.
     The behaviour is the platform's own newsFeed() (partials/news-feed-script):
     posting, photos, polls, highlights, likes, comments, edit/delete, views,
     moderation and realtime all run through it unchanged. Member posts render
     with the existing post-card partial. Expects the same variables as the
     classic home views ($user, $posts, $personalPosts, $followingPosts,
     $allPosts, $suggestions, $feedTabDots). --}}
@php
    $me = $user ?? Auth::user();
    $myAvatar = $me->profile_picture ? file_url($me->profile_picture).'?v='.optional($me->updated_at)->timestamp : null;
    $myClubs = $me->memberClubs()->wherePivot('status', 'active')->get(['tenants.id', 'tenants.club_name', 'tenants.logo', 'tenants.slug', 'tenants.country']);
    $postCount = count($personalPosts ?? []);
    $likeCount = collect($personalPosts ?? [])->sum(fn ($p) => (int) ($p['likes'] ?? 0));
@endphp
<div class="t1 t1-page" x-data="newsFeed()">
    {{-- Band: your clubs as the round row (stories were removed in July 2026). --}}
    <div class="t1-bleed">
        <header class="t1-band">
            <span class="t1-c1"></span>
            <div class="t1-band-in">
                <span class="t1-chipw"><i class="bi bi-newspaper"></i>{{ __('nav.tab_feed') }}</span>
                <div class="t1-stories">
                    <button type="button" class="t1-story" @click="$refs.ta.focus()">
                        <span class="t1-add"><i class="bi bi-plus-lg"></i></span><span class="t1-nm">{{ __('ui::feed.new_post') }}</span>
                    </button>
                    @foreach($myClubs as $club)
                        <a class="t1-story" href="{{ $club->url }}">
                            <span class="t1-ring">
                                <span class="t1-pv org" style="--pc:hsl(250 55% 60%);width:52px;height:68px;border-radius:13px;border:2.5px solid hsl(250 65% 60%)">
                                    @if($club->logo)<img src="{{ file_url($club->logo) }}" alt="">@else{{ mb_strtoupper(mb_substr($club->club_name, 0, 2)) }}@endif
                                </span>
                            </span>
                            <span class="t1-nm">{{ $club->club_name }}</span>
                        </a>
                    @endforeach
                    <a class="t1-story" href="{{ route('me.people') }}">
                        <span class="t1-add"><i class="bi bi-person-plus"></i></span><span class="t1-nm">{{ __('personal.find_people') }}</span>
                    </a>
                </div>
            </div>
        </header>
    </div>

    <div class="t1-fwrap t1-after">
        {{-- Left (wide desktop): you --}}
        <aside class="t1-fside t1-fleft">
            <div class="t1-box">
                <a href="{{ route('member.show', $me->uuid) }}" style="display:flex;gap:10px;align-items:center;text-decoration:none;color:inherit">
                    <span class="t1-pv m">@if($myAvatar)<img src="{{ $myAvatar }}" alt="">@else<x-gender-avatar :gender="$me->gender" class="w-full h-full" />@endif</span>
                    <span style="min-width:0"><b style="font-size:14px;display:block">{{ $me->full_name ?? $me->name }}</b><small style="color:var(--t1-soft);font-weight:600">{{ $myClubs->first()?->club_name }}</small></span>
                </a>
                <div class="t1-mestats">
                    <div><b>{{ $postCount }}</b><span>{{ __('ui::feed.posts') }}</span></div>
                    <div><b>{{ $likeCount }}</b><span>{{ __('ui::feed.likes') }}</span></div>
                    <div><b>{{ $myClubs->count() }}</b><span>{{ __('ui::feed.clubs') }}</span></div>
                </div>
                <div class="t1-links">
                    <a href="{{ route('member.show', $me->uuid) }}"><i class="bi bi-person"></i>{{ __('nav.my_profile') }}</a>
                    <a href="{{ route('me.packages') }}"><i class="bi bi-box"></i>{{ __('nav.my_packages') }}</a>
                    <a href="{{ route('member.show', $me->uuid) }}#goals"><i class="bi bi-graph-up-arrow"></i>{{ __('nav.my_progress') }}</a>
                    <a href="{{ route('me.payments') }}"><i class="bi bi-credit-card"></i>{{ __('nav.payments') }}</a>
                    <a href="{{ route('me.videos') }}"><i class="bi bi-camera-reels"></i>{{ __('nav.my_videos') }}</a>
                    <a href="{{ route('members.index') }}"><i class="bi bi-people"></i>{{ __('nav.family') }}</a>
                </div>
            </div>
        </aside>

        <div class="t1-fmain">
            {{-- Composer — same state and methods as the classic one --}}
            <div class="t1-composer">
                <input type="file" x-ref="photo" accept="image/*" multiple class="hidden" @change="pickImages($event)">
                <div class="t1-crow">
                    <span class="t1-pv s">@if($myAvatar)<img src="{{ $myAvatar }}" alt="">@else<x-gender-avatar :gender="$me->gender" class="w-full h-full" />@endif</span>
                    <textarea x-model="body" x-ref="ta" rows="1" @input="autoGrow($el)" class="t1-cin"
                              :placeholder="pollOpen ? @js(__('personal.poll_question')) : @js(__('personal.whats_on_your_mind'))"></textarea>
                    <button type="button" @click="submitPost()" :disabled="!canPost() || sending" class="t1-send" :class="{ on: canPost() && !sending }" aria-label="{{ __('personal.send_post') }}">
                        <i class="bi" :class="sending ? 'bi-arrow-repeat animate-spin' : 'bi-send-fill'"></i>
                    </button>
                </div>

                <div x-show="images.length" x-cloak class="t1-thumbs">
                    <template x-for="(img, i) in images" :key="img.url">
                        <div><img :src="img.url" alt=""><button type="button" @click="removeImage(i)" aria-label="{{ __('personal.remove_image') }}"><i class="bi bi-x"></i></button></div>
                    </template>
                </div>

                <div x-show="pollOpen" x-cloak class="t1-builder">
                    <div class="t1-blabel"><i class="bi bi-bar-chart-fill"></i> {{ __('personal.create_poll') }}</div>
                    <template x-for="(opt, i) in pollOptions" :key="i">
                        <div class="t1-crow">
                            <input type="text" x-model="pollOptions[i]" maxlength="120" class="t1-field" :placeholder="@js(__('personal.poll_option')).replace(':n', i + 1)">
                            <button type="button" x-show="pollOptions.length > 2" @click="removeOption(i)" class="t1-x" aria-label="{{ __('personal.remove_option') }}"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </template>
                    <button type="button" x-show="pollOptions.length < 6" @click="addOption()" class="t1-addopt"><i class="bi bi-plus-circle"></i> {{ __('personal.add_option') }}</button>
                </div>

                <div x-show="highlightOpen" x-cloak class="t1-builder">
                    <div class="t1-blabel"><i class="bi bi-stars"></i> {{ __('personal.create_highlight') }}</div>
                    <div class="t1-hlprev" :style="`background: linear-gradient(150deg, ${coverColor}, ${coverColor}b0)`">
                        <i class="bi" :class="coverIcon"></i>
                        <span x-text="coverLabel || @js(__('personal.highlight'))"></span>
                    </div>
                    <input type="text" x-model="coverLabel" maxlength="80" class="t1-field" placeholder="{{ __('personal.highlight_title') }}">
                    <div class="t1-swatches">
                        <template x-for="c in coverColors" :key="c"><button type="button" @click="coverColor = c" :class="{ on: coverColor === c }" :style="`background:${c}`" aria-label="{{ __('personal.pick_a_color') }}"></button></template>
                    </div>
                    <div class="t1-icons">
                        <template x-for="ic in coverIcons" :key="ic"><button type="button" @click="coverIcon = ic" :class="{ on: coverIcon === ic }"><i class="bi" :class="ic"></i></button></template>
                    </div>
                </div>

                <div class="t1-kinds">
                    <button type="button" style="--k:#16a34a" @click="$refs.photo.click()"><i class="bi bi-image"></i>{{ __('personal.photo') }}</button>
                    <button type="button" style="--k:#e11d48" onclick="window.showToast && window.showToast('info', @js(__('personal.video_soon')))"><i class="bi bi-camera-video"></i>{{ __('personal.video') }}</button>
                    <button type="button" style="--k:#d97706" @click="togglePoll()" :class="{ on: pollOpen }"><i class="bi bi-bar-chart"></i>{{ __('personal.poll') }}</button>
                    <button type="button" style="--k:#7c3aed" @click="toggleHighlight()" :class="{ on: highlightOpen }"><i class="bi bi-stars"></i>{{ __('personal.highlight') }}</button>
                    <a style="--k:#0284c7" href="{{ route('me.challenge.create') }}"><i class="bi bi-trophy"></i>{{ __('nav.tab_challenge') }}</a>
                </div>
            </div>

            {{-- All · Following · My clubs · Mine --}}
            <div class="t1-segs" role="group">
                @foreach(['all' => __('personal.all'), 'following' => __('personal.following'), 'club' => __('ui::feed.my_clubs'), 'mine' => __('personal.my_feeds')] as $k => $label)
                    <button type="button" @click="tab='{{ $k }}'; seenTab('{{ $k }}')" :class="{ on: tab==='{{ $k }}' }">{{ $label }}<span class="t1-sdot" x-show="dots.{{ $k }} && tab!=='{{ $k }}'" x-cloak></span></button>
                @endforeach
            </div>

            {{-- All: one stream of club and member posts --}}
            <template x-if="tab==='all'">
                <div style="display:grid;gap:12px">
                    <template x-for="post in allPosts" :key="post.kind + '-' + post.id">
                        <div>
                            <template x-if="post.kind === 'member'">@include('members::personal.partials.post-card')</template>
                            <template x-if="post.kind === 'club'">@include('ui::feed.partials.club-post')</template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Following / Mine --}}
            <template x-for="post in currentPosts" :key="post.id">
                @include('members::personal.partials.post-card')
            </template>

            {{-- My clubs --}}
            <div x-show="tab==='club'" x-cloak style="display:grid;gap:12px">
                <template x-for="post in allPosts.filter(p => p.kind === 'club')" :key="'c-' + post.id">
                    @include('ui::feed.partials.club-post')
                </template>
            </div>

            <div x-show="tab==='all' && allPosts.length===0" x-cloak class="t1-box t1-empty"><b>{{ __('personal.nothing_here_yet') }}</b>{{ __('personal.share_post_hint') }}</div>
            <div x-show="tab==='mine' && personalPosts.length===0" x-cloak class="t1-box t1-empty"><b>{{ __('personal.nothing_here_yet') }}</b>{{ __('personal.share_post_hint') }}</div>
            <div x-show="tab==='club' && !allPosts.some(p => p.kind === 'club')" x-cloak class="t1-box t1-empty"><b>{{ __('personal.club_empty') }}</b>{{ __('personal.club_empty_hint') }}</div>
            <div x-show="tab==='following' && followingPosts.length===0" x-cloak class="t1-box t1-empty"><b>{{ __('personal.following_empty') }}</b>{{ __('personal.following_empty_hint') }}</div>
        </div>

        {{-- Right (desktop): people and shortcuts --}}
        <aside class="t1-fside">
            <div class="t1-box" x-show="suggestions.length">
                <h4><i class="bi bi-people"></i>{{ __('ui::feed.people_you_may_know') }}</h4>
                <template x-for="s in suggestions.slice(0, 6)" :key="s.id">
                    <div class="t1-row">
                        <a :href="s.url" class="t1-pv s"><template x-if="s.avatar"><img :src="s.avatar" alt=""></template></a>
                        <a :href="s.url" class="t1-tx" style="text-decoration:none;color:inherit"><b x-text="s.name"></b></a>
                        <button type="button" class="t1-sbtn" :class="{ on: s.following }" @click="s.following ? unfollowSuggestion(s) : followSuggestion(s)" x-text="s.following ? @js(__('personal.following')) : @js(__('ui::feed.follow'))"></button>
                    </div>
                </template>
                <a class="t1-more" href="{{ route('me.people') }}" style="margin-top:8px">{{ __('personal.find_people') }} <i class="bi bi-chevron-right rtl:rotate-180"></i></a>
            </div>
            <div class="t1-box">
                <h4><i class="bi bi-lightning-charge"></i>{{ __('ui::feed.jump_to') }}</h4>
                <a class="t1-row" href="{{ route('me.schedule') }}"><i class="bi bi-calendar-week"></i><span class="t1-tx"><b>{{ __('nav.tab_schedule') }}</b></span><i class="bi bi-chevron-right rtl:rotate-180"></i></a>
                <a class="t1-row" href="{{ route('me.challenge') }}"><i class="bi bi-trophy"></i><span class="t1-tx"><b>{{ __('nav.tab_challenge') }}</b></span><i class="bi bi-chevron-right rtl:rotate-180"></i></a>
                <a class="t1-row" href="{{ route('me.events') }}"><i class="bi bi-calendar-heart"></i><span class="t1-tx"><b>{{ __('nav.tab_events') }}</b></span><i class="bi bi-chevron-right rtl:rotate-180"></i></a>
            </div>
        </aside>
    </div>

    {{-- Fullscreen image viewer (same state as the classic feed) --}}
    <div x-show="lightbox.open" x-cloak class="t1-lightbox" @keydown.escape.window="closeLightbox()"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <div class="t1-lbbar">
            <button type="button" @click="closeLightbox()" aria-label="{{ __('personal.close') }}"><i class="bi bi-x-lg"></i></button>
            <span x-show="lightbox.images.length > 1" x-text="(lightbox.index + 1) + ' / ' + lightbox.images.length"></span>
        </div>
        <div class="t1-lbstage" @click.self="closeLightbox()">
            <img :src="lightbox.images[lightbox.index]?.url" alt="">
            <button type="button" class="prev" x-show="lightbox.index > 0" @click="lbPrev()" aria-label="{{ __('personal.previous') }}"><i class="bi bi-chevron-left"></i></button>
            <button type="button" class="next" x-show="lightbox.index < lightbox.images.length - 1" @click="lbNext()" aria-label="{{ __('personal.next') }}"><i class="bi bi-chevron-right"></i></button>
        </div>
    </div>

    @include('members::personal.partials.post-viewers-modal')
    @include('partials.news-feed-script')
</div>
