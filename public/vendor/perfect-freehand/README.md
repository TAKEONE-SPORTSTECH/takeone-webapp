# perfect-freehand 1.2.2

Vendored from the npm package `perfect-freehand@1.2.2` (MIT, see LICENSE) on 26 Oct 2026.
The package ships ESM only; `perfect-freehand.min.js` is `dist/esm/index.mjs` with its
single `export{…}` statement replaced by `window.PerfectFreehand = {getStroke, …}` inside an
IIFE, so it loads with a plain `<script>` tag. No other change.
Used by the dev board ink engine (`app/DevBoard`).
