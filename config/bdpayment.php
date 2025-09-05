<?php
/**
 * BdPayment Configuration File
 *
 * This file contains configuration for all supported payment gateways.
 * Set environment variables in your .env file to override defaults.
 *
 * @package RmdMostakim\BdPayment
 */

return [
    // URL to redirect after successful payment (frontend)
    'frontend_success_url' => env('FRONTEND_PAYMENT_SUCCESS_URL', 'http://localhost:3000/payment'),

    // Payment gateway drivers configuration
    'drivers' => [
        // Bkash gateway settings
        'bkash' => [
            'base_url' => env('BKASH_BASE_URL'), // Bkash API base URL
            'username' => env('BKASH_USERNAME'), // Bkash API username
            'password' => env('BKASH_PASSWORD'), // Bkash API password
            'app_key' => env('BKASH_APP_KEY'),   // Bkash app key
            'app_secret' => env('BKASH_APP_SECRET'), // Bkash app secret
            'callback_url' => env('BKASH_CALLBACK_URL', env('APP_URL') . '/api/gateway/bkash/callback'), // Callback URL for Bkash
            'merchant_number' => env('BKASH_MERCHANT_NUMBER', '01xxxxxxxxx'), // Merchant number
            'mode' => env('BKASH_GATEWAT_MODE', 'sandbox'), // Mode: sandbox or production
        ],
        // Nagad gateway settings
        'nagad' => [
            'base_url' => env('NAGAD_BASE_URL'), // Nagad API base URL
            'merchant_id' => env('NAGAD_MERCHANT_ID'), // Nagad merchant ID
            'public_key' => env('NAGAD_PUBLIC_KEY'),   // Path to Nagad public key PEM file
            'private_key' => env('NAGAD_PRIVATE_KEY'), // Path to Nagad private key PEM file
            'callback_url' => env('NAGAD_CALLBACK_URL', env('APP_URL') . '/api/gateway/nagad/callback'), // Callback URL for Nagad
            'mode' => env('NAGAD_GATEWAT_MODE', 'sandbox'), // Mode: sandbox or production
        ],
        // SSLCommerz gateway settings (future implementation)
        'sslcommerz' => [
            'base_url' => env('SSLCOMMERZ_BASE_URL'),
            'store_id' => env('SSLCOMMERZ_STORE_ID'),
            'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
            'callback_url' => env('SSLCOMMERZ_CALLBACK_URL', env('APP_URL') . '/api/gateway/sslcommerz/callback'),
            'mode' => env('SSLCOMMERZ_MODE', 'sandbox'),
            'default_phone'=> env('SSLCOMMERZ_DEFAULT_PHONE', '01711111111'),
            'default_email'=> env('SSLCOMMERZ_DEFAULT_EMAIL', 'hello@example.com'),
            'default_address'=> env('SSLCOMMERZ_DEFAULT_ADDRESS', '123, ABC Road, Dhaka'),
            'default_city'=> env('SSLCOMMERZ_DEFAULT_CITY', 'Dhaka'),
            'default_postcode'=> env('SSLCOMMERZ_DEFAULT_POSTCODE', '1000'),
            'default_country'=> env('SSLCOMMERZ_DEFAULT_COUNTRY', 'Bangladesh'),
        ],
    ]
];
