<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Minimal allow-list HTML sanitizer for rich-text admin content (e.g. club
 * registration terms) that is later rendered to end users with {!! !!}.
 *
 * Strategy: parse with DOMDocument, drop <script>/<style>/comments entirely,
 * UNWRAP any tag not on the allow-list (keeping its text), and strip every
 * attribute except a safe set — neutralising onclick=, style=, javascript:
 * hrefs, etc. Only http(s)/mailto links survive, forced to open safely.
 */
class HtmlSanitizer
{
    /** tag => list of attributes permitted on that tag. */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'b' => [], 'strong' => [], 'i' => [], 'em' => [],
        'u' => [], 's' => [], 'strike' => [], 'sub' => [], 'sup' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'blockquote' => [], 'span' => [], 'div' => [], 'a' => ['href'],
    ];

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        // The XML encoding PI forces UTF-8 parsing (keeps Arabic intact) without
        // the deprecated mb_convert_encoding(HTML-ENTITIES) trick.
        $dom->loadHTML(
            '<?xml encoding="UTF-8">'.'<div id="__root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $root = $xpath->query('//*[@id="__root"]')->item(0);
        if (! $root instanceof DOMElement) {
            return null;
        }

        // Remove dangerous nodes entirely (with their contents).
        foreach (iterator_to_array($xpath->query('//script | //style | //comment()')) as $node) {
            $node->parentNode?->removeChild($node);
        }

        // Clean every element. Snapshot first because we mutate the tree.
        foreach (iterator_to_array($xpath->query('//*')) as $el) {
            if (! $el instanceof DOMElement || $el === $root) {
                continue;
            }

            $tag = strtolower($el->nodeName);

            if (! array_key_exists($tag, self::ALLOWED)) {
                self::unwrap($el); // keep the text, drop the tag

                continue;
            }

            $allowedAttrs = self::ALLOWED[$tag];
            foreach (iterator_to_array($el->attributes ?? []) as $attr) {
                if (! in_array(strtolower($attr->name), $allowedAttrs, true)) {
                    $el->removeAttribute($attr->name);
                }
            }

            if ($tag === 'a') {
                $href = trim($el->getAttribute('href'));
                if ($href === '' || ! preg_match('#^(https?:|mailto:)#i', $href)) {
                    $el->removeAttribute('href');
                } else {
                    $el->setAttribute('href', $href);
                    $el->setAttribute('target', '_blank');
                    $el->setAttribute('rel', 'noopener noreferrer nofollow');
                }
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        $out = trim($out);

        // Treat content with no visible text and no <br> as empty.
        if ($out === '' || trim(strip_tags($out)) === '' && stripos($out, '<br') === false) {
            return null;
        }

        return $out;
    }

    private static function unwrap(DOMElement $el): void
    {
        $parent = $el->parentNode;
        if (! $parent) {
            return;
        }
        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }
}
