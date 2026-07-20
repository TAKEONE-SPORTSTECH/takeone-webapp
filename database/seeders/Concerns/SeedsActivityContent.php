<?php

namespace Database\Seeders\Concerns;

use App\Models\ActivityCatalog;

/**
 * Shared machinery for the activity-content seeders: turns a compact per-sport
 * data array into bilingual (EN base + AR translations) HTML, upserts the
 * directory entry (keyed by slug), stores the image prompt, and hides any
 * generic parent that has been split into variant entries.
 *
 * A concrete seeder only implements entries().
 */
trait SeedsActivityContent
{
    /** @return array<int, array<string, mixed>> */
    abstract protected function entries(): array;

    /** Localized section headings (with emoji) shared by every entry. */
    private array $contentHeadings = [
        'en' => [
            'history' => '📜 Origins &amp; Story',
            'focus' => '🎯 What It Focuses On',
            'benefits' => '💪 Benefits',
            'limitations' => '⚠️ Limitations',
            'rules' => '📋 Rules in Brief',
            'links' => '🔗 Trusted Resources',
        ],
        'ar' => [
            'history' => '📜 النشأة والقصة',
            'focus' => '🎯 محور التركيز',
            'benefits' => '💪 الفوائد',
            'limitations' => '⚠️ القيود',
            'rules' => '📋 القوانين باختصار',
            'links' => '🔗 مصادر موثوقة',
        ],
    ];

    public function run(): void
    {
        $deactivateParents = [];

        foreach ($this->entries() as $e) {
            $entry = ActivityCatalog::firstOrNew(['slug' => $e['slug']]);

            $entry->name = $e['name'];
            $entry->icon = $e['icon'] ?? $entry->icon ?? 'bi-activity';
            $entry->is_active = true;
            $entry->image_prompt = $e['image_prompt'] ?? null;

            $entry->description = $this->buildContentHtml('en', $e);
            $entry->setTranslation('name', 'ar', $e['name_ar'] ?? null);
            $entry->setTranslation('description', 'ar', $this->buildContentHtml('ar', $e));

            $entry->save();

            foreach ((array) ($e['replaces'] ?? []) as $parentSlug) {
                $deactivateParents[$parentSlug] = true;
            }
        }

        foreach (array_keys($deactivateParents) as $parentSlug) {
            ActivityCatalog::where('slug', $parentSlug)->update(['is_active' => false]);
        }
    }

    private function buildContentHtml(string $locale, array $e): string
    {
        $h = $this->contentHeadings[$locale];
        $c = $e[$locale];
        $dir = $locale === 'ar' ? ' dir="rtl"' : '';
        $out = "<div{$dir}>";

        if (! empty($c['intro'])) {
            $out .= '<p>'.$c['intro'].'</p>';
        }

        $out .= "<h3>{$h['history']}</h3>".$this->paras($c['history'] ?? []);
        $out .= "<h3>{$h['focus']}</h3>".$this->paras($c['focus'] ?? []);
        $out .= "<h3>{$h['benefits']}</h3>".$this->contentUl($c['benefits'] ?? []);
        $out .= "<h3>{$h['limitations']}</h3>".$this->contentUl($c['limitations'] ?? []);
        $out .= "<h3>{$h['rules']}</h3>".$this->contentUl($c['rules'] ?? []);

        if (! empty($e['links'])) {
            $items = '';
            foreach ($e['links'] as $l) {
                $url = htmlspecialchars($l['url'], ENT_QUOTES);
                $label = htmlspecialchars($l['label'], ENT_QUOTES);
                $items .= "<li><a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\">{$label}</a></li>";
            }
            $out .= "<h3>{$h['links']}</h3><ul>{$items}</ul>";
        }

        return $out.'</div>';
    }

    /** Render one or more paragraphs (accepts a string or an array of strings). */
    private function paras(string|array $value): string
    {
        $paras = is_array($value) ? $value : [$value];

        return implode('', array_map(
            fn ($p) => trim((string) $p) === '' ? '' : '<p>'.$p.'</p>',
            $paras,
        ));
    }

    private function contentUl(array $items): string
    {
        if (empty($items)) {
            return '';
        }

        return '<ul>'.implode('', array_map(fn ($i) => "<li>{$i}</li>", $items)).'</ul>';
    }
}
