<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * A textarea's prose, rendered as the structure its author typed — including
 * the small, universally-known slice of Markdown people type by reflex.
 *
 * ── Why this exists ────────────────────────────────────────────────────────
 * A field like an event's "About" is a <textarea>. What an organiser writes
 * there carries its meaning in LINE BREAKS and in a handful of marks they have
 * typed a thousand times elsewhere: `##` for a heading, `- ` for a list,
 * `**word**` for emphasis. HTML throws the line breaks away and shows the
 * marks as literal punctuation — "## 🥋 بطولة" — which is worse than plain
 * text, because it looks like a bug in the page rather than a formatting
 * convention that was not honoured.
 *
 * So the text is PARSED here and emitted as real markup.
 *
 * ── It is safe by construction, and that is the whole design ───────────────
 * The input is UNTRUSTED — anyone who can edit an event can write it, and the
 * public reads it. Every character of the author's text goes through e()
 * BEFORE a single tag is added, and the only tags that ever reach the output
 * are the ones this class writes itself. Nothing typed into the field can
 * become markup: not a <script>, not an attribute, not a stray quote. That is
 * why the result is an HtmlString its callers render with {!! !!} — the
 * escaping already happened, one layer in.
 *
 * ── Deliberately a SUBSET, not a Markdown library ──────────────────────────
 * Headings, bold, italic, inline code, bullet and numbered lists, block
 * quotes, horizontal rules and links. No images (an <img> is a request to a
 * URL the author chose, on a page the public reads), no raw-HTML passthrough,
 * no reference links, no tables. Every one of those is its own escaping or
 * privacy problem, and an About field needs none of them. A link's scheme is
 * whitelisted to http(s) and mailto for the same reason.
 *
 * Adding a dependency for this was considered and rejected: a full CommonMark
 * parser is a large supply-chain surface for eight marks, and its safe-mode
 * still has to be configured correctly to be safe (CLAUDE.md → Supply chain).
 */
class PlainProse
{
    /** `- item` / `* item` / `• item` — an item in a bullet list. */
    private const BULLET = '/^\s*[-*•·]\s+(.+)$/u';

    /** `1. item` / `2) item` — an item in a numbered list. */
    private const NUMBERED = '/^\s*\d{1,3}[.)]\s+(.+)$/u';

    /** `## Heading` — one to six hashes. */
    private const HEADING = '/^(#{1,6})\s+(.+?)\s*#*$/u';

    /** `> quoted` */
    private const QUOTE = '/^\s*>\s?(.*)$/u';

    /** `---` / `***` / `___` alone on a line. */
    private const RULE = '/^\s*(-{3,}|\*{3,}|_{3,})\s*$/u';

    /**
     * Heading sizes. Only these six classes are used, and every one of them is
     * in the prebuilt Tailwind bundle — a class nobody has used before has no
     * CSS and renders as nothing (CLAUDE.md → the bundle is prebuilt).
     */
    private const HEADING_CLASS = [
        1 => 'text-xl font-black',
        2 => 'text-lg font-black',
        3 => 'text-base font-bold',
        4 => 'text-[15px] font-bold',
        5 => 'text-[15px] font-bold',
        6 => 'text-[15px] font-bold',
    ];

    /**
     * @param  string  $textClass  utility classes for each <p> and <li>
     * @param  string  $gapClass   the space BETWEEN blocks. The last block
     *                             never carries it, so the text does not push
     *                             against the bottom of the card it sits in.
     *                             (`last:mb-0` is not in the prebuilt bundle,
     *                             so the last block is decided here in PHP.)
     */
    public static function toHtml(
        ?string $text,
        string $textClass = 'text-[15px] leading-relaxed text-foreground',
        string $gapClass = 'mb-3',
    ): HtmlString {
        $blocks = self::blocks($text);

        $html = '';
        $last = count($blocks) - 1;

        foreach ($blocks as $i => $block) {
            $gap = $i === $last ? '' : ' '.$gapClass;
            $lines = $block['lines'];

            switch ($block['type']) {
                case 'h':
                    // A heading belongs to what FOLLOWS it, so it sits tight to
                    // the block below and takes its air from the one above.
                    $level = $block['level'];
                    $cls = self::HEADING_CLASS[$level].' text-foreground mb-2'.($i === 0 ? '' : ' mt-4');
                    $html .= '<h'.$level.' class="'.e($cls).'">'.self::inline($lines[0]).'</h'.$level.'>';
                    break;

                case 'ul':
                    $html .= '<ul class="'.e('list-disc ps-5 space-y-1.5'.$gap).'">'
                        .self::items($lines, $textClass).'</ul>';
                    break;

                case 'ol':
                    // `list-decimal` is NOT in the prebuilt bundle, so the
                    // marker is set inline rather than shipping a class that
                    // resolves to nothing.
                    $html .= '<ol class="'.e('ps-5 space-y-1.5'.$gap).'" style="list-style:decimal outside;">'
                        .self::items($lines, $textClass).'</ol>';
                    break;

                case 'quote':
                    $html .= '<blockquote class="'.e('border-s-4 border-border ps-4'.$gap).'">'
                        .'<p class="'.e($textClass).'">'.self::lines($lines).'</p></blockquote>';
                    break;

                case 'hr':
                    $html .= '<hr class="my-4 border-border">';
                    break;

                default:
                    // Soft line breaks inside one paragraph: the author pressed
                    // Enter once, which is a new LINE, not a new paragraph.
                    $html .= '<p class="'.e($textClass.$gap).'">'.self::lines($lines).'</p>';
            }
        }

        return new HtmlString($html);
    }

    /** @param array<int, string> $lines */
    private static function items(array $lines, string $textClass): string
    {
        return implode('', array_map(
            fn ($l) => '<li class="'.e($textClass).'">'.self::inline($l).'</li>',
            $lines
        ));
    }

    /** @param array<int, string> $lines */
    private static function lines(array $lines): string
    {
        return implode('<br>', array_map(fn ($l) => self::inline($l), $lines));
    }

    /**
     * The text as a list of blocks.
     *
     * A blank line ends a block. So does a change of KIND — a paragraph
     * followed straight by its list, with no blank line the author can see,
     * still becomes two blocks.
     *
     * @return array<int, array{type: string, level?: int, lines: array<int, string>}>
     */
    private static function blocks(?string $text): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $text));

        if ($text === '') {
            return [];
        }

        $blocks = [];
        $type = null;
        $lines = [];

        $flush = function () use (&$blocks, &$type, &$lines) {
            if ($lines !== []) {
                $blocks[] = ['type' => $type ?? 'p', 'lines' => $lines];
            }
            $type = null;
            $lines = [];
        };

        foreach (explode("\n", $text) as $raw) {
            $line = trim($raw);

            if ($line === '') {
                $flush();

                continue;
            }

            // A rule and a heading are each one line and stand alone.
            if (preg_match(self::RULE, $line)) {
                $flush();
                $blocks[] = ['type' => 'hr', 'lines' => []];

                continue;
            }

            if (preg_match(self::HEADING, $line, $m)) {
                $flush();
                $blocks[] = ['type' => 'h', 'level' => strlen($m[1]), 'lines' => [$m[2]]];

                continue;
            }

            if (preg_match(self::BULLET, $line, $m)) {
                $want = 'ul';
                $body = $m[1];
            } elseif (preg_match(self::NUMBERED, $line, $m)) {
                $want = 'ol';
                $body = $m[1];
            } elseif (preg_match(self::QUOTE, $line, $m)) {
                $want = 'quote';
                $body = $m[1];
            } else {
                $want = 'p';
                $body = $line;
            }

            if ($type !== null && $type !== $want) {
                $flush();
            }

            $type = $want;
            $lines[] = trim($body);
        }

        $flush();

        return $blocks;
    }

    /**
     * One line of the author's text → safe HTML.
     *
     * ⚠️ ORDER MATTERS, and the first step is the one that makes the rest
     * safe: escape, THEN add tags. Every pattern below runs against text that
     * can no longer contain a tag, so the only markup in the result is the
     * markup this method wrote.
     */
    private static function inline(string $line): string
    {
        $s = e($line);

        // `code` first: whatever is inside it is already escaped, and marking
        // it now keeps a stray asterisk in a code span from reading as italic.
        $s = preg_replace(
            '/`([^`]+)`/u',
            '<code class="bg-muted rounded px-1 py-0.5 font-mono">$1</code>',
            $s
        );

        // [label](url) — http(s) and mailto only. Anything else is left as the
        // literal text the author typed rather than becoming a link to a
        // scheme nobody vetted (javascript:, data:, intent:…).
        //
        // A leading `!` (Markdown's IMAGE) is swallowed and the thing becomes
        // an ordinary link: this page will not fetch a URL its author chose on
        // behalf of every reader, but neither will it leave a stray "!" in the
        // sentence of someone who pasted an image line in good faith.
        $s = preg_replace_callback(
            '/!?\[([^\]\n]+)\]\(([^)\s]+)\)/u',
            function ($m) {
                // The href is validated on the DECODED value and emitted in
                // its escaped form, which is what belongs inside an attribute.
                $raw = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');

                if (! preg_match('#^(https?://|mailto:)#i', $raw)) {
                    return $m[0];
                }

                return '<a href="'.$m[2].'" target="_blank" rel="noopener nofollow"'
                    .' class="text-primary underline break-words">'.$m[1].'</a>';
            },
            $s
        );

        // **bold** / __bold__ before *italic* / _italic_, or the italic pass
        // eats one asterisk of each pair.
        $s = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/us', '<strong class="font-bold">$1</strong>', $s);
        $s = preg_replace('/__(?=\S)(.+?)(?<=\S)__/us', '<strong class="font-bold">$1</strong>', $s);
        $s = preg_replace('/(?<![\*\w])\*(?=\S)([^*\n]+?)(?<=\S)\*(?!\*)/u', '<em class="italic">$1</em>', $s);
        $s = preg_replace('/(?<![\w_])_(?=\S)([^_\n]+?)(?<=\S)_(?![\w_])/u', '<em class="italic">$1</em>', $s);

        return $s;
    }
}
