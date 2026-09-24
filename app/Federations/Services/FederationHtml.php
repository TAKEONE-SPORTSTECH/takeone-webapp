<?php

namespace App\Federations\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * The server's copy of the dashboard's own `cleanHtml()` — the rich-text
 * "About" of an event is sanitised again here, because a browser's cleaning
 * is advice and this is the rule (Part 3 §3).
 *
 * Same allow-list as the page: headings, paragraphs, lists, quotes, rules,
 * tables, links (http/https only, opened safely) and colour on a <span>.
 * Plus the one image the editor inserts itself — a flag from our own flag set
 * — and nothing else: an author-chosen image on a public page is a fetch we
 * did not choose.
 */
class FederationHtml
{
    private const ALLOWED = [
        'h2', 'h3', 'p', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'u', 's', 'blockquote', 'hr', 'br',
        'a', 'span', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'img', 'font', 'div',
    ];

    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'button', 'textarea', 'link', 'meta'];

    public static function clean(?string $html, int $max = 60000): string
    {
        $html = mb_substr((string) $html, 0, $max);
        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();

        $root = $dom->getElementById('__root');
        if (! $root) {
            return '';
        }
        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $ch) {
            if ($ch->nodeType === XML_COMMENT_NODE || $ch->nodeType === XML_PI_NODE) {
                $node->removeChild($ch);

                continue;
            }
            if (! $ch instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($ch->tagName);

            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($ch);

                continue;
            }
            if (! in_array($tag, self::ALLOWED, true)) {
                self::walk($ch);
                while ($ch->firstChild) {
                    $node->insertBefore($ch->firstChild, $ch);
                }
                $node->removeChild($ch);

                continue;
            }

            $keep = [];
            if ($tag === 'a') {
                $href = trim($ch->getAttribute('href'));
                if (preg_match('#^https?://#i', $href)) {
                    $keep = ['href' => $href, 'target' => '_blank', 'rel' => 'noopener noreferrer'];
                }
            } elseif ($tag === 'img') {
                $src = $ch->getAttribute('src');
                if (! preg_match('#^/federation/app/flags/[a-z]{2}(-[a-z]{2,3})?\.png$#', $src)) {
                    $node->removeChild($ch);

                    continue;
                }
                $keep = ['src' => $src, 'alt' => mb_substr($ch->getAttribute('alt'), 0, 60), 'style' => 'height:1em;vertical-align:-0.1em'];
            } elseif ($tag === 'span' || $tag === 'font') {
                $style = $ch->getAttribute('style');
                $parts = [];
                if (preg_match('/(?:^|;)\s*color:\s*(#[0-9a-f]{3,6})\b/i', $style, $m) || preg_match('/^(#[0-9a-f]{3,6})$/i', $ch->getAttribute('color'), $m)) {
                    $parts[] = 'color:'.$m[1];
                }
                if (preg_match('/background(?:-color)?:\s*(#[0-9a-f]{3,6})\b/i', $style, $m)) {
                    $parts[] = 'background-color:'.$m[1];
                }
                if ($parts) {
                    $keep = ['style' => implode(';', $parts)];
                }
            }

            foreach (iterator_to_array($ch->attributes) as $attr) {
                $ch->removeAttribute($attr->nodeName);
            }
            foreach ($keep as $k => $v) {
                $ch->setAttribute($k, $v);
            }

            self::walk($ch);
        }
    }
}
