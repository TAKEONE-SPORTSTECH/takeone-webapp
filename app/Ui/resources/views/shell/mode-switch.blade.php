{{-- Play / Manage — the person's own control (new UI).
     Play is the person's ROUND profile picture (owner's decision, 2026-10-03:
     a deliberate exception to "Profile pictures are portrait 3:4" for this one
     control). Tapping it while already in Play opens the account menu
     ($onFace: an Alpine expression the host supplies). Manage appears only for
     someone who manages something (App\Ui\NewUi::manageTarget()).
     Props: $onFace (Alpine expr), $manage (bool, currently managing) --}}
@php
    $mu = Auth::user();
    $manageTarget = \App\Ui\NewUi::manageTarget($mu);
    $isManage = $manage ?? false;
@endphp
<div class="t1-idsw">
    <div class="t1-modesw {{ $isManage ? 'is-manage' : '' }}" role="group" aria-label="{{ __('ui::messages.your_account') }}"
         @if(! $manageTarget) style="grid-template-columns:1fr" @endif>
        @if($isManage)
        <a href="{{ route('me.home') }}" class="t1-play" aria-label="{{ __('ui::messages.back_to_play') }}" title="{{ __('ui::messages.back_to_play') }}">
        @else
        <button type="button" class="t1-play" @click="{{ $onFace }}"
                aria-label="{{ __('ui::messages.your_account') }}" title="{{ __('ui::messages.your_account') }}">
        @endif
            <span class="t1-face">
                @if($mu->profile_picture)
                    <img src="{{ file_url($mu->profile_picture) }}?v={{ optional($mu->updated_at)->timestamp }}" alt="">
                @else
                    <x-gender-avatar :gender="$mu->gender" class="w-full h-full" />
                @endif
            </span>
            <span class="t1-lbl">{{ __('ui::messages.play') }}</span>
        @if($isManage)</a>@else</button>@endif
        @if($manageTarget)
            @if($manageTarget['method'] === 'post')
                <form method="POST" action="{{ $manageTarget['url'] }}" style="display:contents">
                    @csrf
                    @foreach($manageTarget['fields'] ?? [] as $f => $v)<input type="hidden" name="{{ $f }}" value="{{ $v }}">@endforeach
                    <button type="submit" class="t1-manage" title="{{ __('ui::messages.manage_title') }}">
                        <i class="bi bi-briefcase"></i><span class="t1-lbl">{{ __('ui::messages.manage') }}</span>
                    </button>
                </form>
            @else
                <a href="{{ $manageTarget['url'] }}" class="t1-manage" title="{{ __('ui::messages.manage_title') }}">
                    <i class="bi bi-briefcase"></i><span class="t1-lbl">{{ __('ui::messages.manage') }}</span>
                </a>
            @endif
        @endif
    </div>
</div>
