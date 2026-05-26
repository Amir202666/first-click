<?php

return [

    /*
    |--------------------------------------------------------------------------
    | مصادر أسعار الصرف (بالترتيب)
    |--------------------------------------------------------------------------
    | frankfurter: مجاني، يعتمد ECB — لا يدعم KWD وعملات خليجية عدة.
    | open_er_api: مجاني بدون مفتاح — يدعم KWD, SAR, AED, ...
    */
    'providers' => [
        'frankfurter' => 'https://api.frankfurter.app/latest',
        'open_er_api' => 'https://open.er-api.com/v6/latest',
    ],

    'timeout_seconds' => 15,

    'verify_ssl' => env('EXCHANGE_RATE_VERIFY_SSL', true),

];
