{{--
    One comment under a bout, in the standalone watch design's own markup.

    It is the server-rendered twin of that page's `buildCommentEl()`: the two
    must produce the SAME DOM, because a comment posted without a reload is
    drawn by the JS one and a comment that was already there is drawn by this
    one, and a reader must not be able to tell which is which.

    A comment is addressed by its uuid. The design's numeric ids were Play's
    row ids; nothing here exposes one.
--}}
@php
    $isReply = $isReply ?? false;
    $replies = $c['replies'] ?? [];
@endphp

<div class="ytc-comment{{ $isReply ? ' ytc-reply' : '' }}" id="comment-{{ $c['key'] }}">
    <span class="ytc-avatar-link">
        <span class="ytc-avatar{{ $isReply ? ' ytc-avatar-sm' : '' }}"
              style="background: {{ $c['bg'] }}; display:grid; place-items:center; font-weight:700; font-size:13px; color:#fff;">{{ $c['initials'] }}</span>
    </span>
    <div class="ytc-body-wrap">
        <div class="ytc-meta">
            <span class="ytc-author">{{ $c['name'] }}</span>
            <span class="ytc-time">{{ $c['when'] }}</span>
        </div>
        <div class="ytc-text _comment-body" data-_comment-enhanced="0">{{ $c['text'] }}</div>
        <div class="ytc-actions">
            <button class="ytc-like-btn{{ $c['liked'] ? ' liked' : '' }}" data-id="{{ $c['key'] }}"
                    data-count="{{ $c['likes'] }}" title="{{ __('personal.like') }}">
                <svg viewBox="0 0 24 24"><path d="M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-2z"/></svg>
                <span class="ytc-like-count" data-id="{{ $c['key'] }}">{{ $c['likes'] > 0 ? $c['likes'] : '' }}</span>
            </button>
            <button class="ytc-dislike-btn" data-id="{{ $c['key'] }}" title="{{ __('events.bout_video_dislike') }}">
                <svg viewBox="0 0 24 24"><path d="M15 3H6c-.83 0-1.54.5-1.84 1.22l-3.02 7.05c-.09.23-.14.47-.14.73v2c0 1.1.9 2 2 2h6.31l-.95 4.57-.03.32c0 .41.17.79.44 1.06L10.83 23l6.59-6.59c.36-.36.58-.86.58-1.41V5c0-1.1-.9-2-2-2zm4 0v12h4V3h-4z"/></svg>
            </button>
            @unless ($isReply)
                <button class="ytc-reply-btn _comment-reply-trigger" data-comment-id="{{ $c['key'] }}">{{ __('events.bout_video_reply') }}</button>
            @endunless
            @if ($c['mine'])
                <div class="ytc-more-wrap">
                    <button class="ytc-more-btn">
                        <svg viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                    </button>
                    <div class="ytc-more-menu">
                        <button class="ytc-more-item ytc-more-delete _comment-delete-trigger" data-comment-id="{{ $c['key'] }}">
                            <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                            {{ __('shared.delete') }}
                        </button>
                    </div>
                </div>
            @endif
        </div>

        @unless ($isReply)
            <div class="ytc-reply-form" id="replyForm{{ $c['key'] }}" style="display:none">
                <span class="ytc-avatar-link">
                    <span class="ytc-avatar ytc-avatar-sm"
                          style="background: {{ \App\Models\BoutComment::tint(auth()->user()->full_name ?: auth()->user()->name) }}; display:grid; place-items:center; font-weight:700; font-size:11px; color:#fff;">{{ \App\Models\BoutComment::initials(auth()->user()->full_name ?: auth()->user()->name) }}</span>
                </span>
                <div class="ytc-input-wrap">
                    <textarea class="ytc-textarea ytc-reply-textarea" id="replyBody{{ $c['key'] }}"
                              maxlength="1000" placeholder="{{ __('events.bout_video_reply') }}…" rows="1"></textarea>
                    <div class="ytc-form-actions">
                        <button class="ytc-btn ytc-btn-cancel _comment-cancel-reply-trigger" data-comment-id="{{ $c['key'] }}">{{ __('shared.cancel') }}</button>
                        <button class="ytc-btn ytc-btn-submit _comment-submit-reply-trigger"
                                data-video-id="{{ $c['key'] }}" data-parent-id="{{ $c['key'] }}">{{ __('events.bout_video_reply') }}</button>
                    </div>
                </div>
            </div>

            @if (count($replies))
                <div class="ytc-replies-section">
                    <button class="ytc-replies-toggle" data-open="1" onclick="ytcToggleReplies(this, '{{ $c['key'] }}')">
                        <svg class="ytc-chevron" viewBox="0 0 24 24"><path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg>
                        <span class="ytc-reply-count">{{ trans_choice('events.bout_video_replies_count', count($replies), ['count' => count($replies)]) }}</span>
                    </button>
                    <div class="ytc-replies-list" id="replies-{{ $c['key'] }}" style="display:block;padding-top:8px;">
                        @foreach ($replies as $r)
                            @include('personal.mobile.partials.bout-comment', ['c' => $r, 'isReply' => true])
                        @endforeach
                    </div>
                </div>
            @endif
        @endunless
    </div>
</div>
