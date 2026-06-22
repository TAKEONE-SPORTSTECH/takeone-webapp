<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | Single source of truth for every language the app exposes. The locale
    | switcher, the SetLocale middleware and the <html dir/lang> attributes all
    | read from this list. To add a new language: add an entry here and drop a
    | matching set of files under lang/<code>/ — nothing else needs to change.
    |
    | Each entry:
    |   name   — English name (admin/debug use)
    |   native — name shown to the user in the switcher (in its own script)
    |   dir    — text direction: 'ltr' or 'rtl'
    |   flag   — flag-icons country code (https://github.com/lipis/flag-icons)
    |
    */

    'en' => ['name' => 'English', 'native' => 'English', 'dir' => 'ltr', 'flag' => 'us'],
    'ar' => ['name' => 'Arabic',  'native' => 'العربية', 'dir' => 'rtl', 'flag' => 'sa'],

];
