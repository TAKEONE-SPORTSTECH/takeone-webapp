{{--
    The club front page's overlays — the achievement story, a news post with its
    likes and comments, every update, the photo viewer, the club film and perk
    collection. Shared by desktop and mobile: on a phone each one rises as a bottom
    sheet, on a desktop it opens as a centred card. Every one carries the gradient
    header band (Design Rule #8) in the club's colour.

    Lives inside the page's x-data="clubPage" scope; teleported to <body> so a
    transformed ancestor can never clip it. Needs $cpMobile, $cpAccent, $cpYoutube,
    $cpName, $cpLogo.
--}}
@php
    $cpPanel = $cpMobile ? 'cp-sheet cp-fade' : 'cp-modal cp-fade';
    $cpWrapAlign = $cpMobile ? 'align-items:flex-end;padding:0' : '';
    $cpBandStyle = 'background: linear-gradient(150deg, '.$cpAccent.', '.$cpAccent.'b0);';
@endphp
<x-medal-icon-defs />

<template x-teleport="body">
<div class="cp" :class="dark && 'cp-dark'" style="min-height:0;background:none;">

    @if($cpAchievements->isNotEmpty())
    {{-- ===== Achievement story ===== --}}
    <div x-show="ach" x-cloak class="cp-overlay" style="{{ $cpWrapAlign }}" @click.self="closeAll()">
        <template x-if="ach">
            <div class="{{ $cpPanel }}" style="display:flex;flex-direction:column;{{ $cpMobile ? '' : 'width:min(760px,100%);' }}" role="dialog" aria-modal="true">
                <div class="cp-band" style="{{ $cpBandStyle }}">
                    @if($cpMobile)<div class="cp-handle"></div>@endif
                    <div style="display:flex;gap:12px;align-items:flex-start;">
                        <span class="cp-round" style="width:48px;height:48px;border-radius:16px;background:rgba(255,255,255,.2);"><i class="bi bi-trophy-fill" style="font-size:20px;"></i></span>
                        <div style="flex:1;min-width:0;">
                            <h3 style="font-size:20px;font-weight:800;line-height:1.15;" x-text="ach.title"></h3>
                            <p style="font-size:12px;color:rgba(255,255,255,.85);margin-top:3px;" x-text="[ach.location, ach.date].filter(Boolean).join(' · ')"></p>
                        </div>
                        <button type="button" class="cp-x" @click="closeAll()" aria-label="{{ __('shared.close') }}"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;" x-show="ach.gold + ach.silver + ach.bronze > 0">
                        <template x-for="m in [[1, ach.gold], [2, ach.silver], [3, ach.bronze]]" :key="m[0]">
                            <span x-show="m[1] > 0" class="cp-badge" style="background:rgba(255,255,255,.2);">
                                <svg width="16" height="21" aria-hidden="true"><use :href="'#medal-place-' + m[0]"></use></svg>
                                <span x-text="m[1] + ' ' + [@js(__('clubs::page.gold')), @js(__('clubs::page.silver')), @js(__('clubs::page.bronze'))][m[0] - 1]"></span>
                            </span>
                        </template>
                    </div>
                </div>
                <div style="flex:1;overflow-y:auto;">
                    <template x-if="ach.images.length">
                        <div style="position:relative;background:#111;">
                            <img :src="ach.images[achImg]" alt="" style="width:100%;height:{{ $cpMobile ? '240px' : '360px' }};object-fit:cover;display:block;">
                            <template x-if="ach.images.length > 1">
                                <div style="position:absolute;inset-inline:0;bottom:12px;display:flex;justify-content:center;gap:6px;">
                                    <template x-for="(img, i) in ach.images" :key="i">
                                        <button type="button" @click="achImg = i" :aria-label="i + 1" :style="'height:7px;border-radius:9px;background:#fff;opacity:' + (achImg === i ? 1 : .5) + ';width:' + (achImg === i ? '18px' : '7px')"></button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                    <div style="padding:22px;display:grid;gap:14px;">
                        <div style="display:flex;gap:8px;flex-wrap:wrap;" x-show="ach.bouts || ach.category">
                            <span class="cp-badge" style="background:var(--cp-accent-soft);color:var(--cp-accent);" x-show="ach.category"><i class="bi bi-tag"></i><span x-text="ach.category"></span></span>
                            <span class="cp-badge" style="background:var(--cp-accent-soft);color:var(--cp-accent);" x-show="ach.bouts"><i class="bi bi-bar-chart-fill"></i><span x-text="ach.bouts + ' ' + @js(__('clubs::page.bouts'))"></span></span>
                            <span class="cp-badge" style="background:#e8f6ee;color:#15803d;" x-show="ach.wins"><i class="bi bi-trophy"></i><span x-text="ach.wins + ' ' + @js(__('clubs::page.wins'))"></span></span>
                        </div>
                        <p class="cp-muted" style="white-space:pre-line;line-height:1.7;" x-show="ach.description" x-text="ach.description"></p>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;" x-show="ach.chips.length">
                            <template x-for="c in ach.chips" :key="c"><span class="cp-pill" style="min-height:0;padding:6px 12px;" x-text="c"></span></template>
                        </div>
                        <div x-show="ach.athletes.length">
                            <p class="cp-tag cp-red" style="margin-bottom:8px;">{{ __('clubs::page.athletes') }}</p>
                            <div style="display:grid;gap:6px;">
                                <template x-for="a in ach.athletes" :key="a.name">
                                    <a :href="a.url || null" class="cp-card" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:14px;">
                                        <span class="cp-round" style="width:32px;height:32px;background:var(--cp-accent);color:#fff;font-weight:800;font-size:13px;" x-text="a.name.charAt(0).toUpperCase()"></span>
                                        <span style="flex:1;min-width:0;"><b style="display:block;font-size:14px;" x-text="a.name"></b><small class="cp-muted" x-text="a.role"></small></span>
                                        <i class="bi bi-chevron-right cp-muted rtl:rotate-180" x-show="a.url"></i>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
    @endif

    @if($cpPosts->isNotEmpty())
    {{-- ===== One news post: like + comments ===== --}}
    <div x-show="post" x-cloak class="cp-overlay" style="{{ $cpWrapAlign }}" @click.self="closeAll()">
        <template x-if="post">
            <div class="{{ $cpPanel }}" style="display:flex;flex-direction:column;{{ $cpMobile ? '' : 'width:min(640px,100%);' }}" role="dialog" aria-modal="true">
                <div class="cp-band" style="{{ $cpBandStyle }}">
                    @if($cpMobile)<div class="cp-handle"></div>@endif
                    <div style="display:flex;gap:12px;align-items:center;">
                        @if($cpLogo)
                            <span style="width:48px;height:48px;flex-shrink:0;"><img src="{{ $cpLogo }}" alt="" style="width:100%;height:100%;object-fit:contain;"></span>
                        @else
                            <span class="cp-round" style="width:48px;height:48px;border-radius:16px;background:rgba(255,255,255,.2);"><i class="bi bi-newspaper" style="font-size:20px;"></i></span>
                        @endif
                        <div style="flex:1;min-width:0;">
                            <h3 style="font-size:18px;font-weight:800;line-height:1.15;">{{ $cpName }}</h3>
                            <p style="font-size:12px;color:rgba(255,255,255,.85);margin-top:3px;" x-text="[post.category, post.date].filter(Boolean).join(' · ')"></p>
                        </div>
                        <button type="button" class="cp-x" @click="closeAll()" aria-label="{{ __('shared.close') }}"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
                <div style="flex:1;overflow-y:auto;padding-bottom:env(safe-area-inset-bottom);">
                    <img x-show="post.image" :src="post.image" alt="" style="width:100%;max-height:380px;object-fit:cover;display:block;">
                    <div style="padding:20px 22px;">
                        <p style="white-space:pre-line;line-height:1.7;" x-text="post.body"></p>
                        <div style="display:flex;gap:10px;margin-top:16px;">
                            <button type="button" class="cp-pill" :class="post.liked && 'is-on'" @click="like(post)">
                                <i class="bi" :class="post.liked ? 'bi-heart-fill' : 'bi-heart'"></i><span x-text="post.likes"></span>
                            </button>
                            <span class="cp-pill" style="pointer-events:none;"><i class="bi bi-chat"></i><span x-text="post.comments.length"></span></span>
                            <button type="button" class="cp-pill" @click="share()"><i class="bi bi-share"></i>{{ __('clubs::page.share') }}</button>
                        </div>
                        <div style="margin-top:18px;display:grid;gap:10px;">
                            <template x-for="c in post.comments" :key="c.id">
                                <div style="display:flex;gap:10px;align-items:flex-start;">
                                    <span class="cp-round" style="width:32px;height:32px;overflow:hidden;background:var(--cp-sand);font-weight:800;font-size:12px;">
                                        <template x-if="c.avatar"><img :src="c.avatar" alt="" class="cp-cover"></template>
                                        <template x-if="!c.avatar"><span x-text="(c.user_name || '?').charAt(0).toUpperCase()"></span></template>
                                    </span>
                                    <div style="flex:1;min-width:0;background:var(--cp-paper);border-radius:14px;padding:9px 12px;">
                                        <b style="font-size:13px;" x-text="c.user_name"></b>
                                        <p style="font-size:14px;white-space:pre-line;" x-text="c.body"></p>
                                        <p class="cp-muted" style="font-size:11px;margin-top:3px;">
                                            <span x-text="c.time_ago"></span>
                                            <button type="button" x-show="c.is_owner" @click="uncomment(post, c)" style="color:#dc2626;margin-inline-start:8px;font-weight:700;">{{ __('shared.delete') }}</button>
                                        </p>
                                    </div>
                                </div>
                            </template>
                        </div>
                        @auth
                            <form @submit.prevent="comment(post)" style="display:flex;gap:8px;margin-top:16px;">
                                <input type="text" class="cp-input" x-model="draft" maxlength="1000" placeholder="{{ __('clubs::page.write_comment') }}" aria-label="{{ __('clubs::page.write_comment') }}">
                                <button type="submit" class="cp-cta" style="min-height:46px;padding:0 18px;" :disabled="sending || !draft.trim()" aria-label="{{ __('clubs::page.send') }}"><i class="bi bi-send-fill cp-flip"></i></button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="cp-pill" style="margin-top:16px;width:100%;">{{ __('clubs::page.sign_in_to_comment') }}</a>
                        @endauth
                    </div>
                </div>
            </div>
        </template>
    </div>
    @endif

    @if($cpPosts->isNotEmpty())
    {{-- ===== Every update ===== --}}
    <div x-show="newsAll" x-cloak class="cp-overlay" style="{{ $cpWrapAlign }}" @click.self="closeAll()">
        <div class="{{ $cpPanel }}" style="display:flex;flex-direction:column;{{ $cpMobile ? '' : 'width:min(720px,100%);' }}" role="dialog" aria-modal="true" x-show="newsAll">
            <div class="cp-band" style="{{ $cpBandStyle }}">
                @if($cpMobile)<div class="cp-handle"></div>@endif
                <div style="display:flex;gap:12px;align-items:center;">
                    <span class="cp-round" style="width:48px;height:48px;border-radius:16px;background:rgba(255,255,255,.2);"><i class="bi bi-newspaper" style="font-size:20px;"></i></span>
                    <div style="flex:1;min-width:0;">
                        <h3 style="font-size:18px;font-weight:800;">{{ __('clubs::page.news_title') }}</h3>
                        <p style="font-size:12px;color:rgba(255,255,255,.85);margin-top:3px;">{{ $cpName }}</p>
                    </div>
                    <button type="button" class="cp-x" @click="closeAll()" aria-label="{{ __('shared.close') }}"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="cp-sheet-body" style="display:grid;gap:10px;">
                <template x-for="(p, i) in d.posts" :key="p.id">
                    <button type="button" class="cp-card cp-lift" style="display:flex;gap:14px;padding:12px;text-align:start;border-radius:18px;" @click="openPost(i)">
                        <span style="width:84px;height:84px;border-radius:14px;overflow:hidden;flex-shrink:0;background:var(--cp-sand);display:grid;place-items:center;">
                            <template x-if="p.image"><img :src="p.image" alt="" class="cp-cover"></template>
                            <template x-if="!p.image"><i class="bi bi-newspaper cp-red" style="font-size:26px;"></i></template>
                        </span>
                        <span style="flex:1;min-width:0;">
                            <span class="cp-tag cp-red" x-text="[p.category, p.date].filter(Boolean).join(' · ')"></span>
                            <b style="display:block;margin-top:4px;" x-text="p.title"></b>
                            <span class="cp-muted" style="font-size:12px;display:flex;gap:12px;margin-top:6px;">
                                <span><i class="bi bi-heart"></i> <span x-text="p.likes"></span></span>
                                <span><i class="bi bi-chat"></i> <span x-text="p.comments.length"></span></span>
                            </span>
                        </span>
                    </button>
                </template>
            </div>
        </div>
    </div>
    @endif

    @if($cpGallery->isNotEmpty())
    {{-- ===== Photo viewer ===== --}}
    <div x-show="gIndex > -1" x-cloak class="cp-viewer" @click.self="closeAll()" role="dialog" aria-modal="true">
        <template x-if="gIndex > -1">
            <div style="width:100%;max-width:1100px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;">
                    <div style="min-width:0;">
                        <p class="cp-tag" style="color:rgba(255,255,255,.55);">{{ $cpName }}</p>
                        <h3 style="font-size:22px;font-weight:700;" x-text="d.gallery[gIndex].caption || @js(__('clubs::page.gallery_title'))"></h3>
                    </div>
                    <span style="display:flex;gap:8px;align-items:center;">
                        <span class="cp-muted" style="color:rgba(255,255,255,.6);font-size:13px;" x-text="(gIndex + 1) + ' / ' + d.gallery.length"></span>
                        <button type="button" class="cp-x" @click="closeAll()" aria-label="{{ __('shared.close') }}"><i class="bi bi-x-lg"></i></button>
                    </span>
                </div>
                <div style="display:flex;gap:10px;align-items:center;justify-content:center;">
                    <button type="button" class="cp-x" x-show="d.gallery.length > 1" @click="step(-1)" aria-label="{{ __('clubs::page.previous') }}"><i class="bi bi-chevron-left rtl:rotate-180"></i></button>
                    <img :src="d.gallery[gIndex].src" :alt="d.gallery[gIndex].caption || ''">
                    <button type="button" class="cp-x" x-show="d.gallery.length > 1" @click="step(1)" aria-label="{{ __('clubs::page.next') }}"><i class="bi bi-chevron-right rtl:rotate-180"></i></button>
                </div>
            </div>
        </template>
    </div>
    @endif

    {{-- ===== The club film ===== --}}
    @if($cpYoutube)
        <div x-show="video" x-cloak class="cp-viewer" @click.self="closeAll()" role="dialog" aria-modal="true">
            <template x-if="video">
                <div style="width:100%;max-width:1000px;">
                    <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
                        <button type="button" class="cp-x" @click="closeAll()" aria-label="{{ __('shared.close') }}"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div style="position:relative;aspect-ratio:16/9;border-radius:18px;overflow:hidden;background:#000;">
                        <iframe src="https://www.youtube-nocookie.com/embed/{{ $cpYoutube }}?autoplay=1&rel=0&playsinline=1" title="{{ $cpName }}"
                                allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"
                                style="position:absolute;inset:0;width:100%;height:100%;border:0;"></iframe>
                    </div>
                </div>
            </template>
        </div>
    @endif

    @if($cpPerks->isNotEmpty())
    {{-- ===== Collect a perk ===== --}}
    <div x-show="perk.open" x-cloak class="cp-overlay" style="{{ $cpWrapAlign }}" @click.self="closeAll()">
        <div class="{{ $cpPanel }}" style="display:flex;flex-direction:column;{{ $cpMobile ? '' : 'width:min(420px,100%);' }}" role="dialog" aria-modal="true" x-show="perk.open">
            <div class="cp-band" style="{{ $cpBandStyle }}">
                @if($cpMobile)<div class="cp-handle"></div>@endif
                <div style="display:flex;gap:12px;align-items:center;">
                    <span class="cp-round" style="width:48px;height:48px;border-radius:16px;background:rgba(255,255,255,.2);"><i class="bi bi-ticket-perforated-fill" style="font-size:20px;"></i></span>
                    <div style="flex:1;min-width:0;">
                        <h3 style="font-size:18px;font-weight:800;" x-text="perk.title || @js(__('clubs::page.perk_title'))"></h3>
                        <p style="font-size:12px;color:rgba(255,255,255,.85);margin-top:3px;">{{ $cpName }}</p>
                    </div>
                    <button type="button" class="cp-x" @click="closeAll()" aria-label="{{ __('shared.close') }}"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="cp-sheet-body" style="text-align:center;">
                <div x-show="perk.view === 'loading'" style="padding:28px 0;">
                    <i class="bi bi-arrow-repeat cp-red animate-spin" style="font-size:30px;display:inline-block;"></i>
                    <p class="cp-muted" style="margin-top:10px;font-size:14px;">{{ __('clubs::club.perk_loading') }}</p>
                </div>
                <div x-show="perk.view === 'members_only'" style="padding:18px 0;">
                    <span class="cp-icon-tile" style="width:56px;height:56px;margin:0 auto;"><i class="bi bi-lock-fill" style="font-size:24px;"></i></span>
                    <h4 style="font-size:20px;font-weight:700;margin-top:12px;">{{ __('clubs::club.perk_members_only') }}</h4>
                    <p class="cp-muted" style="font-size:14px;margin-top:6px;" x-text="perk.message"></p>
                    <button type="button" class="cp-cta" style="margin-top:18px;" @click="closeAll(); join()">{{ __('clubs::page.join_to_unlock') }}</button>
                </div>
                <div x-show="perk.view === 'already'" style="padding:18px 0;">
                    <span class="cp-icon-tile" style="width:56px;height:56px;margin:0 auto;background:#e8f6ee;color:#15803d;"><i class="bi bi-check-circle-fill" style="font-size:24px;"></i></span>
                    <h4 style="font-size:20px;font-weight:700;margin-top:12px;">{{ __('clubs::club.perk_already') }}</h4>
                    <p class="cp-muted" style="font-size:14px;margin-top:6px;" x-show="perk.collectedAt" x-text="perk.collectedAt"></p>
                </div>
                <div x-show="perk.view === 'picker'" style="text-align:start;">
                    <h4 style="font-size:18px;font-weight:700;">{{ __('clubs::club.perk_who') }}</h4>
                    <p class="cp-muted" style="font-size:13px;margin:4px 0 12px;">{{ __('clubs::club.perk_who_sub') }}</p>
                    <div style="display:grid;gap:8px;">
                        <template x-for="m in perk.members" :key="m.id">
                            <button type="button" class="cp-card" :disabled="m.already_collected" @click="!m.already_collected && collectFor(m.id)"
                                    style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:14px;text-align:start;" :style="m.already_collected && 'opacity:.55;cursor:not-allowed'">
                                <span class="cp-portrait" style="width:30px;border-radius:8px;">
                                    <img :src="m.profile_picture ? '/file/' + m.profile_picture : m.placeholder" alt="">
                                </span>
                                <span style="flex:1;min-width:0;"><b style="display:block;font-size:14px;" x-text="m.name"></b>
                                    <small :style="m.already_collected ? 'color:#15803d' : ''" class="cp-muted" x-text="m.already_collected ? @js(__('clubs::club.perk_already')) : @js(__('clubs::club.perk_tap_collect'))"></small></span>
                                <i class="bi bi-chevron-right cp-muted rtl:rotate-180" x-show="!m.already_collected"></i>
                            </button>
                        </template>
                    </div>
                </div>
                <div x-show="perk.view === 'code'" style="padding:8px 0;">
                    <p class="cp-muted" style="font-size:14px;margin-bottom:14px;">{{ __('clubs::club.perk_show_code') }}</p>
                    <div style="display:flex;align-items:center;gap:8px;border:1px dashed var(--cp-line);border-radius:14px;padding:14px 16px;background:var(--cp-paper);">
                        <span class="cp-red" style="flex:1;font-family:monospace;font-weight:800;font-size:20px;letter-spacing:.15em;" x-text="perk.value"></span>
                        <button type="button" @click="copyCode()" aria-label="{{ __('clubs::page.copy') }}"><i class="bi" :class="perk.copied ? 'bi-check-lg' : 'bi-copy'"></i></button>
                    </div>
                    <p x-show="perk.copied" style="color:#15803d;font-size:12px;margin-top:8px;">{{ __('clubs::club.perk_copied') }}</p>
                </div>
                <div x-show="perk.view === 'qr'" style="padding:8px 0;">
                    <p class="cp-muted" style="font-size:14px;margin-bottom:14px;">{{ __('clubs::club.perk_show_qr') }}</p>
                    <div x-ref="perkQr" style="display:flex;justify-content:center;background:#fff;padding:12px;border-radius:16px;"></div>
                </div>
                <div x-show="perk.view === 'error'" style="padding:18px 0;">
                    <span class="cp-icon-tile" style="width:56px;height:56px;margin:0 auto;background:#fde8e8;color:#dc2626;"><i class="bi bi-exclamation-triangle-fill" style="font-size:24px;"></i></span>
                    <p class="cp-muted" style="font-size:14px;margin-top:12px;" x-text="perk.message || @js(__('clubs::club.perk_error'))"></p>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
</template>
