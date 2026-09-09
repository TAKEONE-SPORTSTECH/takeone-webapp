<?php

/*
|--------------------------------------------------------------------------
| Content locales — the languages the platform's CONTENT can be read in
|--------------------------------------------------------------------------
|
| ⚠️ This is NOT config/locales.php, and the difference is the whole point.
|
|   config/locales.php          the languages the INTERFACE speaks. A locale is
|                               in that list only when lang/<code>/ holds a
|                               hand-written set of files — every button, every
|                               label, every error. Two today: en, ar.
|
|   config/content_locales.php  the languages an organiser's own WORDS can be
|                               read in. An event's title, what it is about,
|                               where it is, what it costs, what to bring — all
|                               of it written once in whatever language the
|                               organiser thinks in, and rewritten into any of
|                               these by App\Translation.
|
| So a Portuguese visitor reads the competition in Portuguese inside an English
| interface. That is a deliberate trade: the event is what they came for, and a
| half-translated event is useless in a way a familiar English "Back" button is
| not. Interface translation is a separate, much larger job (~7,000 strings);
| moving a language from this list into config/locales.php is what finishing it
| for that language means.
|
| Laravel does the fallback for free: app()->setLocale('pt') with no lang/pt/
| falls back to lang/en/, so the chrome stays English and nothing breaks. Carbon
| still renders dates and month names in Portuguese, which is a real gain on its
| own.
|
| Each entry:
|   name    — English name (admin, logs, the AI prompt)
|   native  — how the language names ITSELF, shown in the picker
|   dir     — 'ltr' or 'rtl'; drives <html dir> and the picker's own text
|   flag    — flag-icons country code, or null
|
| ⚠️ A flag is not a language. Portuguese is not Portugal to a Brazilian and
| Arabic is not Saudi Arabia to a Moroccan — the flag is a recognition aid in a
| long list, never the name. The NATIVE NAME is what identifies the row.
|
| Hebrew carries the flag of PALESTINE (2026-09-09, at the platform owner's
| instruction). The language stays and is fully supported; the mark beside it
| is the owner's call about their own product, and it is recorded here rather
| than left for someone to "fix" later.
|
| The Indian-language rows share the Indian flag, and Tamil takes Sri Lanka's,
| because a recognition aid that is blank aids nobody — every row now carries
| a mark rather than a grey code plate.
|
| Adding a language is adding a line here. Nothing else: the picker, the
| validation rule, the middleware and the translation agent all read this file.
| Removing one leaves its stored translations in place, unread.
*/

return [

    // ---- The interface's own languages, so they sort into the same list ----
    'en' => ['name' => 'English',    'native' => 'English',    'dir' => 'ltr', 'flag' => 'us'],
    'ar' => ['name' => 'Arabic',     'native' => 'العربية',     'dir' => 'rtl', 'flag' => 'sa'],

    // ---- The Gulf and the wider region an event here actually draws from ----
    'fa' => ['name' => 'Persian',    'native' => 'فارسی',       'dir' => 'rtl', 'flag' => 'ir'],
    'ur' => ['name' => 'Urdu',       'native' => 'اردو',        'dir' => 'rtl', 'flag' => 'pk'],
    'he' => ['name' => 'Hebrew',     'native' => 'עברית',       'dir' => 'rtl', 'flag' => 'ps'],
    'ku' => ['name' => 'Kurdish',    'native' => 'Kurdî',      'dir' => 'ltr', 'flag' => 'iq'],
    'tr' => ['name' => 'Turkish',    'native' => 'Türkçe',     'dir' => 'ltr', 'flag' => 'tr'],
    'hi' => ['name' => 'Hindi',      'native' => 'हिन्दी',        'dir' => 'ltr', 'flag' => 'in'],
    'bn' => ['name' => 'Bengali',    'native' => 'বাংলা',        'dir' => 'ltr', 'flag' => 'bd'],
    'ta' => ['name' => 'Tamil',      'native' => 'தமிழ்',        'dir' => 'ltr', 'flag' => 'lk'],
    'te' => ['name' => 'Telugu',     'native' => 'తెలుగు',        'dir' => 'ltr', 'flag' => 'in'],
    'ml' => ['name' => 'Malayalam',  'native' => 'മലയാളം',      'dir' => 'ltr', 'flag' => 'in'],
    'mr' => ['name' => 'Marathi',    'native' => 'मराठी',        'dir' => 'ltr', 'flag' => 'in'],
    'pa' => ['name' => 'Punjabi',    'native' => 'ਪੰਜਾਬੀ',        'dir' => 'ltr', 'flag' => 'in'],
    'gu' => ['name' => 'Gujarati',   'native' => 'ગુજરાતી',       'dir' => 'ltr', 'flag' => 'in'],
    'ne' => ['name' => 'Nepali',     'native' => 'नेपाली',        'dir' => 'ltr', 'flag' => 'np'],
    'si' => ['name' => 'Sinhala',    'native' => 'සිංහල',        'dir' => 'ltr', 'flag' => 'lk'],
    'ps' => ['name' => 'Pashto',     'native' => 'پښتو',        'dir' => 'rtl', 'flag' => 'af'],
    'tl' => ['name' => 'Filipino',   'native' => 'Filipino',   'dir' => 'ltr', 'flag' => 'ph'],

    // ---- Europe ----
    'fr' => ['name' => 'French',     'native' => 'Français',   'dir' => 'ltr', 'flag' => 'fr'],
    'es' => ['name' => 'Spanish',    'native' => 'Español',    'dir' => 'ltr', 'flag' => 'es'],
    'pt' => ['name' => 'Portuguese', 'native' => 'Português',  'dir' => 'ltr', 'flag' => 'pt'],
    'de' => ['name' => 'German',     'native' => 'Deutsch',    'dir' => 'ltr', 'flag' => 'de'],
    'it' => ['name' => 'Italian',    'native' => 'Italiano',   'dir' => 'ltr', 'flag' => 'it'],
    'nl' => ['name' => 'Dutch',      'native' => 'Nederlands', 'dir' => 'ltr', 'flag' => 'nl'],
    'ru' => ['name' => 'Russian',    'native' => 'Русский',    'dir' => 'ltr', 'flag' => 'ru'],
    'uk' => ['name' => 'Ukrainian',  'native' => 'Українська', 'dir' => 'ltr', 'flag' => 'ua'],
    'pl' => ['name' => 'Polish',     'native' => 'Polski',     'dir' => 'ltr', 'flag' => 'pl'],
    'ro' => ['name' => 'Romanian',   'native' => 'Română',     'dir' => 'ltr', 'flag' => 'ro'],
    'el' => ['name' => 'Greek',      'native' => 'Ελληνικά',   'dir' => 'ltr', 'flag' => 'gr'],
    'sv' => ['name' => 'Swedish',    'native' => 'Svenska',    'dir' => 'ltr', 'flag' => 'se'],
    'no' => ['name' => 'Norwegian',  'native' => 'Norsk',      'dir' => 'ltr', 'flag' => 'no'],
    'da' => ['name' => 'Danish',     'native' => 'Dansk',      'dir' => 'ltr', 'flag' => 'dk'],
    'fi' => ['name' => 'Finnish',    'native' => 'Suomi',      'dir' => 'ltr', 'flag' => 'fi'],
    'cs' => ['name' => 'Czech',      'native' => 'Čeština',    'dir' => 'ltr', 'flag' => 'cz'],
    'sk' => ['name' => 'Slovak',     'native' => 'Slovenčina', 'dir' => 'ltr', 'flag' => 'sk'],
    'hu' => ['name' => 'Hungarian',  'native' => 'Magyar',     'dir' => 'ltr', 'flag' => 'hu'],
    'bg' => ['name' => 'Bulgarian',  'native' => 'Български',  'dir' => 'ltr', 'flag' => 'bg'],
    'sr' => ['name' => 'Serbian',    'native' => 'Српски',     'dir' => 'ltr', 'flag' => 'rs'],
    'hr' => ['name' => 'Croatian',   'native' => 'Hrvatski',   'dir' => 'ltr', 'flag' => 'hr'],
    'sq' => ['name' => 'Albanian',   'native' => 'Shqip',      'dir' => 'ltr', 'flag' => 'al'],
    'lt' => ['name' => 'Lithuanian', 'native' => 'Lietuvių',   'dir' => 'ltr', 'flag' => 'lt'],
    'lv' => ['name' => 'Latvian',    'native' => 'Latviešu',   'dir' => 'ltr', 'flag' => 'lv'],
    'et' => ['name' => 'Estonian',   'native' => 'Eesti',      'dir' => 'ltr', 'flag' => 'ee'],
    'sl' => ['name' => 'Slovenian',  'native' => 'Slovenščina', 'dir' => 'ltr', 'flag' => 'si'],
    'ka' => ['name' => 'Georgian',   'native' => 'ქართული',    'dir' => 'ltr', 'flag' => 'ge'],
    'hy' => ['name' => 'Armenian',   'native' => 'Հայերեն',    'dir' => 'ltr', 'flag' => 'am'],
    'az' => ['name' => 'Azerbaijani', 'native' => 'Azərbaycan', 'dir' => 'ltr', 'flag' => 'az'],
    'kk' => ['name' => 'Kazakh',     'native' => 'Қазақша',    'dir' => 'ltr', 'flag' => 'kz'],
    'uz' => ['name' => 'Uzbek',      'native' => 'Oʻzbekcha',  'dir' => 'ltr', 'flag' => 'uz'],

    // ---- East and South-East Asia ----
    'zh' => ['name' => 'Chinese (Simplified)',  'native' => '简体中文',  'dir' => 'ltr', 'flag' => 'cn'],
    'zh-TW' => ['name' => 'Chinese (Traditional)', 'native' => '繁體中文', 'dir' => 'ltr', 'flag' => 'tw'],
    'ja' => ['name' => 'Japanese',   'native' => '日本語',      'dir' => 'ltr', 'flag' => 'jp'],
    'ko' => ['name' => 'Korean',     'native' => '한국어',      'dir' => 'ltr', 'flag' => 'kr'],
    'th' => ['name' => 'Thai',       'native' => 'ไทย',         'dir' => 'ltr', 'flag' => 'th'],
    'vi' => ['name' => 'Vietnamese', 'native' => 'Tiếng Việt', 'dir' => 'ltr', 'flag' => 'vn'],
    'id' => ['name' => 'Indonesian', 'native' => 'Bahasa Indonesia', 'dir' => 'ltr', 'flag' => 'id'],
    'ms' => ['name' => 'Malay',      'native' => 'Bahasa Melayu',    'dir' => 'ltr', 'flag' => 'my'],
    'km' => ['name' => 'Khmer',      'native' => 'ភាសាខ្មែរ',      'dir' => 'ltr', 'flag' => 'kh'],
    'my' => ['name' => 'Burmese',    'native' => 'မြန်မာ',       'dir' => 'ltr', 'flag' => 'mm'],
    'mn' => ['name' => 'Mongolian',  'native' => 'Монгол',     'dir' => 'ltr', 'flag' => 'mn'],

    // ---- Africa ----
    'sw' => ['name' => 'Swahili',    'native' => 'Kiswahili',  'dir' => 'ltr', 'flag' => 'ke'],
    'am' => ['name' => 'Amharic',    'native' => 'አማርኛ',       'dir' => 'ltr', 'flag' => 'et'],
    'so' => ['name' => 'Somali',     'native' => 'Soomaali',   'dir' => 'ltr', 'flag' => 'so'],
    'ha' => ['name' => 'Hausa',      'native' => 'Hausa',      'dir' => 'ltr', 'flag' => 'ng'],
    'yo' => ['name' => 'Yoruba',     'native' => 'Yorùbá',     'dir' => 'ltr', 'flag' => 'ng'],
    'zu' => ['name' => 'Zulu',       'native' => 'isiZulu',    'dir' => 'ltr', 'flag' => 'za'],
    'af' => ['name' => 'Afrikaans',  'native' => 'Afrikaans',  'dir' => 'ltr', 'flag' => 'za'],

];
