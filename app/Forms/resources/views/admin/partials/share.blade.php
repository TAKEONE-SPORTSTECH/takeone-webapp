{{-- Links, QR, WhatsApp, the QR poster and the printable PDF. --}}
@php
    $waText = fn (string $url) => 'https://wa.me/?text='.rawurlencode(trim($form->title."\n".($form->settings['title_ar'] ?? ''))."\n".$url);
@endphp
<div class="fa-card fa-pad" x-data="{ copied: '' , copy(t, k) { navigator.clipboard?.writeText(t).then(() => { this.copied = k; window.showToast?.('success', @js(__('forms::admin.copied'))); setTimeout(() => this.copied = '', 1500); }); } }">
    <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start">
        <div class="fa-qr">{!! $qr !!}</div>
        <div style="flex:1;min-width:240px">
            <div class="fa-h">{{ __('forms::admin.share_public') }}</div>
            <p class="fa-sub">{{ __('forms::admin.share_public_hint') }}</p>
            <div class="fa-link"><code>{{ $publicUrl }}</code>
                <button type="button" class="fa-btn ghost" style="padding:5px 10px" @click="copy(@js($publicUrl), 'p')"><i class="bi" :class="copied === 'p' ? 'bi-check2' : 'bi-copy'"></i>{{ __('forms::admin.copy') }}</button></div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                <a href="{{ $waText($publicUrl) }}" target="_blank" rel="noopener" class="fa-btn fa-wa"><i class="bi bi-whatsapp"></i>{{ __('forms::admin.whatsapp') }}</a>
                <a href="{{ route('admin.forms.poster', $form->uuid) }}" data-no-shell class="fa-btn"><i class="bi bi-qr-code"></i>{{ __('forms::admin.poster') }}</a>
                <a href="{{ route('admin.forms.pdf', $form->uuid) }}" data-no-shell class="fa-btn" x-data="{ busy: false }" @click="busy = true; setTimeout(() => busy = false, 6000)">
                    <i class="bi" :class="busy ? 'bi-hourglass-split' : 'bi-file-earmark-pdf'"></i><span x-text="busy ? @js(__('forms::admin.pdf_making')) : @js(__('forms::admin.pdf'))"></span></a>
            </div>
            @if($visitUrl)
                <div class="fa-h" style="margin-top:18px">{{ __('forms::admin.share_visit') }} <em class="fa-tag warn">{{ __('forms::admin.visitor_only') }}</em></div>
                <p class="fa-sub">{{ __('forms::admin.share_visit_hint') }}</p>
                <div class="fa-link"><code>{{ $visitUrl }}</code>
                    <button type="button" class="fa-btn ghost" style="padding:5px 10px" @click="copy(@js($visitUrl), 'v')"><i class="bi" :class="copied === 'v' ? 'bi-check2' : 'bi-copy'"></i>{{ __('forms::admin.copy') }}</button></div>
                <div style="margin-top:10px"><a href="{{ $waText($visitUrl) }}" target="_blank" rel="noopener" class="fa-btn fa-wa"><i class="bi bi-whatsapp"></i>{{ __('forms::admin.whatsapp_visitor') }}</a></div>
            @endif
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px">
                <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="fa-btn solid"><i class="bi bi-box-arrow-up-right"></i>{{ __('forms::admin.open_form') }}</a>
                <a href="{{ route('admin.forms.export', $form->uuid) }}" data-no-shell class="fa-btn ghost"><i class="bi bi-table"></i>{{ __('forms::admin.export') }}</a>
            </div>
        </div>
    </div>
</div>
