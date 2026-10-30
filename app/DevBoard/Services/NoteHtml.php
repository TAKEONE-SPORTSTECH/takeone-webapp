<?php

namespace App\DevBoard\Services;

use App\DevBoard\Models\Card;
use App\Support\HtmlSanitizer;

/**
 * A formatted note, made safe to store and to render with innerHTML.
 *
 * The platform's HtmlSanitizer does the work (allow-list of tags, attributes
 * stripped, only http(s)/mailto links). It does not allow pictures, so a note's
 * pictures — and its voice notes and videos — are lifted out first and put back
 * afterwards in ONE shape the server writes itself — `<img data-asset="{uuid}"
 * alt="">`, plus `data-kind="audio|video"` for a recording, taken from the
 * asset row and never from the request — and only for something that belongs
 * to this card. Any other <img> (a remote URL, a data: URI, another card's
 * picture) is dropped.
 *
 * A video from YouTube, Vimeo, Dailymotion, Instagram or TikTok travels the same way, as
 * `<img data-embed="{site}:{id}" alt="">`: the site from a fixed list, the id
 * checked against that site's own pattern, and nothing else of the pasted
 * address kept — the player's address is built by the editor from those two.
 *
 * A table keeps its rows and cells, a cell's colspan/rowspan (2–20) and
 * alignment, and two looks of the note's own: the table's (`data-look=
 * "stripes|plain"`, grid when absent) and a cell's shade (`data-fill="1"…"6"`)
 * — carried past the sanitiser as a marker at the start of the cell.
 */
class NoteHtml
{
    private const MARK = "\u{E000}";

    /** Marks a block's writing direction through the sanitiser, which keeps no `dir`. */
    private const DIR = "\u{E001}";

    /** Marks a link drawn as a button (`data-look="button"`) through the sanitiser, which keeps no data- attributes. */
    private const BTN = "\u{E002}";

    /** A checklist (`<ul data-check>`) and a ticked item in it (`<li data-checked>`), carried the same way. */
    private const CHK = "\u{E003}";

    private const DONE = "\u{E004}";

    /** A video from another site (`<img data-embed="site:id">`), carried as its site and id alone. */
    private const EMBED = "\u{E005}";

    /** A cell's look: `[sp]?[1-6]?` — the table's look (on its first cell) and the cell's shade. */
    private const CELL = "\u{E006}";

    /** The table tags a note keeps, on top of the sanitiser's own list. */
    private const TABLE_TAGS = ['table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'], 'pre' => [], 'code' => []];

    /** The most Markdown a note converts at once (a long README is ~50 KB). */
    public const MARKDOWN_MAX = 512 * 1024;

    /** The sites a note may play a video from, and what one of their ids looks like. */
    public const EMBEDS = [
        'youtube' => '[A-Za-z0-9_-]{11}',
        'vimeo' => '[0-9]{6,12}',
        'dailymotion' => 'x[A-Za-z0-9]{4,12}',
        'instagram' => '[A-Za-z0-9_-]{5,40}',
        'tiktok' => '[0-9]{15,22}',
    ];

    private const BLOCKS = 'p|h[1-4]|li|blockquote|div|ul|ol';

    /** One tag, quoted attribute values included (a `>` inside quotes does not end it). */
    private const TAG = '/<[a-z!\/][^>"\']*(?:(?:"[^"]*"|\'[^\']*\')[^>"\']*)*>/i';

    private const UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    /** @param  Card|null  $card  whose pictures the note may show — none without one (a Markdown conversion) */
    public static function clean(?string $html, ?Card $card): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        // The markers below must only ever be ours: drop them in every spelling a writer could send
        // (raw, decimal or hex references, zero-padded, with or without the semicolon).
        $html = str_replace([self::MARK, self::DIR, self::BTN, self::CHK, self::DONE, self::EMBED, self::CELL], '', $html);
        $html = preg_replace('/&#(?:x0*e00[0-6]|0*5734[4-9]|0*57350);?/i', '', $html);

        // A table's look (read from the table, carried on its first cell) and each cell's shade.
        $html = preg_replace_callback('/<table\b[^>]*>.*?<\/table>/is', function ($t) {
            $look = preg_match('/^<table\b[^>]*\bdata-look\s*=\s*["\']?(stripes|plain)\b/i', $t[0], $l) ? strtolower($l[1])[0] : '';
            $first = true;

            return preg_replace_callback('/<(td|th)\b([^>]*)>/i', function ($c) use (&$first, $look) {
                $fill = preg_match('/\bdata-fill\s*=\s*["\']?([1-6])\b/', $c[2], $f) ? $f[1] : '';
                $mark = ($first ? $look : '').$fill;
                $first = false;

                return $mark === '' ? $c[0] : $c[0].self::CELL.$mark.self::CELL;
            }, $t[0]);
        }, $html);

        // A checklist and its ticked items: one fixed shape each, as markers just inside the tag
        // (before the direction pass, so a list's direction marker comes first and is restored first).
        $html = preg_replace_callback('/<ul\b([^>]*)>/i', fn ($m) => preg_match('/\bdata-check\b(?!ed)/i', $m[1]) ? $m[0].self::CHK : $m[0], $html);
        $html = preg_replace_callback('/<li\b([^>]*)>/i', fn ($m) => preg_match('/\bdata-checked\b/i', $m[1]) ? $m[0].self::DONE : $m[0], $html);

        // A paragraph's direction (a note can mix English and Arabic paragraphs):
        // only `ltr` / `rtl`, carried past the sanitiser as a marker just inside the block.
        $html = preg_replace_callback('/<('.self::BLOCKS.')\b([^>]*)>/i', function ($m) {
            return preg_match('/\bdir\s*=\s*["\']?(rtl|ltr)\b/i', $m[2], $d)
                ? $m[0].self::DIR.strtolower($d[1]).self::DIR
                : $m[0];
        }, $html);
        // A link shown as a button: one fixed look, carried as a marker just inside the link.
        $html = preg_replace_callback('/<a\b([^>]*)>/i', fn ($m) => preg_match('/\bdata-look\s*=\s*["\']?button\b/i', $m[1]) ? $m[0].self::BTN : $m[0], $html);
        $html = preg_replace_callback('/<img\b[^>]*>/i', function ($m) {
            if (preg_match('/data-asset\s*=\s*["\']('.self::UUID.')["\']/i', $m[0], $id)) {
                return self::MARK.strtolower($id[1]).self::MARK;
            }
            $embed = preg_match('/data-embed\s*=\s*["\']([a-z]+):([A-Za-z0-9_-]+)["\']/', $m[0], $e) ? self::embed($e[1], $e[2]) : null;

            return $embed ? self::EMBED.$embed.self::EMBED : '';
        }, $html);

        $clean = HtmlSanitizer::clean($html, self::TABLE_TAGS);
        if ($clean === null) {
            return null;
        }

        $clean = str_replace(['&#57344;', '&#xE000;', '&#xe000;'], self::MARK, $clean);
        $clean = str_replace(['&#57345;', '&#xE001;', '&#xe001;'], self::DIR, $clean);
        $clean = str_replace(['&#57346;', '&#xE002;', '&#xe002;'], self::BTN, $clean);
        $clean = str_replace(['&#57347;', '&#xE003;', '&#xe003;'], self::CHK, $clean);
        $clean = str_replace(['&#57348;', '&#xE004;', '&#xe004;'], self::DONE, $clean);
        $clean = str_replace(['&#57349;', '&#xE005;', '&#xe005;'], self::EMBED, $clean);
        $clean = str_replace(['&#57350;', '&#xE006;', '&#xe006;'], self::CELL, $clean);
        // Markers are restored in text only — never inside a tag, where splicing would break an attribute.
        $clean = preg_replace_callback(self::TAG, fn ($m) => str_replace([self::MARK, self::DIR, self::BTN, self::CHK, self::DONE, self::EMBED, self::CELL], '', $m[0]), $clean);
        // only a link that still goes somewhere (the sanitiser drops an unsafe address) becomes a button
        $clean = preg_replace('/<a\b(\s[^>]*\bhref="[^"]+"[^>]*)>'.self::BTN.'/i', '<a$1 data-look="button">', $clean);
        $clean = str_replace(self::BTN, '', $clean); // a stray marker never survives
        $clean = preg_replace('/<('.self::BLOCKS.')((?:\s[^>]*)?)>'.self::DIR.'(rtl|ltr)'.self::DIR.'/i', '<$1$2 dir="$3">', $clean);
        $clean = preg_replace('/'.self::DIR.'(?:rtl|ltr)?'.self::DIR.'?/', '', $clean); // a stray marker never survives
        $clean = preg_replace('/<ul\b((?:\s[^>]*)?)>'.self::CHK.'/i', '<ul$1 data-check="1">', $clean);
        $clean = preg_replace('/<li\b((?:\s[^>]*)?)>'.self::DONE.'/i', '<li$1 data-checked="1">', $clean);
        $clean = str_replace([self::CHK, self::DONE], '', $clean);
        $clean = preg_replace_callback('/'.self::EMBED.'([a-z]+):([A-Za-z0-9_-]+)'.self::EMBED.'/', function ($m) {
            $embed = self::embed($m[1], $m[2]);

            return $embed ? '<img data-embed="'.$embed.'" alt="">' : '';
        }, $clean);
        $clean = str_replace(self::EMBED, '', $clean); // a stray marker never survives
        $clean = preg_replace_callback('/<(td|th)((?:\s[^>]*)?)>'.self::CELL.'([sp]?)([1-6]?)'.self::CELL.'/i', fn ($m) => '<'.$m[1].$m[2].($m[3] !== '' ? ' data-tlook="'.$m[3].'"' : '').($m[4] !== '' ? ' data-fill="'.$m[4].'"' : '').'>', $clean);
        $clean = str_replace(self::CELL, '', $clean);
        // the look found on a first cell goes back onto its table
        $clean = preg_replace_callback('/<table>(\s*(?:<tbody>|<thead>)?\s*<tr>\s*<t[dh]\b[^>]*?) data-tlook="([sp])"/i', fn ($m) => '<table data-look="'.($m[2] === 's' ? 'stripes' : 'plain').'">'.$m[1], $clean);
        $clean = preg_replace('/ data-tlook="[sp]"/', '', $clean);
        // a span is a small whole number or nothing
        $clean = preg_replace_callback('/\s(colspan|rowspan)="([^"]*)"/i', function ($m) {
            $n = ctype_digit($m[2]) ? (int) $m[2] : 0;

            return $n >= 2 && $n <= 20 ? ' '.strtolower($m[1]).'="'.$n.'"' : '';
        }, $clean);
        $owned = $card ? $card->assets()->pluck('kind', 'uuid') : collect();
        $clean = preg_replace_callback('/'.self::MARK.'('.self::UUID.')'.self::MARK.'/', function ($m) use ($owned) {
            if (! isset($owned[$m[1]])) {
                return '';
            }
            $kind = in_array($owned[$m[1]], ['audio', 'video'], true) ? ' data-kind="'.$owned[$m[1]].'"' : '';

            return '<img data-asset="'.$m[1].'"'.$kind.' alt="">';
        }, $clean);

        return trim(strip_tags($clean, '<img><table>')) === '' ? null : $clean;
    }

    /**
     * Markdown (a pasted text or a .md file) as a note's own formatting: headings,
     * lists, checklists from `- [ ]`, tables, quotes, code, links. Converted by the
     * CommonMark that ships with Laravel (GitHub's flavour), raw HTML in it
     * stripped, unsafe links refused — then shaped into what a note keeps and
     * cleaned exactly like a saved note. A picture in it becomes a link to the
     * picture: a note never loads one from another site.
     */
    public static function fromMarkdown(string $text): ?string
    {
        $text = preg_replace('/^\x{FEFF}/u', '', $text);
        $text = preg_replace('/\A---\R.*?\R(?:---|\.\.\.)\R/s', '', $text); // a file's front matter is not its content
        $html = \Illuminate\Support\Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false, 'max_nesting_level' => 20]);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="md">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $x = new \DOMXPath($dom);
        $root = $x->query('//*[@id="md"]')->item(0);
        if (! $root) {
            return null;
        }
        foreach (iterator_to_array($x->query('//img')) as $img) {
            $src = $img->getAttribute('src');
            $a = $dom->createElement('a');
            $a->setAttribute('href', $src);
            $a->textContent = trim($img->getAttribute('alt')) ?: $src;
            $img->parentNode->replaceChild($a, $img);
        }
        // `- [x] done` → the note's own checklist
        foreach (iterator_to_array($x->query('//li/input[@type="checkbox"]')) as $box) {
            $li = $box->parentNode;
            if ($box->hasAttribute('checked')) {
                $li->setAttribute('data-checked', '1');
            }
            $next = $box->nextSibling;
            $li->removeChild($box);
            if ($next instanceof \DOMText) {
                $next->data = ltrim($next->data);
            }
            if ($li->parentNode instanceof \DOMElement && $li->parentNode->nodeName === 'ul') {
                $li->parentNode->setAttribute('data-check', '1');
            }
        }
        // a header row lives in the table's one body, so its columns line up with the rest
        foreach (iterator_to_array($x->query('//table/thead')) as $head) {
            $table = $head->parentNode;
            $body = $x->query('tbody', $table)->item(0) ?? $table->appendChild($dom->createElement('tbody'));
            foreach (array_reverse(iterator_to_array($x->query('tr', $head))) as $tr) {
                $body->insertBefore($tr, $body->firstChild);
            }
            $table->removeChild($head);
        }
        foreach (iterator_to_array($x->query('//th[@align] | //td[@align]')) as $cell) { // `|:-:|` — a column's alignment
            $cell->setAttribute('style', 'text-align: '.$cell->getAttribute('align'));
            $cell->removeAttribute('align');
        }
        foreach (iterator_to_array($x->query('//h5 | //h6')) as $hx) {
            $h4 = $dom->createElement('h4');
            while ($hx->firstChild) {
                $h4->appendChild($hx->firstChild);
            }
            $hx->parentNode->replaceChild($h4, $hx);
        }
        // the line breaks between blocks are layout, not text (a code block keeps every one of its own)
        foreach (iterator_to_array($x->query('//text()[not(ancestor::pre)]')) as $t) {
            if (trim($t->data) === '' && preg_match('/^(div|ul|ol|li|blockquote|table|tbody|tr)$/', $t->parentNode->nodeName)) {
                $t->parentNode->removeChild($t);
            }
        }
        foreach (iterator_to_array($x->query('//pre/code')) as $code) { // the block keeps the code's text, the code tag is the block's
            $code->textContent = rtrim($code->textContent, "\n");
        }

        $out = '';
        foreach ($root->childNodes as $n) {
            $out .= $dom->saveHTML($n);
        }

        return self::clean($out, null);
    }

    /** `site:id` when the site is one a note may play from and the id is that site's shape, else null. */
    public static function embed(string $site, string $id): ?string
    {
        $pattern = self::EMBEDS[$site] ?? null;

        return $pattern && preg_match('/^'.$pattern.'$/D', $id) ? $site.':'.$id : null;
    }

    /**
     * The note's tile: its first line as the title, the next words as the
     * excerpt — plain text, the way a notebook's index reads.
     *
     * @return array{0:string,1:string}
     */
    public static function parts(?string $html, int $length = 200): array
    {
        if (! $html) {
            return ['', ''];
        }
        $text = preg_replace('/<(\/?(p|div|h[1-4]|li|blockquote|ul|ol|tr|table|pre)|br|hr)\b[^>]*>/i', "\n", $html);
        $text = preg_replace('/<\/t[dh]>/i', ' ', $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_values(array_filter(array_map(fn ($l) => trim(preg_replace('/\s+/u', ' ', $l)), explode("\n", $text)), fn ($l) => $l !== ''));

        $title = mb_strimwidth($lines[0] ?? '', 0, 120, '…');
        $rest = mb_strimwidth(implode(' · ', array_slice($lines, 1)), 0, $length, '…');

        return [$title, $rest];
    }

    /**
     * How many of each thing a note holds.
     *
     * @return array{image:int,audio:int,video:int,lead:?string} lead = the kind of the first one
     */
    public static function kinds(?string $html): array
    {
        preg_match_all('/<img data-asset="'.self::UUID.'"(?: data-kind="(audio|video)")?/', (string) $html, $m);
        $kinds = array_map(fn ($k) => $k !== '' ? $k : 'image', $m[1]);
        $count = array_count_values($kinds);

        return ['image' => $count['image'] ?? 0, 'audio' => $count['audio'] ?? 0, 'video' => $count['video'] ?? 0, 'lead' => $kinds[0] ?? null];
    }

    /** The pictures alone (no recordings), in order. @return string[] uuids */
    public static function pictures(?string $html): array
    {
        preg_match_all('/<img data-asset="('.self::UUID.')" alt=/', (string) $html, $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * The other notes this one links to — a link made with "Link to a note" is
     * an ordinary address of the app (…/me/takenote?note={uuid}), so it survives
     * the sanitiser and still opens the note when followed from anywhere.
     *
     * @return string[] uuids
     */
    public static function noteLinks(?string $html): array
    {
        preg_match_all('#href="[^"]*/me/takenote\?note=('.self::UUID.')"#', (string) $html, $m);

        return array_values(array_unique($m[1]));
    }

    /** The pictures (and clips) a note shows, in order. @return string[] uuids */
    public static function assets(?string $html): array
    {
        preg_match_all('/data-asset="('.self::UUID.')"/', (string) $html, $m);

        return array_values(array_unique($m[1]));
    }
}
