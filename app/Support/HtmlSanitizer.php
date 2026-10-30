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
 * attribute except a safe set — neutralising onclick=, javascript: hrefs,
 * etc. Only http(s)/mailto links survive, forced to open safely.
 *
 * `style` survives in exactly two forms the editor writes: a text colour
 * (hex / rgb only) and a text alignment — every other declaration is dropped.
 * The editor's <font color> becomes a <span style="color:…">.
 */
class HtmlSanitizer
{
    /** tag => list of attributes permitted on that tag. */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'b' => [], 'strong' => [], 'i' => [], 'em' => [],
        'u' => [], 's' => [], 'strike' => [], 'sub' => [], 'sup' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'blockquote' => [], 'span' => [], 'div' => [], 'a' => ['href'], 'hr' => [],
    ];

    private const COLOUR = '/^(#[0-9a-f]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\))$/i';

    /**
     * @param  array<string, string[]>  $extra  further tags (=> their attributes) one caller allows on top of
     *                                          the shared list — e.g. a note's tables. Empty for everyone else.
     */
    public static function clean(?string $html, array $extra = []): ?string
    {
        $allowed = $extra ? array_merge(self::ALLOWED, $extra) : self::ALLOWED;

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

            // <font color="…"> (what the editor's colour button writes) → a span.
            if ($tag === 'font') {
                $colour = trim($el->getAttribute('color'));
                if (preg_match(self::COLOUR, $colour)) {
                    $span = $dom->createElement('span');
                    $span->setAttribute('style', 'color: '.strtolower($colour));
                    while ($el->firstChild) {
                        $span->appendChild($el->firstChild);
                    }
                    $el->parentNode?->replaceChild($span, $el);

                    continue;
                }
            }

            if (! array_key_exists($tag, $allowed)) {
                self::unwrap($el); // keep the text, drop the tag

                continue;
            }

            $style = self::safeStyle($el->getAttribute('style'));
            $allowedAttrs = $allowed[$tag];
            foreach (iterator_to_array($el->attributes ?? []) as $attr) {
                if (! in_array(strtolower($attr->name), $allowedAttrs, true)) {
                    $el->removeAttribute($attr->name);
                }
            }
            if ($style !== '') {
                $el->setAttribute('style', $style);
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

    /** Keep only `color` (hex/rgb) and `text-align` from a style attribute. */
    private static function safeStyle(string $style): string
    {
        $keep = [];
        foreach (explode(';', $style) as $decl) {
            [$prop, $value] = array_map('trim', explode(':', $decl, 2) + [1 => '']);
            $prop = strtolower($prop);
            $value = strtolower($value);
            if ($prop === 'color' && preg_match(self::COLOUR, $value)) {
                $keep[] = 'color: '.$value;
            } elseif ($prop === 'text-align' && in_array($value, ['left', 'right', 'center', 'justify', 'start', 'end'], true)) {
                $keep[] = 'text-align: '.$value;
            }
        }

        return implode('; ', $keep);
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
