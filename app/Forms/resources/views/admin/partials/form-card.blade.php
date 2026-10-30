{{-- One form in the list. The whole card opens its results; the pencil
     opens the builder (a full load — the builder is its own page). --}}
@php $ttl = app()->getLocale() === 'ar' && $f['title_ar'] ? $f['title_ar'] : $f['title']; @endphp
<div class="fa-card fa-fcard" style="position:relative">
    <a href="{{ $f['url'] }}" {{ ($shell ?? false) ? 'data-shell-link' : '' }} class="fa-fcard-main">
        <span class="fa-fcard-ic">@if(! empty($f['cover_url']))<img src="{{ $f['cover_url'] }}" alt="" loading="lazy">@else<i class="bi bi-ui-checks-grid"></i>@endif</span>
        <span style="flex:1;min-width:0">
            <span style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                <span class="fa-pill" style="background:{{ $f['active'] ? '#16a34a' : '#9aa0b4' }}">{{ $f['active'] ? __('forms::admin.active') : __('forms::admin.closed') }}</span>
                <span class="fa-tag">{{ __('forms::admin.type_'.$f['type']) }}</span>
                <span class="fa-tag">{{ $f['owner'] ?? __('forms::admin.platform') }}</span>
            </span>
            <b class="fa-fcard-t">{{ $ttl }}</b>
            <span class="fa-sub" style="display:block">{{ $f['questions'] }} {{ __('forms::admin.questions') }} · {{ $f['pages'] }} {{ __('forms::admin.pages') }} · {{ __('forms::admin.created', ['when' => $f['created']?->translatedFormat('j M Y')]) }}</span>
        </span>
        <span class="fa-fcard-n"><b class="num">{{ $f['responses'] }}</b><small>{{ $f['responses'] === 1 ? __('forms::admin.answers_one') : __('forms::admin.answers') }}</small>
            <small style="display:block;margin-top:2px">{{ $f['last'] ? \Illuminate\Support\Carbon::parse($f['last'])->diffForHumans() : __('forms::admin.no_answers') }}</small></span>
    </a>
    {{-- Hand it to somebody, straight from the list --}}
    <div class="fa-fcard-acts" x-data="{ ok: false }">
        <button type="button" @click="navigator.clipboard?.writeText(@js($f['public_url'])).then(() => { ok = true; window.showToast?.('success', @js(__('forms::admin.copied'))); setTimeout(() => ok = false, 1500) })"><i class="bi" :class="ok ? 'bi-check2' : 'bi-link-45deg'"></i>{{ __('forms::admin.copy_link') }}</button>
        <a href="https://wa.me/?text={{ rawurlencode(trim($f['title']."\n".$f['title_ar'])."\n".$f['public_url']) }}" target="_blank" rel="noopener" class="wa"><i class="bi bi-whatsapp"></i>WhatsApp</a>
        <a href="{{ $f['poster_url'] }}" data-no-shell><i class="bi bi-qr-code"></i>{{ __('forms::admin.poster_short') }}</a>
        <a href="{{ $f['pdf_url'] }}" data-no-shell><i class="bi bi-file-earmark-pdf"></i>PDF</a>
    </div>
    <a href="{{ $f['edit_url'] }}" data-no-shell class="fa-fcard-edit" title="{{ __('forms::admin.edit') }}" aria-label="{{ __('forms::admin.edit') }}"><i class="bi bi-pencil-square"></i></a>
</div>
