<?php

namespace Tests\Feature\Printables;

use App\Printables\Support\DocumentSanitizer;
use Tests\TestCase;

/**
 * Styled QR codes in the printables designer (docs/qr-designer-study.md):
 * only known shapes, real colours and clamped numbers are ever stored, and a
 * QR with no style stays exactly as it was.
 */
class QrStyleTest extends TestCase
{
    private function clean(array $element): ?array
    {
        $doc = (new DocumentSanitizer([], ['qr', 'photo'], ['Inter']))->clean(['pages' => [['id' => 'p1', 'elements' => [
            ['id' => 'e1', 'type' => 'image', 'x' => 10, 'y' => 10, 'width' => 100, 'height' => 100] + $element,
        ]]]]);

        return $doc['pages'][0]['elements'][0] ?? null;
    }

    public function test_a_valid_style_is_kept(): void
    {
        $el = $this->clean(['field' => 'qr', 'qrStyle' => [
            'body' => 'liquid', 'eyeFrame' => 'leaf', 'eyeBall' => 'circle', 'bodyColor' => '#1E3A8A', 'bg' => 'transparent',
            'gradient' => ['type' => 'radial', 'angle' => 90, 'stops' => [['#7c6bf5', 0], ['#1e3a8a', 1]]],
            'gradientOnEyes' => true, 'margin' => 3, 'ecLevel' => 'H',
            'eyes' => ['tr' => ['frame' => 'circle', 'flip' => true]],
        ]]);

        $q = $el['qrStyle'];
        $this->assertSame(['liquid', 'leaf', 'circle'], [$q['body'], $q['eyeFrame'], $q['eyeBall']]);
        $this->assertSame('#1e3a8a', $q['bodyColor']);
        $this->assertSame('transparent', $q['bg']);
        $this->assertSame('radial', $q['gradient']['type']);
        $this->assertCount(2, $q['gradient']['stops']);
        $this->assertSame(3, $q['margin']);
        $this->assertSame('H', $q['ecLevel']);
        $this->assertSame(['frame' => 'circle', 'flip' => true], (array) $q['eyes']->tr);
    }

    public function test_unknown_shapes_bad_colours_and_extra_keys_are_cleaned(): void
    {
        $el = $this->clean(['field' => 'qr', 'qrStyle' => [
            'body' => '<script>', 'eyeFrame' => 'dotted', 'eyeBall' => 'star', 'bodyColor' => 'red; background:url(x)',
            'eyeFrameColor' => 'transparent', 'margin' => 99, 'ecLevel' => 'Z',
            'gradient' => ['stops' => [['#000000', 0]]], 'onload' => 'alert(1)',
            'eyes' => ['xx' => ['frame' => 'circle'], 'tl' => ['frame' => 'nope']],
        ]]);

        $q = $el['qrStyle'];
        $this->assertSame(['square', 'square', 'square'], [$q['body'], $q['eyeFrame'], $q['eyeBall']]);
        $this->assertSame('#000000', $q['bodyColor']);
        $this->assertNull($q['eyeFrameColor'], 'Ink can never be transparent.');
        $this->assertSame(6, $q['margin']);
        $this->assertSame('auto', $q['ecLevel']);
        $this->assertNull($q['gradient'], 'A gradient needs two stops.');
        $this->assertArrayNotHasKey('onload', $q);
        $this->assertSame([], (array) $q['eyes']);
    }

    public function test_a_qr_without_a_style_is_stored_exactly_as_before(): void
    {
        $el = $this->clean(['field' => 'qr', 'fit' => 'contain']);

        $this->assertArrayNotHasKey('qrStyle', $el);
        $this->assertSame('qr', $el['field']);
    }

    public function test_only_the_qr_field_may_carry_a_style(): void
    {
        $this->assertArrayNotHasKey('qrStyle', $this->clean(['field' => 'photo', 'qrStyle' => ['body' => 'dots']]));
    }
}
