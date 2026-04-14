<?php

declare(strict_types=1);

use KalnaLab\Scrive\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Bind the package's Testbench-based TestCase to every test file in
| tests/Unit and tests/Feature so Laravel's container, facades and HTTP
| client are available inside each test.
|
*/

uses(TestCase::class)->in('Unit', 'Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
|
| Shared fixtures for tests. Keep these side-effect free — they should
| only construct data or return canned responses.
|
*/

/**
 * A minimal document payload that passes ScriveDocument::loadDocument()'s shape check.
 *
 * @param  array<int, array<string, mixed>>  $parties
 */
function scrive_document(string $id = 'doc-1', array $parties = []): array
{
    return [
        'id' => $id,
        'title' => 'Test document',
        'parties' => $parties !== [] ? $parties : [scrive_signing_party()],
    ];
}

/**
 * A signing party entry with sensible defaults for most tests.
 *
 * @param  array<int, array<string, mixed>>  $fields
 * @return array<string, mixed>
 */
function scrive_signing_party(string $id = 'party-1', array $fields = []): array
{
    return [
        'id' => $id,
        'signatory_role' => 'signing_party',
        'is_author' => false,
        'api_delivery_url' => "/d/{$id}/access",
        'fields' => $fields !== [] ? $fields : [
            ['type' => 'name', 'order' => 1, 'value' => ''],
            ['type' => 'name', 'order' => 2, 'value' => ''],
            ['type' => 'email', 'order' => 1, 'value' => ''],
            ['type' => 'personal_number', 'order' => 1, 'value' => ''],
        ],
    ];
}
