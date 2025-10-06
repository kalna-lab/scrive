<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    'env' => env('SCRIVE_ENV', 'live'),
    'auth' => [
        'env' => env('SCRIVE_AUTH_ENV', env('SCRIVE_ENV', 'live')),
        'redirect-path' => env('SCRIVE_CALLBACK_PATH', '/login'),
        'landing-path' => env('SCRIVE_LANDING_PATH', '/'),
        'failed-path' => env('SCRIVE_FAILED_PATH', '/'),
        'reference-text' => env('SCRIVE_REFERENCE_TEXT', ''),
        'live' => [
            'token' => env('SCRIVE_TOKEN', ''),
            'base-path' => env('SCRIVE_PATH', 'https://eid.scrive.com/'),
        ],
        'test' => [
            'token' => env('SCRIVE_TEST_TOKEN', ''),
            'base-path' => env('SCRIVE_TEST_PATH', 'https://testbed-eid.scrive.com/'),
        ],
    ],
    'document' => [
        'env' => env('SCRIVE_DOC_ENV', env('SCRIVE_ENV', 'live')),
        'callback-path' => env('SCRIVE_DOC_CALLBACK_PATH', '/'),
        'live' => [
            'api-token' => env('SCRIVE_API_TOKEN', ''),
            'api-secret' => env('SCRIVE_API_SECRET', ''),
            'access-token' => env('SCRIVE_ACCESS_TOKEN', ''),
            'access-secret' => env('SCRIVE_ACCESS_SECRET', ''),
            'base-path' => env('SCRIVE_BASE_PATH', 'https://api.scrive.com/'),
        ],
        'test' => [
            'api-token' => env('SCRIVE_TEST_API_TOKEN', ''),
            'api-secret' => env('SCRIVE_TEST_API_SECRET', ''),
            'access-token' => env('SCRIVE_TEST_ACCESS_TOKEN', ''),
            'access-secret' => env('SCRIVE_TEST_ACCESS_SECRET', ''),
            'base-path' => env('SCRIVE_TEST_BASE_PATH', 'https://api-testbed.scrive.com/'),
        ],
    ],
];
