<?php

/*
 * Fetch the printables designer's font catalogue (owner, 2026-10-20: "hundreds of
 * fonts, Arabic, English, Japanese") into public/fonts/designer, and write the
 * list the designer offers to config/printables-fonts.php.
 *
 * Self-hosted on purpose (CLAUDE.md → Self-Host Everything): a certificate must
 * print in a hall with no uplink, from the same files the editor showed.
 *
 * Source: the Google Fonts repository (github.com/google/fonts), every family under
 * an open licence (OFL / Apache / UFL), its licence file saved beside it. Per family:
 * its variable font where there is one (upright, and italic where it has one), else
 * up to three static weights — regular, bold, and black or italic — and only regular
 * and bold for the very large Japanese families.
 *
 * Re-runnable: a family already on disk is kept as it is. Run from the project root:
 *   sudo -u actionsrunner php scripts/fetch-designer-fonts.php
 * then `php artisan config:cache` (as www-data).
 */

const ROOT = __DIR__.'/../public/fonts/designer';
const OUT = __DIR__.'/../config/printables-fonts.php';
const RAW = 'https://raw.githubusercontent.com/google/fonts/main/';

// The catalogue. Groups: sans · serif · display · script · mono · arabic · japanese.
$catalogue = [
    'sans' => ['Roboto', 'Open Sans', 'Lato', 'Nunito', 'Nunito Sans', 'Rubik', 'Work Sans', 'Noto Sans', 'Source Sans 3', 'PT Sans',
        'Ubuntu', 'Mukta', 'Kanit', 'Barlow', 'Barlow Condensed', 'Barlow Semi Condensed', 'Heebo', 'Fira Sans', 'Fira Sans Condensed',
        'Quicksand', 'Manrope', 'DM Sans', 'Karla', 'Josefin Sans', 'Archivo', 'Archivo Narrow', 'Mulish', 'Outfit', 'Plus Jakarta Sans',
        'Urbanist', 'Lexend', 'Red Hat Display', 'Figtree', 'Sora', 'Space Grotesk', 'Hind', 'Titillium Web', 'Exo 2', 'Cabin', 'Dosis',
        'Oxygen', 'Assistant', 'Prompt', 'Saira', 'Saira Condensed', 'Chakra Petch', 'Rajdhani', 'Signika', 'Maven Pro', 'Varela Round',
        'Comfortaa', 'Asap', 'Asap Condensed', 'Encode Sans', 'Libre Franklin', 'Public Sans', 'IBM Plex Sans', 'IBM Plex Sans Condensed',
        'Roboto Condensed', 'League Spartan', 'Antonio', 'Fredoka', 'Baloo 2', 'Jost', 'Albert Sans', 'Onest', 'Instrument Sans',
        'Golos Text', 'Be Vietnam Pro', 'Epilogue', 'Schibsted Grotesk', 'Bricolage Grotesque', 'Hanken Grotesk', 'Wix Madefor Display'],
    'serif' => ['Merriweather', 'Roboto Slab', 'Libre Baskerville', 'EB Garamond', 'Crimson Text', 'Crimson Pro', 'PT Serif', 'Noto Serif',
        'Source Serif 4', 'Bitter', 'Arvo', 'Zilla Slab', 'Josefin Slab', 'Domine', 'Cardo', 'Spectral', 'Libre Caslon Text',
        'Old Standard TT', 'Cormorant', 'Prata', 'DM Serif Display', 'DM Serif Text', 'Yeseva One', 'Rozha One', 'Bodoni Moda',
        'Marcellus', 'Marcellus SC', 'Cinzel Decorative', 'Forum', 'Philosopher', 'Alegreya', 'Alegreya SC', 'Vollkorn', 'Gelasio',
        'Frank Ruhl Libre', 'IM Fell English', 'Playfair Display SC', 'Abhaya Libre', 'Unna', 'Rufina', 'Fraunces', 'Literata',
        'Newsreader', 'Instrument Serif', 'Young Serif', 'Gloock', 'Libre Bodoni', 'Castoro', 'Tenor Sans', 'Italiana'],
    'display' => ['Archivo Black', 'Teko', 'Orbitron', 'Russo One', 'Oxanium', 'Michroma', 'Audiowide', 'Righteous', 'Bungee',
        'Bungee Inline', 'Black Ops One', 'Passion One', 'Fjalla One', 'Alfa Slab One', 'Bowlby One', 'Bowlby One SC', 'Squada One',
        'Staatliches', 'Big Shoulders Display', 'Pathway Gothic One', 'Francois One', 'Paytone One', 'Lilita One', 'Luckiest Guy',
        'Bangers', 'Press Start 2P', 'Monoton', 'Chewy', 'Titan One', 'Racing Sans One', 'Faster One', 'Sarpanch',
        'Shrikhand', 'Rubik Mono One', 'Bungee Shade', 'Ultra', 'Holtwood One SC', 'Rammetto One', 'Changa One', 'Graduate',
        'Saira Stencil One', 'Stardos Stencil', 'Allerta Stencil', 'Wallpoet', 'Syncopate', 'Unbounded', 'Climate Crisis',
        'Rubik Dirt', 'Rubik Glitch', 'Bungee Outline', 'Coda', 'Electrolize', 'Iceland', 'Silkscreen', 'Barrio', 'Rye', 'Sancreek',
        'Smokum', 'Ewert', 'Diplomata'],
    'script' => ['Satisfy', 'Sacramento', 'Allura', 'Alex Brush', 'Tangerine', 'Pinyon Script', 'Kaushan Script', 'Lobster', 'Lobster Two',
        'Cookie', 'Yellowtail', 'Courgette', 'Kalam', 'Caveat', 'Caveat Brush', 'Shadows Into Light', 'Indie Flower', 'Amatic SC',
        'Architects Daughter', 'Gloria Hallelujah', 'Patrick Hand', 'Homemade Apple', 'Rock Salt', 'Mr Dafoe', 'Herr Von Muellerhoff',
        'Monsieur La Doulaise', 'Petit Formal Script', 'Italianno', 'Rouge Script', 'Marck Script', 'Cedarville Cursive', 'La Belle Aurore',
        'Nothing You Could Do', 'Reenie Beanie', 'Covered By Your Grace', 'Just Another Hand', 'Damion', 'Norican', 'Arizonia',
        'Bilbo Swash Caps', 'Clicker Script', 'Euphoria Script', 'Meddon', 'Mrs Saint Delafield', 'Qwigley', 'Ruthie', 'WindSong',
        'Birthstone', 'Imperial Script', 'Ballet', 'Licorice', 'Bad Script', 'Sofia', 'Niconne', 'Playball', 'Berkshire Swash',
        'Grand Hotel', 'Leckerli One', 'Merienda', 'Dynalight', 'Corinthia', 'Ephesis', 'Updock', 'Ole', 'Island Moments'],
    'mono' => ['Roboto Mono', 'Source Code Pro', 'JetBrains Mono', 'Fira Code', 'Space Mono', 'IBM Plex Mono', 'Courier Prime', 'DM Mono'],
    'arabic' => ['Almarai', 'Changa', 'El Messiri', 'Reem Kufi', 'Reem Kufi Fun', 'Reem Kufi Ink', 'Lalezar', 'Lemonada', 'Markazi Text',
        'Harmattan', 'IBM Plex Sans Arabic', 'Noto Kufi Arabic', 'Noto Naskh Arabic', 'Noto Sans Arabic', 'Readex Pro', 'Rakkas', 'Mada',
        'Baloo Bhaijaan 2', 'Aref Ruqaa', 'Aref Ruqaa Ink', 'Scheherazade New', 'Katibeh', 'Mirza', 'Jomhuria', 'Vibes', 'Blaka',
        'Blaka Hollow', 'Blaka Ink', 'Marhey', 'Kufam', 'Gulzar', 'Alexandria', 'Zain', 'Qahiri', 'Badeen Display', 'Amiri Quran',
        'Lateef', 'Alkalami', 'Ruwudu', 'Vazirmatn', 'Playpen Sans Arabic', 'Beiruti', 'Noto Nastaliq Urdu', 'Handjet'],
    'japanese' => ['Noto Sans JP', 'Noto Serif JP', 'M PLUS 1p', 'M PLUS Rounded 1c', 'M PLUS 1', 'Kosugi Maru', 'Kosugi', 'Sawarabi Gothic',
        'Sawarabi Mincho', 'Zen Maru Gothic', 'Zen Kaku Gothic New', 'Zen Old Mincho', 'Zen Antique', 'Shippori Mincho', 'Kaisei Decol',
        'Kaisei Opti', 'Yusei Magic', 'Reggae One', 'RocknRoll One', 'Hachi Maru Pop', 'Mochiy Pop One', 'Potta One', 'Stick',
        'DotGothic16', 'Kiwi Maru', 'Yuji Syuku', 'Klee One', 'BIZ UDPGothic', 'BIZ UDPMincho', 'Murecho', 'Rampart One', 'Train One',
        'Yomogi', 'Zen Kurenaido', 'Dela Gothic One'],
];

// Families the designer already offers (the hand-picked list in config/printables.php) are kept as they are.
$have = [];
$src = (string) file_get_contents(__DIR__.'/../config/printables.php');
preg_match_all("/'family'\\s*=>\\s*'([^']+)'/", $src, $m);
foreach ($m[1] as $f) {
    $have[$f] = true;
}

function get(string $url): ?string
{
    $ctx = stream_context_create(['http' => ['timeout' => 60, 'ignore_errors' => true, 'header' => "User-Agent: takeone-font-fetch\r\n"]]);
    $body = @file_get_contents($url, false, $ctx);
    $code = isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $mm) ? (int) $mm[1] : 0;

    return $code === 200 && is_string($body) && $body !== '' ? $body : null;
}

function first_of(array $list, callable $fn): ?array
{
    foreach ($list as $x) {
        if ($fn($x)) {
            return $x;
        }
    }

    return null;
}

$entries = [];
$missing = [];
$bytes = 0;
foreach ($catalogue as $group => $families) {
    foreach ($families as $family) {
        if (isset($have[$family])) {
            continue;
        }
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $family));
        $dirName = trim(strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $family)), '-');
        $meta = null;
        $base = null;
        foreach (['ofl', 'apache', 'ufl'] as $lic) {
            if ($meta = get(RAW.$lic.'/'.$slug.'/METADATA.pb')) {
                $base = RAW.$lic.'/'.$slug.'/';
                break;
            }
        }
        if (! $meta) {
            $missing[] = $family;
            continue;
        }

        // Every font file the family has: style, weight, file.
        preg_match_all('/fonts\s*\{(.*?)\n\}/s', $meta, $blocks);
        $files = [];
        foreach ($blocks[1] as $b) {
            preg_match('/style:\s*"(\w+)"/', $b, $st);
            preg_match('/weight:\s*(\d+)/', $b, $wt);
            preg_match('/filename:\s*"([^"]+)"/', $b, $fn);
            if ($fn) {
                $files[] = ['style' => $st[1] ?? 'normal', 'weight' => (int) ($wt[1] ?? 400), 'file' => $fn[1]];
            }
        }
        preg_match('/axes\s*\{\s*tag:\s*"wght"\s*min_value:\s*([\d.]+)\s*max_value:\s*([\d.]+)/s', $meta, $axis);

        $pick = [];
        $safe = fn (string $f) => preg_replace('/[^A-Za-z0-9_-]/', '', pathinfo($f, PATHINFO_FILENAME)).'.ttf';
        $variable = array_values(array_filter($files, fn ($f) => str_contains($f['file'], '[')));
        if ($variable) {
            $range = $axis ? ((int) $axis[1]).' '.((int) $axis[2]) : '400';
            foreach (['normal', 'italic'] as $style) {
                if ($f = first_of($variable, fn ($f) => $f['style'] === $style)) {
                    $pick[] = ['src' => $f['file'], 'weight' => $range, 'style' => $style, 'name' => str_replace(' ', '', $family).($style === 'italic' ? '-Italic' : '').'-VF.ttf'];
                }
            }
        } else {
            $max = $group === 'japanese' ? 2 : 3;
            $want = $group === 'japanese' ? [[400, 'normal'], [700, 'normal']] : [[400, 'normal'], [700, 'normal'], [900, 'normal'], [400, 'italic']];
            foreach ($want as [$w, $style]) {
                if (count($pick) >= $max) {
                    break;
                }
                if ($f = first_of($files, fn ($f) => $f['weight'] === $w && $f['style'] === $style)) {
                    $pick[] = ['src' => $f['file'], 'weight' => (string) $w, 'style' => $style, 'name' => $safe($f['file'])];
                }
            }
            if (! $pick && $files) {   // a single-weight family that is not 400 (a display face)
                $f = $files[0];
                $pick[] = ['src' => $f['file'], 'weight' => (string) $f['weight'], 'style' => $f['style'], 'name' => $safe($f['file'])];
            }
        }
        if (! $pick) {
            $missing[] = $family;
            continue;
        }

        @mkdir(ROOT.'/'.$dirName, 0775, true);
        $faces = [];
        foreach ($pick as $p) {
            $target = ROOT.'/'.$dirName.'/'.$p['name'];
            if (! is_file($target)) {
                $data = get($base.rawurlencode($p['src']));
                // Only a real font file (TrueType / OpenType) is kept.
                if ($data === null || ! in_array(substr($data, 0, 4), ["\x00\x01\x00\x00", 'OTTO', 'true'], true)) {
                    continue;
                }
                file_put_contents($target, $data);
                $bytes += strlen($data);
            }
            $faces[] = ['file' => $dirName.'/'.$p['name'], 'weight' => $p['weight']] + ($p['style'] === 'italic' ? ['style' => 'italic'] : []);
        }
        if (! $faces) {
            $missing[] = $family;
            @rmdir(ROOT.'/'.$dirName);
            continue;
        }
        foreach (['OFL.txt', 'LICENSE.txt', 'UFL.txt'] as $lic) {
            if (! is_file(ROOT.'/'.$dirName.'/'.$lic) && ($text = get($base.$lic))) {
                file_put_contents(ROOT.'/'.$dirName.'/'.$lic, $text);
                break;
            }
        }
        $entries[] = ['family' => $family, 'group' => $group, 'faces' => $faces];
        fwrite(STDOUT, sprintf("%-28s %-9s %d face(s)\n", $family, $group, count($faces)));
    }
}

// Keep what an earlier run found, add this run's.
$previous = is_file(OUT) ? (array) include OUT : [];
$byFamily = [];
foreach (array_merge($previous, $entries) as $e) {
    $byFamily[$e['family']] = $e;
}
$php = "<?php\n\n// Generated by scripts/fetch-designer-fonts.php — the designer's extra typefaces (files in\n// public/fonts/designer). Merged into config('printables.fonts'). Edit the script, not this.\n\nreturn ".var_export(array_values($byFamily), true).";\n";
file_put_contents(OUT, $php);

fwrite(STDOUT, sprintf("\nAdded %d families, %.1f MB. Not found: %s\n", count($entries), $bytes / 1048576, $missing ? implode(', ', $missing) : 'none'));
