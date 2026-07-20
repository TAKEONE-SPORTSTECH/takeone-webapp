<?php

namespace Database\Seeders;

use App\Models\ActivityCatalog;
use App\Models\ClubActivity;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Scopes\TenantScope;

/**
 * Seeds the global activity directory with a broad starter set of common
 * sports/fitness activities (EN + AR names), then backfills any distinct
 * activities clubs have already created so nothing existing is lost.
 *
 * Idempotent: keyed by canonical slug, safe to re-run.
 */
class ActivityCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $variants = $this->variantMap();

        foreach ($this->starterSet() as [$en, $ar, $icon]) {
            $slug = ActivityCatalog::slugFor($en);
            $entry = ActivityCatalog::firstOrNew(['slug' => $slug]);
            if (! $entry->exists) {
                $entry->name = $en;
                $entry->icon = $icon;
                $entry->is_active = true;
            }
            if ($ar && blank(data_get($entry->translations, 'name.ar'))) {
                $entry->setTranslation('name', 'ar', $ar);
            }
            // Seed curated styles/federations (deduped; never removes club-added ones).
            foreach ($variants[$slug] ?? [] as [$vEn, $vAr]) {
                $entry->addVariant($vEn, $vAr);
            }
            $entry->save();
        }

        // Backfill from what clubs already created (bypass the tenant scope so
        // we see every club's activities, not just the seeder's context).
        ClubActivity::withoutGlobalScope(TenantScope::class)
            ->get(['name', 'description', 'translations', 'picture_url', 'tenant_id'])
            ->each(function ($a) {
                if (blank($a->name)) {
                    return;
                }
                ActivityCatalog::contribute([
                    'name' => $a->name,
                    'description' => $a->description,
                    'translations' => $a->translations,
                    'picture_url' => $a->picture_url,
                ], $a->tenant_id);
            });
    }

    /**
     * Curated styles / federations per discipline, keyed by catalog slug.
     * @return array<string, array<int, array{0:string,1:?string}>> [EN, AR]
     */
    private function variantMap(): array
    {
        return [
            'taekwondo' => [
                ['WTF / Kukkiwon', 'الاتحاد العالمي / كوكيون'],
                ['ITF', 'الاتحاد الدولي'],
            ],
            'karate' => [
                ['Shotokan', 'شوتوكان'],
                ['Goju-ryu', 'غوجو-ريو'],
                ['Shito-ryu', 'شيتو-ريو'],
                ['Wado-ryu', 'وادو-ريو'],
                ['Kyokushin', 'كيوكوشين'],
            ],
            'kung-fu' => [
                ['Wing Chun', 'وينغ تشون'],
                ['Shaolin', 'شاولين'],
                ['Tai Chi', 'تاي تشي'],
            ],
            'brazilian-jiu-jitsu' => [
                ['Gi', 'مع البدلة'],
                ['No-Gi', 'بدون البدلة'],
            ],
            'judo' => [
                ['Kodokan', 'كودوكان'],
            ],
            'wrestling' => [
                ['Freestyle', 'حرة'],
                ['Greco-Roman', 'رومانية'],
            ],
            'boxing' => [
                ['Amateur', 'هواة'],
                ['Professional', 'محترفين'],
            ],
        ];
    }

    /**
     * @return array<int, array{0:string,1:?string,2:?string}> [English, Arabic, bootstrap-icon]
     */
    private function starterSet(): array
    {
        return [
            // Combat / martial arts
            ['Taekwondo', 'التايكوندو', 'bi-person-arms-up'],
            ['Karate', 'الكاراتيه', 'bi-person-arms-up'],
            ['Judo', 'الجودو', 'bi-person-arms-up'],
            ['Boxing', 'الملاكمة', 'bi-person-arms-up'],
            ['Kickboxing', 'الكيك بوكسينغ', 'bi-person-arms-up'],
            ['Muay Thai', 'المواي تاي', 'bi-person-arms-up'],
            ['Brazilian Jiu-Jitsu', 'الجوجيتسو البرازيلي', 'bi-person-arms-up'],
            ['Mixed Martial Arts', 'الفنون القتالية المختلطة', 'bi-person-arms-up'],
            ['Wrestling', 'المصارعة', 'bi-person-arms-up'],
            ['Fencing', 'المبارزة', 'bi-person-arms-up'],
            ['Kung Fu', 'الكونغ فو', 'bi-person-arms-up'],
            ['Aikido', 'الأيكيدو', 'bi-person-arms-up'],
            ['Capoeira', 'الكابويرا', 'bi-person-arms-up'],

            // Fitness / gym
            ['Fitness Training', 'اللياقة البدنية', 'bi-heart-pulse'],
            ['CrossFit', 'الكروسفيت', 'bi-heart-pulse'],
            ['Weightlifting', 'رفع الأثقال', 'bi-heart-pulse'],
            ['Bodybuilding', 'كمال الأجسام', 'bi-heart-pulse'],
            ['Functional Training', 'التدريب الوظيفي', 'bi-heart-pulse'],
            ['Pilates', 'البيلاتس', 'bi-heart-pulse'],
            ['Yoga', 'اليوغا', 'bi-heart-pulse'],
            ['Zumba', 'الزومبا', 'bi-music-note-beamed'],
            ['Spinning', 'سبينينغ', 'bi-bicycle'],
            ['Aerobics', 'الأيروبيك', 'bi-heart-pulse'],

            // Racket / court
            ['Tennis', 'التنس', 'bi-dribbble'],
            ['Padel', 'البادل', 'bi-dribbble'],
            ['Squash', 'الاسكواش', 'bi-dribbble'],
            ['Badminton', 'الريشة الطائرة', 'bi-dribbble'],
            ['Table Tennis', 'تنس الطاولة', 'bi-dribbble'],

            // Team sports
            ['Football', 'كرة القدم', 'bi-dribbble'],
            ['Basketball', 'كرة السلة', 'bi-dribbble'],
            ['Volleyball', 'الكرة الطائرة', 'bi-dribbble'],
            ['Handball', 'كرة اليد', 'bi-dribbble'],
            ['Cricket', 'الكريكيت', 'bi-dribbble'],
            ['Rugby', 'الرغبي', 'bi-dribbble'],
            ['Hockey', 'الهوكي', 'bi-dribbble'],

            // Water
            ['Swimming', 'السباحة', 'bi-water'],
            ['Water Polo', 'كرة الماء', 'bi-water'],
            ['Diving', 'الغوص', 'bi-water'],
            ['Rowing', 'التجديف', 'bi-water'],

            // Other
            ['Gymnastics', 'الجمباز', 'bi-person-arms-up'],
            ['Athletics', 'ألعاب القوى', 'bi-stopwatch'],
            ['Running', 'الجري', 'bi-stopwatch'],
            ['Cycling', 'ركوب الدراجات', 'bi-bicycle'],
            ['Ballet', 'الباليه', 'bi-music-note-beamed'],
            ['Dance', 'الرقص', 'bi-music-note-beamed'],
            ['Rock Climbing', 'تسلق الصخور', 'bi-triangle'],
            ['Archery', 'الرماية', 'bi-bullseye'],
            ['Horse Riding', 'ركوب الخيل', 'bi-person'],
            ['Golf', 'الغولف', 'bi-flag'],
            ['Skating', 'التزلج', 'bi-person'],
            ['Chess', 'الشطرنج', 'bi-grid-3x3'],
        ];
    }
}
