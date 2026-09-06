/**
 * realtime-only.js — MQTT for a page that is NOT the platform shell.
 *
 * WHY THIS EXISTS
 * ---------------
 * `realtime.js` has always been bundled inside `app.js`, and `app.js` carries
 * the whole platform shell — jQuery, the Bootstrap bridge, the toast container,
 * the shell navigators. The standalone event surface (`entry/layout.blade.php`)
 * deliberately loads none of that: it is a sealed, self-contained app whose
 * whole point is that it does not drag the platform in behind it.
 *
 * The cost of that was total: every page under `/e/{uuid}` was FROZEN. No
 * `realtime:*` event was ever emitted there, so the poster, the participants
 * list, the public draw and an athlete's own entry panel could only change by
 * someone pulling to refresh. Two independent audits found it the same way.
 *
 * So: one entry point that pulls in the realtime client and nothing else. It is
 * the same module the platform uses — not a second implementation — so a fix to
 * the transport lands in both places at once.
 *
 * ⚠️ LOAD IT ONLY FOR A SIGNED-IN READER. `/realtime/token` is behind `auth`,
 * and MQTT topics are keyed on the numeric user id, so an anonymous visitor has
 * no channel to subscribe to. Loading this for them buys a failed token fetch
 * and nothing else — see the `@auth` guard in `entry/layout.blade.php`.
 *
 * Giving ANONYMOUS spectators a live poster and a live bracket is a separate,
 * larger question: it needs a per-EVENT public topic rather than a per-user one,
 * and a decision about what a stranger may be pushed. Not solved here.
 */
import './realtime';
