{{--
    Claims awaiting a peer/coach vouch (mobile public profile).

    These are tournaments the member recorded themselves that no club can
    confirm, so they are shown plainly as unverified and are NEVER counted in
    the hero tallies or the Honours list above — the profile must not let a
    self-report read as a medal.

    The eligibility check here is a UI gate only; AchievementVerificationService
    re-checks the real rule when the vouch is submitted.
--}}
@once
<style>
    /* Own layout classes rather than inline display, because x-show writes
       style.display directly and would erase an inline flex. Scoped names so
       this partial does not depend on the host page's stylesheet. */
    .vouch-stack { display: flex; flex-direction: column; gap: 10px; }
    .vouch-col { display: flex; flex-direction: column; }
    .vouch-row { display: flex; align-items: center; gap: 12px; transition: transform .2s ease, box-shadow .2s ease; }
    .vouch-row:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(28,16,72,.13); }
</style>
@endonce

<div class="vouch-stack" x-data="peopleVouchSheet()">
    <p style="margin:6px 2px 0;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#8a8fa3">{{ __('Awaiting verification') }}</p>

    @foreach($vouchable as $t)
        <div data-vouch-card="{{ $t->uuid }}" class="vouch-row"
             style="background:#fff;border-radius:18px;box-shadow:0 6px 20px rgba(28,16,72,.07);padding:14px">
            <span style="flex-shrink:0;display:grid;place-items:center;width:34px;height:34px;border-radius:11px;background:#fdf4e3;color:#92700f;font-size:15px"><i class="bi bi-hourglass-split"></i></span>
            <div style="min-width:0;flex:1">
                <p style="margin:0;font-size:13.5px;font-weight:700;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $t->title }}</p>
                <p style="margin:3px 0 0;font-size:11px;color:#8a8fa3;line-height:1.35">{{ collect([$t->sport, optional($t->date)->format('M Y')])->filter()->implode(' · ') }}</p>
            </div>
            @if($canVouch)
                <button type="button" style="flex-shrink:0;height:30px;padding:0 12px;border:1px solid #d8d0f6;background:#f2effe;color:#5834bd;border-radius:999px;font-family:inherit;font-size:12px;font-weight:700;cursor:pointer"
                        @click="open('{{ route('attestations.vouch', ['achievement', $t->uuid]) }}', $el.closest('[data-vouch-card]'))">{{ __('Vouch') }}</button>
            @else
                <x-verification-badge :status="$t->verification_status" size="xs" />
            @endif
        </div>
    @endforeach

    {{-- Bottom sheet: teleported out of the transformed page wrapper, scrollable
         body, safe-area footer. --}}
    <template x-teleport="body">
        <div x-show="showSheet" x-cloak style="position:fixed;inset:0;z-index:75" @keydown.escape.window="showSheet = false">
            <div x-show="showSheet" x-transition.opacity style="position:absolute;inset:0;background:rgba(9,5,22,.55)" @click="showSheet = false"></div>
            <div x-show="showSheet"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 class="vouch-col" style="position:absolute;inset-inline:0;bottom:0;max-height:92vh;background:#f4f5f9;border-radius:24px 24px 0 0;box-shadow:0 -12px 40px rgba(9,5,22,.35);color:#1c1c28">
                <div style="flex-shrink:0;padding:12px 20px 4px">
                    <div style="width:40px;height:4px;border-radius:999px;background:#d6d8e3;margin:0 auto 12px"></div>
                    <h3 style="margin:0;font-size:16px;font-weight:800">{{ __('Vouch for this achievement') }}</h3>
                    <p style="margin:5px 0 0;font-size:12px;line-height:1.5;color:#8a8fa3">{{ __('Only vouch for what you personally witnessed.') }}</p>
                </div>

                <div style="flex:1;min-height:0;overflow-y:auto;padding:14px 20px 4px">
                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px">
                        <template x-for="opt in relOptions" :key="opt.v">
                            <button type="button" @click="relationship = opt.v"
                                    :style="'box-sizing:border-box;padding:11px 12px;border-radius:14px;font-family:inherit;font-size:13px;text-align:start;cursor:pointer;' + (relationship === opt.v ? 'border:1px solid #6d4bd8;background:#f2effe;color:#5834bd;font-weight:700;' : 'border:1px solid #e4e5ee;background:#fff;color:#6b6f80;')"
                                    x-text="opt.l"></button>
                        </template>
                    </div>
                    <textarea x-model="note" rows="3" placeholder="{{ __('Add a note (optional)') }}"
                              style="width:100%;margin-top:12px;padding:11px 12px;border:1px solid #e4e5ee;border-radius:14px;background:#fff;font-family:inherit;font-size:13px;resize:none"></textarea>
                </div>

                <div style="flex-shrink:0;display:flex;gap:8px;padding:12px 20px;padding-bottom:calc(0.75rem + env(safe-area-inset-bottom))">
                    <button type="button" @click="showSheet = false"
                            style="flex:1;height:48px;border:1px solid #e4e5ee;background:#fff;color:#6b6f80;border-radius:14px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer">{{ __('shared.cancel') }}</button>
                    <button type="button" @click="submit()" :disabled="saving"
                            :style="'flex:1.4;height:48px;border:0;background:#6d4bd8;color:#fff;border-radius:14px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;' + (saving ? 'opacity:.6;' : '')">{{ __('Submit vouch') }}</button>
                </div>
            </div>
        </div>
    </template>
</div>

{{-- Inline, not @push('scripts'): the mobile shell re-runs inline scripts on
     every AJAX navigation, and a pushed stack would not be re-evaluated. --}}
<script>
    function peopleVouchSheet() {
        return {
            showSheet: false, saving: false, relationship: 'teammate', note: '', url: '', card: null,
            relOptions: [
                { v: 'coach', l: @js(__('Coach')) },
                { v: 'official', l: @js(__('Official')) },
                { v: 'teammate', l: @js(__('Teammate')) },
                { v: 'other', l: @js(__('Other')) },
            ],
            open(url, card) { this.url = url; this.card = card; this.relationship = 'teammate'; this.note = ''; this.showSheet = true; },
            async submit() {
                this.saving = true;
                try {
                    const res = await fetch(this.url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': @js(csrf_token()),
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ stance: 'vouch', relationship: this.relationship, note: this.note }),
                    });
                    const data = await res.json();
                    if (data.success) {
                        // A claim that just crossed the threshold leaves this list in
                        // place, rather than waiting for a reload to stop showing it.
                        if (this.card && data.verification && data.verification.status === 'verified') this.card.remove();
                        window.showToast && window.showToast('success', data.message);
                        this.showSheet = false;
                    } else {
                        window.showToast && window.showToast('error', data.message || @js(__('Could not record your vouch.')));
                    }
                } catch (e) {
                    window.showToast && window.showToast('error', @js(__('Something went wrong.')));
                }
                this.saving = false;
            },
        };
    }
</script>
