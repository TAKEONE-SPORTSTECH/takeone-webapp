{{-- The builder's behaviour, REGISTERED with Alpine.data so the desktop and
     phone builders share one engine. The builder is always a full page load
     (its links carry data-no-shell), so alpine:init has not fired yet. The
     server re-checks and rebuilds everything this sends (FormSchema). --}}
@php
    $bT = [
        'saved' => __('forms::builder.saved'), 'failed' => __('forms::builder.failed'),
        'unsaved' => __('forms::builder.unsaved'), 'removePage' => __('forms::builder.remove_page_q'),
        'removePageBody' => __('forms::builder.remove_page_body'), 'removeQ' => __('forms::builder.remove_q'),
        'removeQBody' => __('forms::builder.remove_q_body'), 'remove' => __('forms::builder.remove'),
        'page' => __('forms::builder.page'), 'newQ' => __('forms::builder.new_question'), 'option' => __('forms::builder.option'),
    ];
    $bTypes = [
        ['radio', 'bi-ui-radios', __('forms::builder.t_radio')],
        ['checkboxes', 'bi-ui-checks', __('forms::builder.t_checkboxes')],
        ['scale', 'bi-123', __('forms::builder.t_scale')],
        ['grid', 'bi-grid-3x3-gap', __('forms::builder.t_grid')],
        ['rank', 'bi-sort-numeric-down', __('forms::builder.t_rank')],
        ['text', 'bi-input-cursor-text', __('forms::builder.t_text')],
        ['textarea', 'bi-textarea-t', __('forms::builder.t_textarea')],
        ['numbers', 'bi-calculator', __('forms::builder.t_numbers')],
        ['number', 'bi-hash', __('forms::builder.t_number')],
        ['phone', 'bi-telephone', __('forms::builder.t_phone')],
        ['email', 'bi-envelope', __('forms::builder.t_email')],
        ['checkbox', 'bi-check2-square', __('forms::builder.t_checkbox')],
        ['note', 'bi-journal-text', __('forms::builder.t_note')],
    ];
@endphp
<script>
document.addEventListener('alpine:init', () => {
  const T = @js($bT);
  const TYPES = @js(array_map(fn ($t) => ['type' => $t[0], 'icon' => $t[1], 'label' => $t[2]], $bTypes));
  const clone = (o) => JSON.parse(JSON.stringify(o));
  const opt = (label, label_ar = '') => ({ value: '', label, label_ar });

  function blankField(type) {
    const f = { key: '', type, label: '', label_ar: '', help: '', help_ar: '', required: false };
    if (type === 'radio' || type === 'checkboxes') f.options = [opt(T.option + ' 1'), opt(T.option + ' 2')];
    if (type === 'checkboxes') f.other = false;
    if (type === 'radio' || type === 'scale') f.note = false;
    if (type === 'scale') Object.assign(f, { min: 1, max: 5, low: 'Not at all', low_ar: 'إطلاقاً', high: 'Very much', high_ar: 'كثيراً جداً' });
    if (type === 'grid') { f.rows = [opt(T.option + ' 1'), opt(T.option + ' 2')]; f.columns = [opt('Must', 'ضرورية'), opt('Nice', 'جيدة'), opt('No', 'لا')]; f.required = true; }
    if (type === 'rank') { f.rows = [opt(T.option + ' 1'), opt(T.option + ' 2'), opt(T.option + ' 3')]; f.max = 3; }
    if (type === 'numbers') f.rows = [opt(T.option + ' 1')];
    if (type === 'note') f.private = true;
    return f;
  }

  Alpine.data('formBuilder', (cfg) => ({
    form: cfg.state, urls: cfg.urls, answers: cfg.answers, types: TYPES, T,
    page: 0, sel: null, saving: false, dirty: false, errors: {}, panel: 'hub', sheet: null, bulk: '', coverOpen: false,

    init() {
      this.form.pages.forEach(p => p.fields.forEach(f => this.ensure(f)));
      this.$watch('form', () => { this.dirty = true; }, { deep: true });
      // The cropper saves the artwork itself and announces it; take the new address.
      if (window.__formCoverListener) document.removeEventListener('imageUploaded', window.__formCoverListener);
      window.__formCoverListener = (e) => {
        if (e.detail && 'cover_url' in e.detail) {
          const keep = this.dirty; this.form.cover_url = e.detail.cover_url; this.coverOpen = false;
          this.$nextTick(() => { this.dirty = keep; });
        }
      };
      document.addEventListener('imageUploaded', window.__formCoverListener);
    },
    async removeCover() {
      const ok = await window.confirmAction?.({ title: @js(__('forms::builder.cover_remove_q')), message: '', type: 'danger', confirmText: T.remove });
      if (!ok || !this.urls.cover) return;
      const r = await fetch(this.urls.cover, { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
      const j = await r.json().catch(() => ({}));
      if (r.ok && j.success) { const keep = this.dirty; this.form.cover_url = null; this.$nextTick(() => { this.dirty = keep; }); window.showToast?.('success', j.message); }
      else window.showToast?.('error', j.message || T.failed);
    },
    ensure(f) { // fields from older forms may lack keys the editor binds to
      ['label_ar', 'help', 'help_ar'].forEach(k => { if (f[k] === undefined) f[k] = ''; });
      if (['radio', 'checkboxes'].includes(f.type) && !f.options) f.options = [];
      if (['grid', 'rank', 'numbers'].includes(f.type) && !f.rows) f.rows = [];
      if (f.type === 'grid' && !f.columns) f.columns = [];
    },

    get cur() { return this.form.pages[this.page]; },
    get field() { return this.sel !== null ? (this.cur.fields[this.sel] || null) : null; },
    get total() { return this.form.pages.reduce((n, p) => n + p.fields.length, 0); },
    get grids() { return this.form.pages.flatMap(p => p.fields).filter(f => f.type === 'grid' && f.key); },
    typeOf(t) { return this.types.find(x => x.type === t) || { icon: 'bi-question', label: t }; },
    hasList(f) { return f && ['radio', 'checkboxes', 'grid', 'rank', 'numbers'].includes(f.type); },
    listKey(f) { return ['radio', 'checkboxes'].includes(f.type) ? 'options' : 'rows'; },

    addPage() {
      const n = this.form.pages.length + 1;
      this.form.pages.push({ id: null, title: T.page + ' ' + n, title_ar: 'الصفحة ' + n, intro: '', intro_ar: '', private: false, fields: [] });
      this.page = this.form.pages.length - 1; this.sel = null; this.panel = 'page';
    },
    async removePage(i) {
      if (this.form.pages.length < 2) return;
      if (this.form.pages[i].fields.length) {
        const ok = await window.confirmAction?.({ title: T.removePage, message: T.removePageBody, type: 'danger', confirmText: T.remove });
        if (!ok) return;
      }
      this.form.pages.splice(i, 1); this.page = Math.max(0, Math.min(this.page, this.form.pages.length - 1)); this.sel = null;
      if (this.panel === 'page') this.panel = 'hub';
    },
    movePage(i, d) { const j = i + d; const a = this.form.pages; if (j < 0 || j >= a.length) return; [a[i], a[j]] = [a[j], a[i]]; this.page = j; },

    addField(type) {
      const f = blankField(type); f.label = T.newQ;
      this.cur.fields.push(f); this.sel = this.cur.fields.length - 1; this.sheet = 'field';
      this.$nextTick(() => document.getElementById('fb-label')?.select());
    },
    dupField(i) { const c = clone(this.cur.fields[i]); c.key = ''; this.cur.fields.splice(i + 1, 0, c); this.sel = i + 1; },
    async removeField(i) {
      if (this.answers) {
        const ok = await window.confirmAction?.({ title: T.removeQ, message: T.removeQBody, type: 'danger', confirmText: T.remove });
        if (!ok) return;
      }
      this.cur.fields.splice(i, 1); this.sel = null; this.sheet = null;
    },
    moveField(i, d) { const a = this.cur.fields; const j = i + d; if (j < 0 || j >= a.length) return; [a[i], a[j]] = [a[j], a[i]]; this.sel = j; },
    toPage(i, to) { const f = this.cur.fields.splice(i, 1)[0]; this.form.pages[to].fields.push(f); this.page = to; this.sel = this.cur.fields.length - 1; },
    pick(i) { this.sel = i; this.sheet = 'field'; },

    addItem(list) { list.push(opt('')); this.$nextTick(() => { const els = document.querySelectorAll('[data-item-input]'); els[els.length - 1]?.focus(); }); },
    moveItem(list, i, d) { const j = i + d; if (j < 0 || j >= list.length) return; [list[i], list[j]] = [list[j], list[i]]; },
    pasteItems(list) { // one per line; "English | عربي"
      this.bulk.split('\n').map(l => l.trim()).filter(Boolean).forEach(l => { const [en, ar] = l.split('|').map(x => (x || '').trim()); list.push(opt(en, ar || '')); });
      this.bulk = '';
    },

    async save() {
      if (this.saving) return; this.saving = true; this.errors = {};
      try {
        const r = await fetch(this.urls.save, {
          method: this.urls.method,
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
          body: JSON.stringify({ form: this.form }),
        });
        const j = await r.json().catch(() => ({}));
        if (r.ok && j.success) {
          const wasNew = this.urls.method === 'POST';
          this.form = j.form.state; this.form.pages.forEach(p => p.fields.forEach(f => this.ensure(f)));
          this.urls = Object.assign(this.urls, { save: j.form.edit_url.replace(/\/edit$/, ''), method: 'PUT', show: j.form.show_url, public: j.form.public_url });
          if (wasNew) history.replaceState(null, '', j.form.edit_url);
          this.$nextTick(() => { this.dirty = false; });
          window.showToast?.('success', j.message || T.saved);
          window.dispatchEvent(new CustomEvent('form-saved', { detail: j.form }));
        } else if (r.status === 422 && j.errors) {
          this.errors = j.errors;
          const k = Object.keys(j.errors)[0]; const m = k.match(/^pages\.(\d+)\.fields\.(\d+)/);
          if (m) { this.page = +m[1]; this.sel = +m[2]; this.sheet = 'field'; this.panel = 'page'; }
          window.showToast?.('error', j.message || Object.values(j.errors)[0][0]);
        } else {
          window.showToast?.('error', j.message || T.failed);
        }
      } catch (e) { window.showToast?.('error', T.failed); }
      this.saving = false;
    },
  }));
});
</script>
