{{-- Every response: a searchable list, and one response in full in a sheet.
     The answers arrive as JSON and are drawn with x-text only. --}}
@php
    $ar = app()->getLocale() === 'ar';
    $respT = [
        'complete' => __('forms::admin.resp_complete', ['pct' => '__P__']),
        'unnamed' => __('forms::admin.resp_unnamed'),
        'visit' => __('forms::admin.mode_visit'), 'self' => __('forms::admin.mode_self'),
        'visitor' => __('forms::admin.visitor_only'),
    ];
@endphp
{{-- State is written inline (as the Duplicates page does) so it survives the
     admin shell swapping this page in after Alpine has already started. --}}
<div x-data="{
        rows: @js($responses), T: @js($respT), ar: @js($ar), q: '', open: null,
        get shown() {
            const q = this.q.trim().toLowerCase();
            return q ? this.rows.filter(r => [r.who, r.area, ...r.answers.map(a => a.a)].join(' ').toLowerCase().includes(q)) : this.rows;
        },
        when(iso) { return iso ? new Date(iso).toLocaleDateString(this.ar ? 'ar' : 'en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : ''; },
     }" class="space-y-3">
    @if($example)
        <div class="fa-example"><i class="bi bi-eye"></i><span class="flex-1">{{ __('forms::admin.example_on') }}</span></div>
    @endif
    <div class="fa-card" style="padding:8px 10px;display:flex;align-items:center;gap:8px">
        <i class="bi bi-search" style="color:var(--ink3);padding-inline-start:4px"></i>
        <input type="search" x-model="q" placeholder="{{ __('forms::admin.resp_search') }}" aria-label="{{ __('forms::admin.resp_search') }}"
               style="flex:1;border:0;outline:none;font-size:14px;padding:6px 0;background:transparent">
        <span class="fa-tag num" x-text="shown.length"></span>
    </div>
    <div class="fa-card" style="overflow:hidden">
        <template x-for="r in shown" :key="r.id">
            <button type="button" class="fa-resp" @click="open = r">
                <span class="av" x-text="(r.who || '?').trim().charAt(0).toUpperCase()"></span>
                <span class="tx"><b x-text="r.who || T.unnamed"></b>
                    <span x-text="[when(r.at), r.area, r.mode === 'visit' ? T.visit : T.self, r.lang === 'ar' ? 'عربي' : 'EN'].filter(Boolean).join(' · ')"></span></span>
                <svg class="fa-ring" viewBox="0 0 36 36" :aria-label="T.complete.replace('__P__', r.complete)">
                    <circle cx="18" cy="18" r="14" fill="none" stroke="hsl(220 15% 92%)" stroke-width="4"/>
                    <circle cx="18" cy="18" r="14" fill="none" stroke="hsl(250 65% 65%)" stroke-width="4" stroke-linecap="round"
                            :stroke-dasharray="88" :stroke-dashoffset="88 - 88 * r.complete / 100" transform="rotate(-90 18 18)"/>
                    <text x="18" y="21.5" text-anchor="middle" font-size="9" font-weight="800" fill="hsl(229 13% 38%)" x-text="r.complete"></text>
                </svg>
                <i class="bi bi-chevron-right rtl:rotate-180" style="color:var(--ink3)"></i>
            </button>
        </template>
        <p x-show="!shown.length" class="fa-sub" style="padding:24px;text-align:center">{{ __('forms::admin.resp_empty') }}</p>
    </div>

    {{-- one response, in full (Design Rule #8 sheet) --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fa" style="position:fixed;inset:0;z-index:70" @keydown.escape.window="open = null">
            <div style="position:absolute;inset:0;background:rgba(15,16,23,.45)" @click="open = null" x-transition.opacity></div>
            <div style="position:absolute;inset-inline:0;bottom:0;margin:0 auto;max-width:640px;max-height:90vh;display:flex;flex-direction:column;background:#fff;border-radius:24px 24px 0 0;overflow:hidden"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div style="flex-shrink:0;padding:12px 20px 16px;color:#fff;position:relative;overflow:hidden;background:linear-gradient(150deg,#7c6bf5,#7c6bf5b0)">
                    <div style="position:absolute;inset-inline-end:-32px;top:-40px;width:144px;height:144px;border-radius:50%;background:rgba(255,255,255,.1)"></div>
                    <div style="margin:0 auto 12px;width:40px;height:4px;border-radius:99px;background:rgba(255,255,255,.4)"></div>
                    <div style="position:relative;display:flex;gap:12px;align-items:flex-start">
                        <span style="width:48px;height:48px;border-radius:16px;background:rgba(255,255,255,.2);display:grid;place-items:center;flex-shrink:0"><i class="bi bi-person-lines-fill" style="font-size:20px"></i></span>
                        <div style="flex:1;min-width:0"><h3 style="font-size:18px;font-weight:900;line-height:1.2" x-text="open?.who || T.unnamed"></h3>
                            <p style="font-size:12px;opacity:.85;margin-top:2px" x-text="open ? [when(open.at), open.area, T.complete.replace('__P__', open.complete)].filter(Boolean).join(' · ') : ''"></p></div>
                        <button type="button" @click="open = null" aria-label="{{ __('shared.close') }}"
                                style="width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);display:grid;place-items:center;color:#fff;flex-shrink:0"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
                <div style="flex:1;overflow-y:auto;padding:4px 20px calc(16px + env(safe-area-inset-bottom))">
                    <template x-for="(a, i) in (open?.answers || [])" :key="i">
                        <div class="fa-ans">
                            <p x-show="i === 0 || open.answers[i-1].page !== a.page" style="font-weight:800;color:hsl(250 62% 56%);margin:6px 0 6px" x-text="ar && a.page_ar ? a.page_ar : a.page"></p>
                            <p><span x-text="ar && a.q_ar ? a.q_ar : a.q"></span> <em x-show="a.private" class="fa-tag warn" x-text="T.visitor"></em></p>
                            <div dir="auto" x-text="a.a"></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
