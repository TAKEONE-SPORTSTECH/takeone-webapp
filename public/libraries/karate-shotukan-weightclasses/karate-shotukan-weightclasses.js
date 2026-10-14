/*!
 * karate-shotukan-weightclasses — the federation's Karate (Shotokan) age
 * categories and kumite weight classes, season 2025-2026.
 *
 * The TABLE lives in weightclasses.json beside this file and nowhere else.
 * This script never carries its own copy: the data reaches it
 *   · embedded by the server (App\Support\KarateShotukanWeightclasses::tags(),
 *     or the <x-karate-shotukan-weightclasses /> Blade component) — the normal
 *     way, so every call below is synchronous; or
 *   · fetched on demand with KarateShotukanWeightclasses.ready() when a page
 *     includes the script on its own.
 *
 * API (window.KarateShotukanWeightclasses):
 *   ready()                          → Promise, resolves once the table is loaded
 *   season / data                    → the season string / the raw table
 *   ageCategories()                  → ['Mini poussins', …, 'Vétérans 5']
 *   ageCategoryOf(age)               → 'Cadets'   (null below minAge)
 *   ageOf(dob, at?)                  → whole years on `at` (default today)
 *   table()                          → {M: {category: [classes]}, F: {…}}
 *   weights(gender, category)        → ['-52', '-57', …, '+70']
 *   weightClassOf(gender, cat, kg)   → '-52' | '+70'   (null without a weight)
 *   label(gender, cat, kg)           → 'Kumite -52 kg' | 'Kumite — TBC'
 *   classify({gender, dob|age, weight}, at?) → {ageGroup, weightClass, label} | null
 *
 * `gender` accepts 'M' / 'F' / 'Male' / 'Female' (any case). A '-X' class
 * holds weights up to and including X; above the last '-X' is the '+' class.
 */
(function (root) {
  'use strict';
  var SRC = (function () {
    try { var s = document.currentScript && document.currentScript.src; if (s) return s.replace(/[^/]*$/, '') + 'weightclasses.json'; } catch (e) {}
    return '/libraries/karate-shotukan-weightclasses/weightclasses.json';
  })();
  var D = root.__KARATE_SHOTUKAN_WEIGHTCLASSES__ || null, loading = null;

  function need() { if (!D) throw new Error('karate-shotukan-weightclasses: table not loaded — embed it from the server or await ready()'); return D; }
  function g(x) { return /^f/i.test(String(x || '')) ? 'F' : 'M'; }

  var api = {
    ready: function () {
      if (D) return Promise.resolve(api);
      if (!loading) loading = fetch(SRC, {credentials: 'same-origin'}).then(function (r) { return r.json(); }).then(function (j) { D = j; return api; });
      return loading;
    },
    get data() { return need(); },
    get season() { return need().season; },
    // The English name of an age category ('Benjamins' -> 'Under 12').
    englishName: function (category) {
      var cs = need().ageCategories;
      for (var i = 0; i < cs.length; i++) if (cs[i].name === category) return cs[i].label || cs[i].name;
      return category;
    },
    ageCategories: function () { return need().ageCategories.map(function (c) { return c.name; }); },
    ageCategoryOf: function (age) {
      var d = need(); age = +age;
      if (!(age >= d.minAge)) return null;
      for (var i = 0; i < d.ageCategories.length; i++) { var c = d.ageCategories[i]; if (c.maxAge == null || age <= c.maxAge) return c.name; }
      return null;
    },
    ageOf: function (dob, at) {
      if (!dob) return null;
      var b = new Date(dob), n = at ? new Date(at) : new Date();
      if (isNaN(b)) return null;
      var a = n.getFullYear() - b.getFullYear();
      if (n.getMonth() < b.getMonth() || (n.getMonth() === b.getMonth() && n.getDate() < b.getDate())) a--;
      return a;
    },
    table: function () { return need().weights; },
    weights: function (gender, category) { return (need().weights[g(gender)] || {})[category] || []; },
    weightClassOf: function (gender, category, kg) {
      var list = api.weights(gender, category); kg = parseFloat(kg);
      if (!list.length || !(kg > 0)) return null;
      for (var i = 0; i < list.length; i++) if (list[i][0] === '-' && kg <= parseFloat(list[i].slice(1))) return list[i];
      return list[list.length - 1];
    },
    label: function (gender, category, kg) {
      var w = api.weightClassOf(gender, category, kg);
      return w ? 'Kumite ' + w + ' kg' : 'Kumite — TBC';
    },
    classify: function (a, at) {
      a = a || {};
      if (!a.gender || !(a.weight > 0 || parseFloat(a.weight) > 0)) return null;
      var age = a.age != null && a.age !== '' ? +a.age : api.ageOf(a.dob, at);
      var ag = age == null ? null : api.ageCategoryOf(age);
      if (!ag) return null;
      var w = api.weightClassOf(a.gender, ag, a.weight);
      return w ? {ageGroup: ag, weightClass: w, label: 'Kumite ' + w + ' kg'} : null;
    }
  };

  root.KarateShotukanWeightclasses = api;
})(typeof window !== 'undefined' ? window : this);
