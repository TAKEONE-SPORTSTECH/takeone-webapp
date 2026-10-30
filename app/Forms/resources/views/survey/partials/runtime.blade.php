{{-- The survey's behaviour, REGISTERED with Alpine.data (never inline x-data
     logic) so mobile and desktop share one engine and differ only in layout.
     Answers are kept on this device as they are typed (localStorage), so a
     dropped signal or a closed tab loses nothing; they reach the server only
     on Send, through forms.submit, which re-validates every key. --}}
<script>
document.addEventListener('alpine:init', () => {
  const UI = {
    start: ['Start', 'ابدأ'], next: ['Next', 'التالي'], back: ['Back', 'رجوع'], send: ['Send answers', 'إرسال الإجابات'],
    sending: ['Sending…', 'جارٍ الإرسال…'], pick1: ['Pick one', 'اختر إجابة واحدة'], pickMany: ['Pick all that apply', 'اختر كل ما ينطبق'],
    other: ['Other — please say', 'أخرى — يرجى التوضيح'], note: ['Add a note (optional)', 'أضف ملاحظة (اختياري)'],
    required: ['Please answer this one.', 'يرجى الإجابة على هذا السؤال.'], gridLeft: ['left', 'متبقٍ'],
    agree: ['I agree', 'أوافق'], visitor: ['Visitor only', 'للزائر فقط'], visitorMode: ['Visitor mode', 'وضع الزائر'],
    questions: ['questions', 'سؤالاً'], minutes: ['minutes', 'دقيقة'], languages: ['languages', 'لغتان'],
    saved: ['Saved on this device', 'محفوظ على هذا الجهاز'], doneTitle: ['Thank you!', 'شكراً لكم!'],
    another: ['Start the next club', 'ابدأ النادي التالي'], step: ['of', 'من'], page: ['Page', 'الصفحة'],
    resume: ['You have unsent answers on this device. They are kept below.', 'لديك إجابات غير مرسلة على هذا الجهاز، وهي محفوظة.'],
    rankNone: ['Mark some lines “Must” first, or pick from the full list below.', 'اختر «ضرورية» لبعض الأسطر أولاً، أو اختر من القائمة الكاملة.'],
    rankFrom: ['From your “Must” answers', 'من إجاباتكم «ضرورية»'], typeHere: ['Type here…', 'اكتب هنا…'],
    failed: ['Could not send. Check the connection and try again — your answers are still here.', 'تعذّر الإرسال. تحقق من الاتصال وحاول مجدداً — إجاباتك ما زالت محفوظة.'],
  };

  Alpine.data('survey', (cfg) => ({
    pages: cfg.pages, copy: cfg.copy, cfgCover: cfg.cover || null, lang: 'en', started: false, done: false, busy: false, doneMsg: '',
    step: 0, page: 0, values: {}, errors: {}, resumed: false, flashErr: '',
    storeKey: 'form:' + cfg.uuid + (cfg.visit ? ':visit' : ''),

    init() {
      let l = new URLSearchParams(location.search).get('lang');
      try { l = l || localStorage.getItem('form:lang'); } catch (e) {}
      if (!l) l = (navigator.language || '').startsWith('ar') ? 'ar' : 'en';
      this.setLang(l);
      this.reset(false);
      try {
        const s = JSON.parse(localStorage.getItem(this.storeKey) || 'null');
        if (s && s.values) { Object.assign(this.values, s.values); this.resumed = Object.keys(s.values).length > 0; }
      } catch (e) {}
      this.$watch('values', () => { try { localStorage.setItem(this.storeKey, JSON.stringify({ values: this.values, at: Date.now() })); } catch (e) {} }, { deep: true });
    },

    reset(clear) {
      const v = {};
      this.pages.forEach(p => p.fields.forEach(f => {
        if (f.type === 'checkboxes' || f.type === 'rank') v[f.key] = [];
        else if (f.type === 'grid' || f.type === 'numbers') v[f.key] = {};
        else if (f.type === 'checkbox') v[f.key] = false;
        else v[f.key] = '';
        if (f.other) v[f.key + '__other'] = '';
        if (f.note) v[f.key + '__note'] = '';
      }));
      this.values = v; this.errors = {};
      if (clear) { try { localStorage.removeItem(this.storeKey); } catch (e) {} }
    },

    setLang(l) {
      this.lang = l === 'ar' ? 'ar' : 'en';
      document.documentElement.lang = this.lang;
      document.documentElement.dir = this.lang === 'ar' ? 'rtl' : 'ltr';
      document.body.classList.toggle('ar', this.lang === 'ar');
      try { localStorage.setItem('form:lang', this.lang); } catch (e) {}
    },

    u(k) { return UI[k][this.lang === 'ar' ? 1 : 0]; },
    t(o, k) { if (!o) return ''; return (this.lang === 'ar' && o[k + '_ar']) ? o[k + '_ar'] : (o[k] || ''); },
    alt(o, k) { if (!o) return ''; return this.lang === 'ar' ? (o[k] || '') : (o[k + '_ar'] || ''); },

    // the flat list of steps: one question per step (the phone layout)
    get steps() { const s = []; this.pages.forEach((p, pi) => p.fields.forEach(f => s.push({ pi, f }))); return s; },
    get total() { return this.steps.length; },
    get cur() { return this.steps[this.step] || null; },
    get curPage() { return this.pages[this.cur ? this.cur.pi : this.page]; },
    get progress() { return this.started ? Math.round(((this.step + 1) / this.total) * 100) : 0; },
    get pageProgress() { return Math.round(((this.page + 1) / this.pages.length) * 100); },
    get minutes() { return Math.max(5, Math.round(this.total * 0.5 / 5) * 5); },

    has(f) {
      const v = this.values[f.key];
      if (f.type === 'grid') return (f.rows || []).every(r => !!(v || {})[r.value]);
      if (f.type === 'checkbox') return v === true;
      if (Array.isArray(v)) return v.length > 0;
      if (v && typeof v === 'object') return Object.values(v).some(x => x !== '' && x !== null);
      return v !== '' && v !== null && v !== undefined;
    },
    check(fields) {
      let ok = true; const e = {};
      fields.forEach(f => { if (f.required && !this.has(f)) { e[f.key] = true; ok = false; } });
      this.errors = e; return ok;
    },

    pageOf(key) { return this.pages.find(p => p.fields.some(f => f.key === key)); },
    toggle(key, val) { const a = this.values[key]; const i = a.indexOf(val); i >= 0 ? a.splice(i, 1) : a.push(val); this.errors[key] = false; },
    pick(key, val) { this.values[key] = this.values[key] === val ? '' : val; this.errors[key] = false; },
    cell(key, row, col) { this.values[key][row] = this.values[key][row] === col ? '' : col; },
    gridDone(f) { const v = this.values[f.key] || {}; return (f.rows || []).filter(r => !!v[r.value]).length; },

    rankRows(f) {
      if (!f.from) return f.rows || [];
      const src = this.values[f.from] || {};
      const picked = (f.rows || []).filter(r => src[r.value] === (f.fromValue || 'must'));
      return picked.length ? picked : (f.rows || []);
    },
    rankPos(f, val) { const i = (this.values[f.key] || []).indexOf(val); return i >= 0 ? i + 1 : ''; },
    rankTap(f, val) {
      const a = this.values[f.key]; const i = a.indexOf(val);
      if (i >= 0) a.splice(i, 1); else if (a.length < (f.max || 5)) a.push(val);
    },

    goSection(i) { const s = this.steps.findIndex(x => x.pi === i); if (s < 0) return; this.started = true; this.step = s; this.page = i; this.errors = {}; this.top(); },
    begin() { this.started = true; this.step = 0; this.page = 0; this.top(); },
    next() {
      if (!this.check([this.cur.f])) return;
      if (this.step < this.total - 1) { this.step++; this.top(); } else this.send();
    },
    back() { if (this.step > 0) { this.step--; this.errors = {}; this.top(); } else { this.started = false; } },
    nextPage() {
      if (!this.check(this.pages[this.page].fields)) { this.$nextTick(() => document.querySelector('.is-err')?.scrollIntoView({ behavior: 'smooth', block: 'center' })); return; }
      if (this.page < this.pages.length - 1) { this.page++; this.top(); } else this.send();
    },
    backPage() { if (this.page > 0) { this.page--; this.errors = {}; this.top(); } else { this.started = false; } },
    goPage(i) { this.page = i; this.started = true; this.errors = {}; this.top(); },
    top() {
      window.scrollTo({ top: 0, behavior: 'smooth' });
      // the phone layout scrolls inside its frame, not the window
      this.$nextTick(() => document.querySelectorAll('[data-scroll]').forEach(el => el.scrollTo({ top: 0, behavior: 'smooth' })));
    },

    async send() {
      if (this.busy) return;
      this.busy = true; this.flashErr = '';
      try {
        const r = await fetch(cfg.action, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify({ fields: this.values, lang: this.lang, visit: cfg.visitToken || undefined }),
        });
        const j = await r.json().catch(() => ({}));
        if (r.ok && j.success) {
          this.doneMsg = j.message; this.done = true; this.reset(true); this.top();
        } else if (r.status === 422 && j.errors) {
          const key = Object.keys(j.errors)[0].replace(/^fields\./, '').split('.')[0].replace(/__(other|note)$/, '');
          const si = this.steps.findIndex(s => s.f.key === key);
          if (si >= 0) { this.step = si; this.page = this.steps[si].pi; }
          this.errors = { [key]: true }; this.flashErr = j.message || this.u('required');
        } else {
          this.flashErr = j.message || this.u('failed');
        }
      } catch (e) { this.flashErr = this.u('failed'); }
      this.busy = false;
    },
    again() { this.done = false; this.started = false; this.step = 0; this.page = 0; this.resumed = false; this.top(); },
  }));
});
</script>
