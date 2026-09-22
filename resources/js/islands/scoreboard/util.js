/**
 * util.js — the small, shared, untrusted-value helpers for both scoreboard
 * islands.
 *
 * Every name, club and image path on these surfaces was typed by a person, and
 * a mat screen is the most public publication surface in the product. Text
 * always goes in as text (React does that by construction); a URL is dropped
 * unless it is one we would have generated.
 */

/** Only a same-origin path or an http(s) URL — never a scheme that can carry script. */
export function safeUrl(u) {
    return typeof u === 'string' && /^(https?:\/\/|\/)[^"'()\\\s]*$/.test(u) ? u : null;
}

/** A CSS `url()` for a background, or `none`. Escaped, never interpolated raw. */
export function bgImage(u) {
    const safe = safeUrl(u);
    return safe ? `url("${encodeURI(safe)}")` : 'none';
}

/**
 * Our own files, served from this box — a hall with no uplink is the ordinary
 * case (CLAUDE.md -> Flags Come From One Local Source).
 */
export function flagUrl(code) {
    return /^[a-z]{2}$/.test(String(code || '')) ? `/images/flags/${code}.svg` : null;
}

/** m:ss, floored — the same arithmetic the Blade renderer used. */
export function clockWords(seconds) {
    const t = Math.max(0, seconds);
    return `${Math.floor(t / 60)}:${String(Math.floor(t % 60)).padStart(2, '0')}`;
}

/** The mat number out of a label ("Mat 3" -> "3"), falling back to the label. */
export function matNumber(label) {
    const s = String(label == null ? '' : label);
    return s.replace(/[^0-9]/g, '') || s;
}

/** Join the parts we actually have, dropping the ones we do not. */
export function joinFacts(parts, sep = ' · ') {
    return parts.filter(Boolean).join(sep);
}
