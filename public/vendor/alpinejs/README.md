# Alpine.js — self-hosted

`3.17.4/alpine.min.js` is `dist/cdn.min.js` from the npm package `alpinejs@3.17.4`
(MIT, see `3.17.4/LICENSE`), fetched with `npm pack alpinejs@3.17.4`
(integrity `sha512-N2gsr+58XVUt7OAPKnUGiKPDtkd+xbSGTae8C8IJxeRdkkhBJPIautkJ+qcwb7lcjJ/QK3/CPVIbN20JPvtK7A==`)
on 2026-10-09. It is the same version the app layouts receive from the jsDelivr
`alpinejs@3.x.x` CDN link on that date.

**Why it is here:** the Karate scoring console is a standalone page that must
work in a hall with no internet (CLAUDE.md, *Self-Host Everything*). Its Bouts
card's Players tab uses the shared `fields/gender-toggle` component, which needs
Alpine. Loaded only there, from
`app/Scoreboard/resources/views/karate/scoreboard/partials/bouts-card.blade.php`.

**To update:** `npm pack alpinejs@<version>`, copy `package/dist/cdn.min.js` into a
new `<version>/alpine.min.js` folder with the licence, point the partial at it,
and delete the old folder.
