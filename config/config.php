<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    'env' => env('SCRIVE_ENV', 'live'),
    'redirect-path' => env('SCRIVE_CALLBACK_PATH', '/login'),
    'landing-path' => env('SCRIVE_LANDING_PATH', '/'),
    'live' => [
        'token' => env('SCRIVE_TOKEN', ''),
        'base-path' => env('SCRIVE_PATH', 'https://eid.scrive.com/api/v1/transaction/'),
    ],
    'test' => [
        'token' => env('SCRIVE_TEST_TOKEN', ''),
        'base-path' => env('SCRIVE_TEST_PATH', 'https://testbed-eid.scrive.com/api/v1/transaction/'),
    ],
];
