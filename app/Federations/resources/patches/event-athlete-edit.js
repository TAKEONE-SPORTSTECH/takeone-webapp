  // An athlete's edit dialog on the event's Athletes tab (FederationApp:
  // 'athed-*' rewrites). Reads the athlete through TakeOneFed.athleteGet and
  // saves through TakeOneFed.athleteSave (FederationAthleteEditor), which
  // decides what may change: a person the federation wrote down keeps their
  // details here, a member who claimed their account keeps their own.
  athEdOpen(r) {
    const T = window.TakeOneFed || {};
    if (!T.athleteGet || !r || !r.entryId || !r.takeoneId) return;
    const key = r.entryId + '|' + r.takeoneId;
    this.setState({athEd: {key, r: {entryId: r.entryId, takeoneId: r.takeoneId}, name0: r.name, loading: true, msg: '', busy: false}});
    T.athleteGet(r).then(res => {
      const cur = this.state.athEd;
      if (!cur || cur.key !== key) return;
      if (!res.ok) { this.athEdSet({loading: false, failed: true, msg: res.message || 'That athlete could not be opened.'}); return; }
      const a = res.athlete || {};
      const [yy, mm, dd] = (a.dob || '').split('-');
      const nat = (a.countries || []).find(c => c.iso3 === a.nat);
      this.athEdSet({loading: false, a, canEdit: !!res.canEdit, own: !!a.ownDetails,
        name: a.name || '', gender: a.gender || '', dd: dd || '', mm: mm || '', yy: yy || '',
        nat: a.nat || '', natName: nat ? nat.name : '', natQ: '', natOpen: false, natId: a.natId || '',
        weight: a.weight != null ? String(a.weight) : '', photo: null, photoUrl: a.photo || ''});
    });
  }
  athEdSet(patch) { this.setState(s => ({athEd: s.athEd ? Object.assign({}, s.athEd, patch) : s.athEd})); }
  athEdPhoto() {
    const T = window.TakeOneFed || {};
    if (!T.cropImage) return;
    // Portrait 3:4, stored at 600×800 like every face on the platform.
    T.cropImage({width: 600, height: 800, title: 'Crop the athlete\u2019s photo'}).then(res => {
      if (!res) return;
      if (res.error) { this.athEdSet({msg: res.error}); return; }
      this.athEdSet({photo: res, photoUrl: res, msg: ''});
    });
  }
  athEdSave() {
    const e = this.state.athEd;
    const T = window.TakeOneFed || {};
    if (!e || e.busy || !e.canEdit || !T.athleteSave) return;
    const data = {natId: e.natId.trim(), nat: e.nat || null, weight: e.weight.trim() === '' ? null : e.weight.trim()};
    if (data.weight !== null && !(Number(data.weight) >= 10 && Number(data.weight) <= 250)) { this.athEdSet({msg: 'Weight must be between 10 and 250 kg.'}); return; }
    if (e.own) {
      if (e.name.trim().length < 2) { this.athEdSet({msg: 'Enter the athlete\u2019s full name.'}); return; }
      data.name = e.name.trim();
      data.gender = e.gender || null;
      const parts = [e.dd, e.mm, e.yy].map(x => String(x || '').trim());
      if (parts.every(x => x === '')) {
        data.dob = null;
      } else {
        const d = Number(parts[0]), m = Number(parts[1]), y = Number(parts[2]);
        const dt = new Date(Date.UTC(y, m - 1, d));
        if (!(y > 1900 && dt.getUTCFullYear() === y && dt.getUTCMonth() === m - 1 && dt.getUTCDate() === d) || dt >= new Date()) {
          this.athEdSet({msg: 'Enter a real date of birth — day, month and year — or leave all three empty.'}); return;
        }
        data.dob = y + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
      }
      if (e.photo) data.photo = e.photo;
    }
    this.athEdSet({busy: true, msg: ''});
    T.athleteSave(e.r, data).then(res => {
      if (!this.state.athEd || this.state.athEd.key !== e.key) return;
      if (!res.ok) { this.athEdSet({busy: false, msg: res.message || 'That did not save. Try again.'}); return; }
      this.setState({athEd: null, athFlash: res.message || 'Athlete updated.'});
      clearTimeout(this._athFlashT);
      this._athFlashT = setTimeout(() => this.setState({athFlash: null}), 4000);
    });
  }
  evAthleteEditVals() {
    const e = (this.state || {}).athEd;
    const css = x => this.cssToObj(x);
    const flash = (this.state || {}).athFlash || '';
    if (!e) return {open: false, flash, hasFlash: !!flash};
    const a = e.a || {};
    const edit = !!e.canEdit, own = !!e.own;
    const g = v => css('flex:1;height:40px;border-radius:11px;font-family:inherit;font-size:.8rem;font-weight:600;cursor:' + (edit && own ? 'pointer' : 'default') + ';'
      + (e.gender === v ? (v === 'Female' ? 'background:#fdf2f8;border:1px solid #db2777;color:#db2777' : 'background:#eff6ff;border:1px solid #2563eb;color:#2563eb') : 'background:#fff;border:1px solid #e3e7ee;color:#5d6880'));
    const q = String(e.natQ || '').trim().toLowerCase();
    const matches = q.length ? (a.countries || []).filter(c => c.name.toLowerCase().includes(q) || c.iso3.toLowerCase() === q).slice(0, 8) : [];
    return {
      open: true, loading: !!e.loading, ready: !e.loading && !e.failed, failed: !!e.failed,
      title: e.name0 || a.name || 'Athlete', takeoneId: a.takeoneId || e.r.takeoneId,
      stop: ev => ev.stopPropagation(), onClose: () => this.setState({athEd: null}),
      edit, own, locked: !own, readOnly: !edit, canPhoto: edit && own, editOwn: edit && own, viewOwn: !(edit && own),
      hasPhoto: !!e.photoUrl, noPhoto: !e.photoUrl, initials: String(e.name || e.name0 || '').split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase(),
      photoStyle: Object.assign(css('width:96px;height:128px;border-radius:14px;background:#eef1f6;background-size:cover;background-position:center;flex:none;display:grid;place-items:center;font-size:1.1rem;font-weight:700;color:#1e2c4f;box-shadow:0 0 0 1px #e3e7ee'),
        e.photoUrl ? {backgroundImage: 'url("' + String(e.photoUrl).replace(/["\\]/g, '') + '")'} : {}),
      photoNew: !!e.photo, onPhoto: () => this.athEdPhoto(), photoLabel: e.photoUrl ? 'Change photo' : 'Add photo',
      name: e.name || '', onName: ev => this.athEdSet({name: ev.target.value}),
      maleStyle: g('Male'), femaleStyle: g('Female'),
      onMale: () => { if (edit && own) this.athEdSet({gender: e.gender === 'Male' ? '' : 'Male'}); },
      onFemale: () => { if (edit && own) this.athEdSet({gender: e.gender === 'Female' ? '' : 'Female'}); },
      genderText: e.gender || '\u2014',
      dd: e.dd || '', mm: e.mm || '', yy: e.yy || '',
      onDd: ev => this.athEdSet({dd: ev.target.value.replace(/\D/g, '').slice(0, 2)}),
      onMm: ev => this.athEdSet({mm: ev.target.value.replace(/\D/g, '').slice(0, 2)}),
      onYy: ev => this.athEdSet({yy: ev.target.value.replace(/\D/g, '').slice(0, 4)}),
      dobText: e.dd && e.mm && e.yy ? Number(e.dd) + ' ' + ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][Number(e.mm) - 1] + ' ' + e.yy : '\u2014',
      natShown: e.natName || (e.nat ? e.nat : 'Not set'), natQ: e.natQ || '', natOpen: !!e.natOpen && edit,
      onNatOpen: () => { if (edit) this.athEdSet({natOpen: !e.natOpen, natQ: ''}); },
      onNatQ: ev => this.athEdSet({natQ: ev.target.value}),
      natMatches: matches.map(c => ({label: c.name + ' \u00b7 ' + c.iso3, onPick: () => this.athEdSet({nat: c.iso3, natName: c.name, natOpen: false, natQ: ''})})),
      natNone: q.length > 0 && matches.length === 0, natHasMatches: matches.length > 0,
      onNatClear: () => this.athEdSet({nat: '', natName: '', natOpen: false}),
      natId: e.natId || '', onNatId: ev => this.athEdSet({natId: ev.target.value}),
      weight: e.weight || '', onWeight: ev => this.athEdSet({weight: ev.target.value.replace(/[^\d.]/g, '').slice(0, 5)}),
      hasMsg: !!e.msg, msg: e.msg || '',
      saveLabel: e.busy ? 'Saving\u2026' : 'Save changes',
      saveStyle: css('margin-left:auto;height:42px;padding:0 22px;border:0;border-radius:11px;font-family:inherit;font-size:.84rem;font-weight:700;color:#1e2c4f;background:linear-gradient(100deg,#edcd85,#d0a854);box-shadow:0 6px 16px rgba(202,161,79,.3);' + (e.busy ? 'opacity:.6;cursor:wait' : 'cursor:pointer')),
      onSave: () => this.athEdSave(),
      flash, hasFlash: !!flash
    };
  }
