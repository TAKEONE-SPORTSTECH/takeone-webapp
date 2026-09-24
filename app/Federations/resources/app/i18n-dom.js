// Translation layer: replaces UI strings with translations stored in the strings table (FedStore.strings()).
// No machine translation. Strings without a stored translation stay in English and are registered as "missing"
// so staff can translate them in Settings → Translations.
(function () {
  var ATTRS = ['placeholder', 'title', 'aria-label'];
  var S = {lang: 'en', root: null, obs: null, table: {}, newKeys: {}, known: null};
  var tracked = new Set();

  function translatable(s) {
    var t = (s || '').trim();
    if (t.length < 2 || t.length > 600) return false;
    if (!/[A-Za-z]{2,}/.test(t)) return false;
    if (/^[\w.+-]+@[\w-]+\.[\w.]+$/.test(t)) return false;
    if (/^(https?:|www\.)/i.test(t)) return false;
    if (/^[A-Z]{2,4}-\d{3,}$/.test(t)) return false;
    if (/^[+\d\s().-]{6,}$/.test(t)) return false;
    if (/^(BHD|SAR|AED|QAR|KWD|OMR|USD|EUR|GBP)\s?[\d.,]+/.test(t)) return false;
    return true;
  }
  function skipEl(el) {
    for (var p = el; p && p !== document.body; p = p.parentElement) {
      if (p.nodeType !== 1) continue;
      var tg = p.tagName;
      if (tg === 'SCRIPT' || tg === 'STYLE' || tg === 'TEXTAREA' || tg === 'CODE' || tg === 'OPTION' && false) return true;
      if (p.isContentEditable || p.hasAttribute('data-no-tr')) return true;
      if (p.classList && p.classList.contains('leaflet-container')) return true;
    }
    return false;
  }
  function key(s) { return s.replace(/\s+/g, ' ').trim(); }
  function lookup(k) { var row = S.table[k]; return row && row[S.lang] ? row[S.lang] : null; }
  var regT = null;
  function register(k) {
    if (!S.known) S.known = new Set(Object.keys((window.FedStore && window.FedStore.stringKeys && window.FedStore.stringKeys()) || {}));
    if (S.table[k] || S.newKeys[k] || S.known.has(k)) return;
    S.newKeys[k] = 1; S.known.add(k); clearTimeout(regT);
    regT = setTimeout(function () { var ks = Object.keys(S.newKeys); S.newKeys = {}; if (window.FedStore && window.FedStore.registerStrings) window.FedStore.registerStrings(ks); }, 800);
  }

  function applyText(node) {
    if (!node.__orig || (node.nodeValue !== node.__tr && node.nodeValue !== node.__orig)) node.__orig = node.nodeValue;
    var raw = node.__orig, k = key(raw);
    if (!translatable(k)) return;
    register(k);
    var tr = S.lang === 'en' ? null : lookup(k);
    if (!tr) { if (node.__tr && node.nodeValue === node.__tr) node.nodeValue = raw; node.__tr = null; return; }
    tracked.add(node);
    var out = raw.match(/^\s*/)[0] + tr + raw.match(/\s*$/)[0];
    if (node.nodeValue !== out) { node.__tr = out; node.nodeValue = out; }
  }
  function applyAttrs(el) {
    ATTRS.forEach(function (a) {
      if (!el.hasAttribute(a)) return;
      var so = '__o_' + a, st = '__t_' + a, cur = el.getAttribute(a);
      if (!el[so] || (cur !== el[so] && cur !== el[st])) el[so] = cur;
      var k = key(el[so]);
      if (!translatable(k)) return;
      register(k);
      var tr = S.lang === 'en' ? null : lookup(k);
      if (!tr) { if (el[st] && cur === el[st]) el.setAttribute(a, el[so]); el[st] = null; return; }
      tracked.add(el);
      if (cur !== tr) { el[st] = tr; el.setAttribute(a, tr); }
    });
  }
  function walk(root) {
    if (!root) return;
    if (root.nodeType === 3) { if (!skipEl(root.parentElement)) applyText(root); return; }
    if (root.nodeType !== 1 || skipEl(root)) return;
    var tw = document.createTreeWalker(root, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT, {
      acceptNode: function (n) { return n.nodeType === 1 && skipEl(n) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT; }
    });
    var n = root;
    do { if (n.nodeType === 3) applyText(n); else if (n.nodeType === 1) applyAttrs(n); } while ((n = tw.nextNode()));
  }
  function reload() { S.table = window.FedStore && window.FedStore.strings ? window.FedStore.strings() : {}; }
  var rfT = null;
  function refreshAll() { clearTimeout(rfT); rfT = setTimeout(doRefresh, 120); }
  function doRefresh() { reload(); tracked.forEach(function (n) { if (!n.isConnected) { tracked.delete(n); return; } if (n.nodeType === 3) applyText(n); else applyAttrs(n); }); if (S.root) walk(S.root); if (S.obs) S.obs.takeRecords(); }

  function setLang(lang, root) {
    S.lang = lang || 'en'; S.root = root || document.body;
    reload();
    if (S.obs) S.obs.disconnect();
    tracked.forEach(function (n) { if (n.isConnected) { if (n.nodeType === 3) applyText(n); else applyAttrs(n); } });
    walk(S.root);
    var burst = 0, burstT = Date.now();
    S.obs = new MutationObserver(function (muts, ob) {
      var now = Date.now(); if (now - burstT > 1000) { burst = 0; burstT = now; }
      burst += muts.length;
      if (burst > 20000) { ob.disconnect(); console.warn('TakeOneI18n: too many DOM changes, live translation paused'); return; } // safety stop
      muts.forEach(function (m) {
        if (m.type === 'characterData') { if (!skipEl(m.target.parentElement)) applyText(m.target); }
        else if (m.type === 'attributes') { if (ATTRS.indexOf(m.attributeName) >= 0 && !skipEl(m.target)) applyAttrs(m.target); }
        else m.addedNodes.forEach(function (x) { walk(x); });
      });
      ob.takeRecords(); // drop records caused by our own writes
    });
    S.obs.observe(S.root, {subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ATTRS});
  }
  window.addEventListener('fedstore-change', function (e) { if (e.detail === 'takeone_strings') refreshAll(); });
  window.TakeOneI18n = {setLang: setLang, refresh: refreshAll};
})();
