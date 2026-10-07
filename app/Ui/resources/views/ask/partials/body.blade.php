{{-- Ask TAKEONE — the chat. Shared by ask/desktop and ask/mobile. Every value
     the server returns is drawn with x-text / bound attributes, never HTML. --}}
<script>
window.t1Ask = function () {
    return {
        msgs: [], busy: false, box: '',
        sugs: [@js(__('ui::ask.s1')), @js(__('ui::ask.s2')), @js(__('ui::ask.s3'))],
        history() {
            return this.msgs.filter(m => !m.pending && !m.error).slice(-8)
                .map(m => ({ role: m.role === 'me' ? 'user' : 'assistant', content: m.role === 'me' ? m.text : ((m.data.reply || '') + ' ' + (m.data.question || '')).trim() }))
                .filter(m => m.content);
        },
        async send(text) {
            text = (text || '').trim(); if (!text || this.busy) return;
            const history = this.history();
            this.msgs.push({ role: 'me', text }); this.box = '';
            const pending = { role: 'ai', pending: true }; this.msgs.push(pending); this.busy = true;
            this.$nextTick(() => this.scroll());
            try {
                const res = await fetch(@js(route('me.ask.message')), { method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    body: JSON.stringify({ message: text, history }) });
                if (res.status === 429) throw new Error(@js(__('ui::ask.too_many')));
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(@js(__('ui::ask.error')));
                Object.assign(pending, { pending: false, data });
            } catch (e) {
                Object.assign(pending, { pending: false, error: e.message || @js(__('ui::ask.error')) });
            } finally { this.busy = false; this.$nextTick(() => this.scroll()); }
        },
        scroll() { const el = this.$refs.log && this.$refs.log.lastElementChild; el && el.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); },
        exploreUrl(d) { return @js(route('clubs.explore')) + '?tab=clubs&only=' + encodeURIComponent(d.clubs.map(c => c.key).join(',')); },
    };
};
</script>
<div class="t1 t1-page" x-data="t1Ask()">
    <div class="t1-bleed">
        <header class="t1-aband">
            <span class="t1-c1"></span>
            <a class="t1-ctl" href="{{ route('clubs.explore') }}" aria-label="{{ __('ui::ask.back') }}" title="{{ __('ui::ask.back') }}"><i class="bi bi-chevron-left rtl:rotate-180"></i></a>
            <h1>{{ __('ui::ask.heading') }}</h1>
            <p>{{ __('ui::ask.sub') }}</p>
        </header>
    </div>

    <div class="t1-chat" x-ref="log">
        <div class="t1-sugs" x-show="!msgs.length">
            <template x-for="(s, i) in sugs" :key="i">
                <button type="button" @click="send(s)"><i class="bi" :class="['bi-person-badge', 'bi-water', 'bi-people'][i]"></i><span x-text="s"></span></button>
            </template>
        </div>
        <template x-for="(m, i) in msgs" :key="i">
            <div>
                <template x-if="m.role === 'me'"><div class="t1-msg me"><div class="t1-bub" x-text="m.text"></div></div></template>
                <template x-if="m.role === 'ai'">
                    <div class="t1-msg ai">
                        <span class="t1-aiav" :class="{ still: !m.pending }">@include('ui::shell.robot', ['uid' => 'chat'])</span>
                        <div class="t1-aicol">
                            <template x-if="m.pending"><div class="t1-bub"><span class="t1-thinking"><i></i><i></i><i></i>&nbsp;{{ __('ui::ask.thinking') }}</span></div></template>
                            <template x-if="m.error"><div class="t1-bub" x-text="m.error"></div></template>
                            <template x-if="m.data">
                                <div style="display:grid;gap:10px;min-width:0">
                                    <div class="t1-bub"><span x-text="m.data.reply"></span>
                                        <template x-if="m.data.question"><div class="t1-q" x-text="m.data.question"></div></template></div>
                                    <template x-if="m.data.clubs.length">
                                        <div class="t1-res">
                                            <template x-for="c in m.data.clubs" :key="c.key">
                                                <a class="t1-card" :href="c.url">
                                                    <div class="t1-media" style="--c:#7c3aed;aspect-ratio:16/8">
                                                        <template x-if="c.cover"><img class="t1-cover" :src="c.cover" alt="" loading="lazy"></template>
                                                        <div class="t1-shade"></div>
                                                        <template x-if="c.logo"><span class="t1-logo-bare"><img :src="c.logo" alt=""></span></template>
                                                    </div>
                                                    <div class="t1-cb">
                                                        <div class="t1-ct"><h4 x-text="c.name"></h4></div>
                                                        <div class="t1-meta" x-show="c.area"><i class="bi bi-geo-alt"></i><span x-text="c.area"></span></div>
                                                        <template x-for="p in c.packages" :key="p.id">
                                                            <div class="t1-why"><i class="bi bi-check2-circle"></i><span x-text="[p.name, p.ages ? p.ages + ' yrs' : null, p.price + ' / mo', p.when].filter(Boolean).join(' · ')"></span></div>
                                                        </template>
                                                    </div>
                                                </a>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="m.data.coaches.length">
                                        <div class="t1-res">
                                            <template x-for="c in m.data.coaches" :key="c.url">
                                                <a class="t1-card" :href="c.url"><div class="t1-cb"><div class="t1-ct"><h4 x-text="c.name"></h4></div><div class="t1-tags" x-text="c.why"></div></div></a>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="m.data.plan">
                                        <div class="t1-plan">
                                            <div class="t1-planh"><b>{{ __('ui::ask.plan') }}</b><i class="bi bi-calendar-week"></i></div>
                                            <div class="t1-plankids">
                                                <template x-for="k in m.data.plan.children" :key="k.label">
                                                    <div class="t1-kid"><h6 x-text="k.label"></h6><b x-text="k.package"></b><small x-text="[k.club, k.when].filter(Boolean).join(' · ')"></small><span class="t1-pill" x-text="k.price"></span></div>
                                                </template>
                                            </div>
                                            <div class="t1-planf"><span><b x-text="m.data.plan.total"></b> {{ __('ui::ask.per_month') }}</span><small>{{ __('ui::ask.note') }}</small></div>
                                        </div>
                                    </template>
                                    <template x-if="m.data.clubs.length"><a class="t1-apply" :href="exploreUrl(m.data)"><i class="bi bi-grid"></i>{{ __('ui::ask.show_in_explore') }}</a></template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <div class="t1-composer2">
        <form @submit.prevent="send(box)">
            <textarea x-model="box" rows="1" maxlength="600" placeholder="{{ __('ui::ask.placeholder') }}" aria-label="{{ __('ui::ask.placeholder') }}"
                      @keydown.enter.prevent="if (!$event.shiftKey) send(box)"></textarea>
            <button type="submit" :disabled="busy || !box.trim()" aria-label="{{ __('ui::ask.send') }}"><i class="bi" :class="busy ? 'bi-hourglass-split' : 'bi-send-fill'"></i></button>
        </form>
    </div>
</div>
