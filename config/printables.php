<?php

/*
|--------------------------------------------------------------------------
| Printables — the one designer for everything the platform prints
|--------------------------------------------------------------------------
| A certificate, an official's badge, a poster, an ID card: each is a KIND of
| printable, and every kind opens the SAME editor (app/Printables). A kind says
| who may design it, which page sizes it may take and which live fields it can
| print ({name}, the QR …). The editor itself knows nothing about any of them.
|
| Adding a printable = one class implementing App\Printables\Contracts\PrintableKind
| and one line in `kinds`. Adding a page size = one line in `formats`.
*/

return [

    /*
     * The designer is new and replaces the certificates design room, so it is
     * OFF by default (RULE #1). Set PRINTABLES_DESIGNER=true, then config:cache.
     */
    'enabled' => (bool) env('PRINTABLES_DESIGNER', false),

    /*
     * Styled QR codes (shapes, colours, gradients — docs/qr-designer-study.md).
     * OFF by default: an element with no style prints exactly as before.
     */
    'qr_style' => (bool) env('PRINTABLES_QR_STYLE', false),

    /*
     * Print runs (a print shop's file of a whole event's certificates): many
     * recipients to one Chromium, and several Chromiums at once, one per core —
     * instead of a Chromium per certificate (Printer::renderMany). OFF by default:
     * off, every certificate is drawn on its own exactly as before.
     */
    'batch' => [
        'enabled' => (bool) env('PRINTABLES_BATCH', false),
        'per_chromium' => (int) env('PRINTABLES_BATCH_SIZE', 20),
        'parallel' => (int) env('PRINTABLES_BATCH_PARALLEL', 2),
    ],

    /* The Chromium that prints designs to PDF/PNG; empty = the usual system paths. */
    'chrome' => env('PRINTABLES_CHROME', ''),

    /*
     * kind key => class. The key is stored on every design, so it never changes
     * once shipped.
     */
    'kinds' => [
        'freeform' => \App\Printables\Kinds\FreeformKind::class,
        'certificate' => \App\Certificates\Support\CertificatePrintable::class,
    ],

    /*
     * Page sizes, in millimetres, written UPRIGHT (width ≤ height). Orientation
     * is chosen per design, so A4 is one entry, not two. `group` is how the
     * size picker lists them. A design may also take a custom size inside
     * `custom` (mm).
     */
    'formats' => [
        'a4' => ['label' => 'A4', 'group' => 'paper', 'w' => 210, 'h' => 297],
        'a5' => ['label' => 'A5', 'group' => 'paper', 'w' => 148, 'h' => 210],
        'a6' => ['label' => 'A6', 'group' => 'paper', 'w' => 105, 'h' => 148],
        'a3' => ['label' => 'A3', 'group' => 'paper', 'w' => 297, 'h' => 420],
        'letter' => ['label' => 'US Letter', 'group' => 'paper', 'w' => 215.9, 'h' => 279.4],
        'legal' => ['label' => 'US Legal', 'group' => 'paper', 'w' => 215.9, 'h' => 355.6],
        'photo_4x6' => ['label' => '4 × 6 in', 'group' => 'card', 'w' => 101.6, 'h' => 152.4],
        'photo_5x7' => ['label' => '5 × 7 in', 'group' => 'card', 'w' => 127, 'h' => 177.8],
        'id_card' => ['label' => 'ID card (CR80)', 'group' => 'card', 'w' => 53.98, 'h' => 85.6],
        'badge_a6' => ['label' => 'Lanyard badge', 'group' => 'card', 'w' => 105, 'h' => 148],
        'square' => ['label' => 'Square 20 cm', 'group' => 'card', 'w' => 200, 'h' => 200],
        'poster_a2' => ['label' => 'Poster A2', 'group' => 'poster', 'w' => 420, 'h' => 594],
        'poster_18x24' => ['label' => 'Poster 18 × 24 in', 'group' => 'poster', 'w' => 457.2, 'h' => 609.6],
    ],

    'custom' => ['min' => 20, 'max' => 1200],

    /* What one design may hold — the editor and the server enforce the same numbers. */
    'limits' => [
        'pages' => 30,
        'elements' => 400,
        'document_bytes' => 1_500_000,
        'asset_bytes' => 12 * 1024 * 1024,
        'assets_per_design' => 200,
    ],

    /*
     * The typefaces the editor offers — files in public/fonts/designer (see its
     * README). A variable font lists one face with a weight RANGE. The hand-picked
     * list below comes first; the wider catalogue (scripts/fetch-designer-fonts.php
     * → config/printables-fonts.php) is added after it.
     */
    'fonts' => array_merge([
        ['family' => 'Inter', 'group' => 'sans', 'faces' => [['file' => 'inter/Inter-Regular.ttf', 'weight' => '400'], ['file' => 'inter/Inter-SemiBold.ttf', 'weight' => '600'], ['file' => 'inter/Inter-Bold.ttf', 'weight' => '700']]],
        ['family' => 'Montserrat', 'group' => 'sans', 'faces' => [['file' => 'montserrat/Montserrat-VF.ttf', 'weight' => '100 900'], ['file' => 'montserrat/Montserrat-Italic-VF.ttf', 'weight' => '100 900', 'style' => 'italic']]],
        ['family' => 'Poppins', 'group' => 'sans', 'faces' => [['file' => 'poppins/Poppins-Regular.ttf', 'weight' => '400'], ['file' => 'poppins/Poppins-Italic.ttf', 'weight' => '400', 'style' => 'italic'], ['file' => 'poppins/Poppins-SemiBold.ttf', 'weight' => '600'], ['file' => 'poppins/Poppins-Bold.ttf', 'weight' => '700'], ['file' => 'poppins/Poppins-Black.ttf', 'weight' => '900']]],
        ['family' => 'Raleway', 'group' => 'sans', 'faces' => [['file' => 'raleway/Raleway-VF.ttf', 'weight' => '100 900'], ['file' => 'raleway/Raleway-Italic-VF.ttf', 'weight' => '100 900', 'style' => 'italic']]],
        ['family' => 'Oswald', 'group' => 'display', 'faces' => [['file' => 'oswald/Oswald-VF.ttf', 'weight' => '200 700']]],
        ['family' => 'Bebas Neue', 'group' => 'display', 'faces' => [['file' => 'bebas-neue/BebasNeue-Regular.ttf', 'weight' => '400']]],
        ['family' => 'Anton', 'group' => 'display', 'faces' => [['file' => 'anton/Anton-Regular.ttf', 'weight' => '400']]],
        ['family' => 'Abril Fatface', 'group' => 'display', 'faces' => [['file' => 'abril-fatface/AbrilFatface-Regular.ttf', 'weight' => '400']]],
        ['family' => 'Cinzel', 'group' => 'serif', 'faces' => [['file' => 'cinzel/Cinzel-VF.ttf', 'weight' => '400 900']]],
        ['family' => 'Playfair Display', 'group' => 'serif', 'faces' => [['file' => 'playfair-display/PlayfairDisplay-VF.ttf', 'weight' => '400 900'], ['file' => 'playfair-display/PlayfairDisplay-Italic-VF.ttf', 'weight' => '400 900', 'style' => 'italic']]],
        ['family' => 'Lora', 'group' => 'serif', 'faces' => [['file' => 'lora/Lora-VF.ttf', 'weight' => '400 700'], ['file' => 'lora/Lora-Italic-VF.ttf', 'weight' => '400 700', 'style' => 'italic']]],
        ['family' => 'Cormorant Garamond', 'group' => 'serif', 'faces' => [['file' => 'cormorant-garamond/CormorantGaramond-SemiBold.ttf', 'weight' => '600'], ['file' => 'cormorant-garamond/CormorantGaramond-Bold.ttf', 'weight' => '700'], ['file' => 'cormorant-garamond/CormorantGaramond-MediumItalic.ttf', 'weight' => '500', 'style' => 'italic']]],
        ['family' => 'Great Vibes', 'group' => 'script', 'faces' => [['file' => 'great-vibes/GreatVibes-Regular.ttf', 'weight' => '400']]],
        ['family' => 'Parisienne', 'group' => 'script', 'faces' => [['file' => 'parisienne/Parisienne-Regular.ttf', 'weight' => '400']]],
        ['family' => 'Dancing Script', 'group' => 'script', 'faces' => [['file' => 'dancing-script/DancingScript-VF.ttf', 'weight' => '400 700']]],
        ['family' => 'Pacifico', 'group' => 'script', 'faces' => [['file' => 'pacifico/Pacifico-Regular.ttf', 'weight' => '400']]],
        ['family' => 'Cairo', 'group' => 'arabic', 'faces' => [['file' => 'cairo/Cairo-VF.ttf', 'weight' => '200 1000']]],
        ['family' => 'Tajawal', 'group' => 'arabic', 'faces' => [['file' => 'tajawal/Tajawal-Regular.ttf', 'weight' => '400'], ['file' => 'tajawal/Tajawal-Bold.ttf', 'weight' => '700'], ['file' => 'tajawal/Tajawal-Black.ttf', 'weight' => '900']]],
        ['family' => 'Amiri', 'group' => 'arabic', 'faces' => [['file' => 'amiri/Amiri-Regular.ttf', 'weight' => '400'], ['file' => 'amiri/Amiri-Bold.ttf', 'weight' => '700']]],
    ], is_file(__DIR__.'/printables-fonts.php') ? (array) require __DIR__.'/printables-fonts.php' : []),
];
