<?php

/*
| Currency labels.
|
| The code IS the English label — "BHD 10" is how a Bahraini price is written
| in English, so every entry here maps to itself and English output is exactly
| what it was before this file existed. The file exists for `lang/ar`, where a
| Latin three-letter code sitting in an Arabic sentence is the thing that reads
| as untranslated (reported 2026-09-04 on the public event poster).
|
| Looked up through App\Events\Support\EventFee::display() with a Lang::has()
| guard, so a currency nobody listed here still renders its code rather than a
| missing-key string.
*/

return [
    'BHD' => 'BHD',
    'SAR' => 'SAR',
    'AED' => 'AED',
    'KWD' => 'KWD',
    'OMR' => 'OMR',
    'QAR' => 'QAR',
    'JOD' => 'JOD',
    'EGP' => 'EGP',
    'USD' => 'USD',
    'EUR' => 'EUR',
    'GBP' => 'GBP',
];
