<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    'env' => env('SCRIVE_ENV', 'live'),
    'live' => [
        'token' => env('SCRIVE_TOKEN', ''),
    ],
    'test' => [
        'token' => env('SCRIVE_TEST_TOKEN', ''),
    ],
    'auth' => [
        'redirect-path' => env('SCRIVE_CALLBACK_PATH', '/login'),
        'landing-path' => env('SCRIVE_LANDING_PATH', '/'),
        'failed-path' => env('SCRIVE_FAILED_PATH', '/'),
        'reference-text' => env('SCRIVE_REFERENCE_TEXT', ''),
        'live' => [
            'base-path' => env('SCRIVE_PATH', 'https://eid.scrive.com/api/v2/transaction/'),
        ],
        'test' => [
            'base-path' => env('SCRIVE_TEST_PATH', 'https://testbed-eid.scrive.com/api/v2/transaction/'),
        ],
    ],
    'document' => [
        'live' => [
            'base-path' => env('SCRIVE_PATH', 'https://eid.scrive.com/api/v2/documents/'),
        ],
        'test' => [
            'base-path' => env('SCRIVE_TEST_PATH', 'https://testbed-eid.scrive.com/api/v2/documents/'),
        ],
    ],
];
