@props([
    'target',              // CSS selector of the field to fill, e.g. [name="slogan"]
    'pair' => null,        // CSS selector of the other-language field (optional)
    'aiLabel' => '',
    'aiPurpose' => '',
    'format' => 'text',    // text | html
])

@once
<style>
    .aifw { position: relative; display: inline-flex; }
    .aifw-btn { width: 26px; height: 26px; border-radius: 7px; display: inline-flex; align-items: center; justify-content: center;
                color: hsl(250 65% 55%); background: transparent; border: none; cursor: pointer; font-size: 14px; transition: background .12s; }
    .aifw-btn:hover { background: #ede9fb; }
    .aifw-btn i { animation: aifwPulse 1.8s ease-in-out infinite; transform-origin: center; }
    @keyframes aifwPulse { 0%, 100% { transform: scale(1) rotate(0); opacity: .8; } 50% { transform: scale(1.2) rotate(-8deg); opacity: 1; } }
    @media (prefers-reduced-motion: reduce) { .aifw-btn i { animation: none; } }
    .aifw-pop { position: absolute; top: 30px; inset-inline-start: 0; z-index: 50; width: 300px; max-width: 78vw;
                background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,.12); padding: 10px; }
    .aifw-pop input { width: 100%; font-size: 13px; padding: 7px 10px; border: 1px solid #e5e7eb; border-radius: 8px; outline: none; }
    .aifw-pop input:focus { border-color: hsl(250 65% 65%); box-shadow: 0 0 0 3px hsl(250 65% 65% / 0.15); }
    .aifw-row { display: flex; gap: 6px; margin-top: 8px; }
    .aifw-gen { flex: 1; font-size: 13px; padding: 7px 10px; border-radius: 8px; border: none; cursor: pointer; background: hsl(250 65% 65%); color: #fff; }
    .aifw-gen:disabled { opacity: .6; }
    .aifw-cancel { font-size: 13px; padding: 7px 12px; border-radius: 8px; border: 1px solid #e5e7eb; background: #fff; color: #4b5563; cursor: pointer; }
</style>
<script>
    function aiFieldWand(opts) {
        return {
            open: false, prompt: '', loading: false,
            toggle() { this.open = !this.open; if (this.open) this.$nextTick(() => this.$refs.input && this.$refs.input.focus()); },
            field(sel) { try { return document.querySelector(sel); } catch (e) { return null; } },
            setField(sel, val) {
                const el = this.field(sel);
                if (el && val != null) { el.value = val; el.dispatchEvent(new Event('input', { bubbles: true })); }
            },
            async generate() {
                if (this.loading) return;
                this.loading = true;
                const key = (opts.format === 'html') ? 'html' : 'text';
                try {
                    const res = await fetch(opts.composeUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                                   'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                        body: JSON.stringify({
                            label: opts.aiLabel || '', purpose: opts.aiPurpose || '',
                            instructions: this.prompt || '', current: this.field(opts.target)?.value || '',
                            dir: 'ltr', format: opts.format || 'text', both: !!opts.pair,
                        }),
                    });
                    const data = await res.json();
                    if (data.success && data[key] != null) {
                        this.setField(opts.target, data[key]);
                        if (opts.pair && data['other_' + key] != null) this.setField(opts.pair, data['other_' + key]);
                        this.open = false; this.prompt = '';
                        window.showToast && window.showToast('success', opts.pair && data['other_' + key] != null ? 'Generated (English + Arabic)' : 'Generated');
                    } else {
                        window.showToast && window.showToast('error', data.message || 'Could not generate content.');
                    }
                } catch (e) {
                    window.showToast && window.showToast('error', 'Could not generate content.');
                } finally { this.loading = false; }
            },
        };
    }
</script>
@endonce

<span class="aifw" x-data="aiFieldWand({ target: @js($target), pair: @js($pair), aiLabel: @js($aiLabel), aiPurpose: @js($aiPurpose), format: @js($format), composeUrl: @js(url('/ai/compose')) })">
    <button type="button" class="aifw-btn" title="Write with AI" @click="toggle()"><i class="bi bi-magic"></i></button>
    <div class="aifw-pop" x-show="open" x-cloak x-transition @click.outside="open = false">
        <input type="text" x-ref="input" x-model="prompt"
               placeholder="Optional — tell the AI what you want, or leave blank"
               @keydown.enter.prevent="generate()" @keydown.escape.prevent="open = false">
        <div class="aifw-row">
            <button type="button" class="aifw-gen" @click="generate()" :disabled="loading">
                <span x-show="!loading"><i class="bi bi-stars"></i> Generate</span>
                <span x-show="loading">Writing…</span>
            </button>
            <button type="button" class="aifw-cancel" @click="open = false">Cancel</button>
        </div>
    </div>
</span>
