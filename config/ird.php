<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CBMS (Central Billing Monitoring System)
    |--------------------------------------------------------------------------
    |
    | Real-time invoice reporting to the Inland Revenue Department. Disabled
    | until IRD issues the taxpayer a CBMS username and password — which they
    | do only after the billing software itself has been submitted, tested,
    | and listed as approved. Leaving this off keeps invoices queued locally
    | with sync status "disabled" rather than failing every sale.
    |
    */

    'cbms' => [
        'enabled' => env('IRD_CBMS_ENABLED', false),
        'endpoint' => env('IRD_CBMS_ENDPOINT', 'https://cbapi.ird.gov.np/api/bill'),
        'username' => env('IRD_CBMS_USERNAME'),
        'password' => env('IRD_CBMS_PASSWORD'),
        'seller_pan' => env('IRD_SELLER_PAN'),
        'timeout' => (int) env('IRD_CBMS_TIMEOUT', 15),
        'retry_attempts' => (int) env('IRD_CBMS_RETRY_ATTEMPTS', 3),
    ],

];
