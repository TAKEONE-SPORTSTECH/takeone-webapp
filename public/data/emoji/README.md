# Emoji list (vendored)

`emoji.json` is generated from the Unicode Consortium's `emoji-test.txt`, version 15.1
(https://unicode.org/Public/emoji/15.1/emoji-test.txt), under the Unicode License
(https://www.unicode.org/license.txt). It is the full standard set — the same emojis
WhatsApp and every phone keyboard offer — as characters; each device draws them in its
own style. No artwork is included.

Shape: `{ v, groups: [{ g: "Smileys & Emotion", e: [[char, name, [5 skin tones]?], …] }] }`
— fully-qualified emoji only, the "Component" group left out, single skin-tone variants
folded into their base emoji (mixed-tone pairs are reached through the base).

Used by the note editor's emoji panel (`app/DevBoard/resources/views/partials/editor.blade.php`).
To update: download a newer emoji-test.txt and re-run the generator described in
`Documentation/DEV-BOARD-PLAN.md` (28 Oct 2026, emoji).
