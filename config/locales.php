<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | Locales the interface can be switched to. `native` is what the language
    | is called in itself — a Nepali speaker looks for "नेपाली", not "Nepali".
    | `pdf_font` names the font family the PDF renderer must use so Devanagari
    | does not come out as empty boxes.
    |
    */

    'supported' => [
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'pdf_font' => 'dejavu sans',
        ],
        'ne' => [
            'name' => 'Nepali',
            'native' => 'नेपाली',
            'pdf_font' => 'notosansdevanagari',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Key
    |--------------------------------------------------------------------------
    |
    | Where the chosen locale is remembered for guests (the login screen, the
    | customer display). Signed-in users get it from their own preference.
    |
    */

    'session_key' => 'locale',

];
