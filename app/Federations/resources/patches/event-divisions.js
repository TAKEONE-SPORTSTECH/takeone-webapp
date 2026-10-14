  // The event's Divisions tab, as Cards or as a Table — the same toggle the
  // Clubs page has, remembered per browser (FederationApp: 'divs-*' rewrites).
  // The rows are the tab's own divCards; nothing here is fetched.
  // A name in three pieces with its gender word inked: Male blue, Female pink.
  evDivInk(name, p) {
    const m = /^(.*?)\b(Male|Female)\b(.*)$/i.exec(String(name || ''));
    const o = {};
    o[p + 'A'] = m ? m[1] : String(name || ''); o[p + 'G'] = m ? m[2] : ''; o[p + 'B'] = m ? m[3] : '';
    o[p + 'Ink'] = m ? this.cssToObj('color:' + (/^f/i.test(m[2]) ? '#db2777' : '#2563eb')) : {};
    return o;
  }
  evDivViewVals(divCards) {
    let v = this.state.divView;
    if (!v) { try { v = window.localStorage.getItem('takeone_fed_div_view'); } catch (e) {} }
    v = v === 'table' ? 'table' : 'cards';
    const setView = k => { try { window.localStorage.setItem('takeone_fed_div_view', k); } catch (e) {} this.setState({divView: k}); };
    const max = Math.max(1, ...divCards.map(d => d.count || 0));
    return {
      cards: v === 'cards', table: v === 'table', total: String(divCards.filter(d => !d.unplaced).length),
      entries: String(divCards.reduce((n, d) => n + (d.count || 0), 0)),
      ready: String(divCards.filter(d => !d.unplaced && d.count >= 2).length),
      // The card takes its division's gender: Female pink, Male blue, the
      // "not in a division" card (no gender of its own) white.
      rows: divCards.map(d => ({...d, ...this.evDivInk(d.label, 'l'), ...this.evDivInk(d.divName, 'n'),
        cardStyle: this.cssToObj('border-radius:16px;border-top:3px solid #d8b25f;padding:20px;box-shadow:0 10px 30px rgba(30,44,79,.08);cursor:pointer;transition:transform .15s ease,box-shadow .15s ease;'
          + (d.unplaced ? 'background:#fff' : d.gender === 'Female' ? 'background:#fbcfe8;border:1px solid #f472b6;border-top:3px solid #d8b25f' : d.gender === 'Male' ? 'background:#bfdbfe;border:1px solid #60a5fa;border-top:3px solid #d8b25f' : 'background:#fff')),
        entriesBar: this.cssToObj('height:6px;border-radius:3px;background:linear-gradient(100deg,#edcd85,#d0a854);width:' + (100 * (d.count || 0) / max).toFixed(1) + '%')})),
      tabs: [['cards', 'Cards', '▦'], ['table', 'Table', '☰']].map(([k, l, ic]) => ({
        label: l, icon: ic, pressed: v === k ? 'true' : 'false', onClick: () => setView(k),
        style: this.cssToObj('display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 12px;border-radius:8px;border:0;font-family:inherit;font-size:.74rem;cursor:pointer;transition:background .15s ease,color .15s ease;' + (v === k ? 'font-weight:700;color:#1e2c4f;background:#fff;box-shadow:0 2px 6px rgba(30,44,79,.12)' : 'font-weight:500;color:#6b7689;background:transparent'))
      }))
    };
  }
