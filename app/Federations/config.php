<?php

return [

    /*
    | The federation dashboard (app/Federations). New and still being shaped,
    | so it is OFF unless switched on: with it off every federation route
    | answers 404, exactly as if the module were not there.
    */
    'enabled' => (bool) env('FEDERATIONS_ENABLED', false),

    /*
    | TakeOne's own account — where a federation pays its subscription by
    | bank transfer (there is no card gateway). Shown on the dashboard's pay
    | screen; while the IBAN is unset the screen says so instead of showing a
    | placeholder number.
    */
    'takeone_bank' => [
        'beneficiary' => env('TAKEONE_BANK_BENEFICIARY', 'TakeOne Sports Technologies W.L.L.'),
        'bank' => env('TAKEONE_BANK_NAME'),
        'iban' => env('TAKEONE_BANK_IBAN'),
        'swift' => env('TAKEONE_BANK_SWIFT'),
    ],

];
