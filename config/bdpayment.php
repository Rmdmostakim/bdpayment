<?php
return [
    'drivers' => [
        'bkash' => [
            'base_url' => env('BKASH_BASE_URL'),
            'username' => env('BKASH_USERNAME'),
            'password' => env('BKASH_PASSWORD'),
            'app_key' => env('BKASH_APP_KEY'),
            'app_secret' => env('BKASH_APP_SECRET'),
            'callback_url' => env('BKASH_CALLBACK_URL', 'https://sandbox.com/callback'),
            'merchant_number' => env('BKASH_MERCHANT_NUMBER', ''),
            'mode' => env('BKASH_GATEWAT_MODE', 'sandbox'),
        ],
    ]
];
