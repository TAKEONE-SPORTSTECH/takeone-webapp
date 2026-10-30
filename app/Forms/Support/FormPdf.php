<?php

namespace App\Forms\Support;

use App\Forms\Models\Form;
use App\Printables\Support\ChromeRenderer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Any form, printed as the bilingual questionnaire booklet — the same design
 * as the two hand-made questionnaire PDFs, drawn from the form's own pages.
 *
 * How the pages are read (so a form built in the builder prints sensibly):
 *   · a PRIVATE first page of short answers is the cover sheet (its fields fill
 *     the white card; its agree box becomes the consent on the guide page);
 *   · a form with a visitor link gets the interviewer's guide page;
 *   · a page holding a Must/Nice/No grid prints as the priority table, with a
 *     Top-N column when a ranking on that page draws from it;
 *   · a page of nothing but long answers prints as "speak your mind", and a
 *     long answer of 8+ rows at its end becomes the open-notes page;
 *   · a PRIVATE last page prints as the visit summary — long answers as lines,
 *     1–5 scales and one-choice answers as the score boxes;
 *   · every other page is a section band, its questions numbered across the
 *     form, the visitor-note's hint as the interviewer tip, the note itself as
 *     the observations box.
 *
 * The PDF is kept on the private disk under the form's own folder, named by a
 * hash of everything that shapes it — an edited form prints afresh, an
 * unchanged one is handed back at once.
 */
class FormPdf
{
    /** Bump when the template changes, so every cached copy is redrawn. */
    private const VERSION = 1;

    public function __construct(private ChromeRenderer $chrome) {}

    /** The PDF's path on the local disk, drawing it first when needed. */
    public function path(Form $form): string
    {
        $hash = substr(md5(json_encode([self::VERSION, $form->title, $form->description, $form->schema,
            array_intersect_key($form->settings ?? [], array_flip(['title_ar', 'description_ar', 'successMessage', 'successMessage_ar', 'visitToken']))])), 0, 16);
        $dir = \App\Support\StoragePath::platform('forms/'.$form->uuid.'/pdf');
        $path = $dir.'/'.$hash.'.pdf';
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            $pdf = $this->chrome->pdf($this->html($form));
            foreach ($disk->files($dir) as $old) {
                $disk->delete($old); // one copy per form: the current one
            }
            $disk->put($path, $pdf);
        }

        return $path;
    }

    /** The QR poster as a PNG (1080 × 1350): artwork, both titles, the QR of the public link. */
    public function poster(Form $form): string
    {
        $s = $form->settings ?? [];
        $url = route('forms.show', $form->uuid);
        $art = null;
        $cover = $s['cover'] ?? null;
        if ($cover && Storage::disk('local')->exists($cover)) {
            $bytes = Storage::disk('local')->get($cover);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
                $art = 'data:'.$mime.';base64,'.base64_encode($bytes);
            }
        }
        $questions = count($form->fields());
        $html = view('forms::pdf.poster', [
            'fonts' => public_path('fonts/designer'),
            'titleHtml' => $this->titleHtml((string) $form->title),
            'titleAr' => (string) ($s['title_ar'] ?? ''),
            'qr' => \App\Support\Qr::svg($url, 400, 1),
            'art' => $art,
            'minutes' => max(5, (int) round($questions * 0.5 / 5) * 5),
            'short' => preg_replace('#^https?://#', '', $url),
        ])->render();

        return $this->chrome->png($html, 1080, 1350);
    }

    public function filename(Form $form): string
    {
        return (Str::slug(Str::limit($form->title, 60, '')) ?: 'form').'.pdf';
    }

    public function html(Form $form): string
    {
        return view('forms::pdf.document', $this->layout($form) + [
            'fonts' => public_path('fonts/designer'),
        ])->render();
    }

    /** The booklet, as data the template draws. */
    public function layout(Form $form): array
    {
        $s = $form->settings ?? [];
        $pages = array_values($form->schema['pages'] ?? []);
        $interviewer = ! empty($s['visitToken']);

        $cover = ['fields' => [], 'consent' => null];
        if ($pages && ! empty($pages[0]['private']) && $this->isCoverPage($pages[0])) {
            foreach (array_shift($pages)['fields'] as $f) {
                if (in_array($f['type'] ?? '', ['checkbox', 'terms'], true)) {
                    $cover['consent'] ??= $f;
                } elseif (count($cover['fields']) < 6) {
                    $cover['fields'][] = $f;
                }
            }
        }
        if (! $cover['fields']) {
            $cover['fields'] = [
                ['label' => 'Name', 'label_ar' => 'الاسم'], ['label' => 'Date', 'label_ar' => 'التاريخ'],
            ];
        }

        $summary = null;
        $last = end($pages);
        if ($last && ! empty($last['private']) && count($pages) > 1) {
            $summary = array_pop($pages);
        }

        $blocks = [];
        $n = 0;    // question numbers run across the whole form
        $sec = 0;  // section numbers
        $free = null;
        foreach ($pages as $p) {
            $fields = $p['fields'] ?? [];
            $types = array_column($fields, 'type');
            if (in_array('grid', $types, true)) {
                $blocks[] = ['kind' => 'grid', 'num' => sprintf('%02d', ++$sec), 'page' => $p] + $this->grid($fields);
            } elseif ($fields && count(array_diff($types, ['textarea'])) === 0) {
                $prompts = $fields;
                $tail = end($prompts);
                if (count($prompts) > 1 && (int) ($tail['rows'] ?? 0) >= 8) {
                    $free = array_pop($prompts);
                }
                $blocks[] = ['kind' => 'speak', 'page' => $p, 'prompts' => $prompts];
            } else {
                $qs = [];
                $obs = null;
                foreach ($fields as $f) {
                    if (($f['type'] ?? '') === 'note') {
                        $obs ??= $f;
                        continue;
                    }
                    $qs[] = ['n' => ++$n, 'f' => $f];
                }
                $blocks[] = ['kind' => 'section', 'num' => sprintf('%02d', ++$sec), 'page' => $p, 'qs' => $qs, 'obs' => $obs];
            }
        }

        if ($summary) {
            $sum = ['lines' => [], 'scores' => []];
            $i = 0;
            foreach ($summary['fields'] ?? [] as $f) {
                if (in_array($f['type'] ?? '', ['scale', 'radio', 'checkbox'], true)
                    || (($f['type'] ?? '') === 'text' && $sum['scores'])) {
                    $sum['scores'][] = $f;
                } else {
                    $sum['lines'][] = ['n' => ++$i, 'f' => $f];
                }
            }
            $summary = $sum + ['page' => $summary];
        }

        $title = (string) $form->title;

        return [
            'form' => $form,
            'title' => $title,
            'titleHtml' => $this->titleHtml($title),
            'titleAr' => (string) ($s['title_ar'] ?? ''),
            'lead' => (string) ($form->description ?? ''),
            'leadAr' => (string) ($s['description_ar'] ?? ''),
            'cover' => $cover,
            'interviewer' => $interviewer,
            'blocks' => $blocks,
            'free' => $free,
            'summary' => $summary,
            'thanks' => (string) ($s['successMessage'] ?? 'Thank you for your time.'),
            'thanksAr' => (string) ($s['successMessage_ar'] ?? 'شكراً لوقتكم.'),
            'footerEn' => $this->cssText(Str::limit($title, 60)),
            'footerAr' => $this->cssText(Str::limit((string) ($s['title_ar'] ?? ''), 60)),
        ];
    }

    private function isCoverPage(array $page): bool
    {
        $types = array_column($page['fields'] ?? [], 'type');

        return $types && count(array_diff($types, ['text', 'phone', 'email', 'number', 'checkbox', 'terms'])) === 0;
    }

    /** The grid, the ranking that draws from it, and any short answer beside them. */
    private function grid(array $fields): array
    {
        $grid = collect($fields)->firstWhere('type', 'grid');
        $rank = collect($fields)->first(fn ($f) => ($f['type'] ?? '') === 'rank' && ($f['from'] ?? null) === ($grid['key'] ?? ''));
        $extra = array_values(array_filter($fields, fn ($f) => ! in_array($f['type'] ?? '', ['grid', 'rank', 'note'], true)));

        return ['grid' => $grid, 'rank' => $rank, 'extra' => $extra];
    }

    /** "What does your club really need?" → the word "really" set in the serif. Escaped first. */
    private function titleHtml(string $title): string
    {
        $safe = e($title);

        return preg_replace('/\b(really)\b/i', '<em>$1</em>', $safe, 1) ?? $safe;
    }

    /** Text placed inside a CSS string (the page footers) — quotes and backslashes neutralised. */
    private function cssText(string $s): string
    {
        return str_replace(['\\', '"', "\n", "\r", '<'], ['', '', ' ', ' ', ''], $s);
    }
}
