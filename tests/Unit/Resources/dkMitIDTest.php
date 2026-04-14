<?php

declare(strict_types=1);

use KalnaLab\Scrive\Exceptions\ScriveValidationException;
use KalnaLab\Scrive\Resources\AuthProviders\dkMitID;
use KalnaLab\Scrive\Resources\AuthProviders\Enums\dkMitIDAction;
use KalnaLab\Scrive\Resources\AuthProviders\Enums\dkMitIDLanguage;
use KalnaLab\Scrive\Resources\AuthProviders\Enums\dkMitIDLevel;
use KalnaLab\Scrive\Resources\AuthProviders\Provider;
use KalnaLab\Scrive\Resources\CompletionData\dkMitIDCompletionData;

it('exposes sensible defaults', function () {
    $provider = new dkMitID;

    expect($provider->action)->toBe(dkMitIDAction::LogOn)
        ->and($provider->language)->toBe(dkMitIDLanguage::Da)
        ->and($provider->level)->toBe(dkMitIDLevel::Substantial)
        ->and($provider->success)->toBeFalse()
        ->and($provider->cpr)->toBe('')
        ->and($provider->referenceText)->toBe('unit-test');
});

it('omits empty cpr from the array representation', function () {
    $array = (new dkMitID)->toArray();

    expect(array_key_exists('cpr', $array))->toBeFalse();
});

it('includes cpr when provided', function () {
    $array = (new dkMitID(cpr: '010180-1234'))->toArray();

    expect($array['cpr'])->toBe('010180-1234');
});

it('parses a completed payload into a success provider', function () {
    $payload = (object)[
        'provider' => 'dkMitID',
        'status' => 'completed',
        'providerInfo' => (object)[
            'dkMitID' => (object)[
                'completionData' => (object)[
                    'cpr' => '010180-1234',
                    'dateOfBirth' => '1980-01-01',
                    'employeeData' => null,
                    'ial' => 'High',
                    'identityName' => 'Jane Doe',
                    'userId' => 'mid-user-9',
                ],
            ],
        ],
    ];

    /** @var dkMitID $provider */
    $provider = Provider::parse($payload);

    expect($provider)->toBeInstanceOf(dkMitID::class)
        ->and($provider->success)->toBeTrue()
        ->and($provider->completionData)->toBeInstanceOf(dkMitIDCompletionData::class)
        ->and($provider->completionData->cpr)->toBe('010180-1234')
        ->and($provider->completionData->identityName)->toBe('Jane Doe')
        ->and($provider->completionData->userId)->toBe('mid-user-9')
        ->and($provider->completionData->dateOfBirth?->format('Y-m-d'))->toBe('1980-01-01');
});

it('returns an unsuccessful provider when status is failed', function () {
    $payload = (object)[
        'provider' => 'dkMitID',
        'status' => 'failed',
        'providerInfo' => (object)['dkMitID' => new stdClass],
    ];

    $provider = Provider::parse($payload);

    expect($provider->success)->toBeFalse()
        ->and($provider->completionData)->toBeInstanceOf(dkMitIDCompletionData::class);
});

it('rejects payloads without a provider field', function () {
    Provider::parse((object)['status' => 'completed']);
})->throws(ScriveValidationException::class, 'missing the `provider`');

it('rejects unknown providers', function () {
    Provider::parse((object)['provider' => 'notARealProvider']);
})->throws(ScriveValidationException::class, 'Unsupported Scrive auth provider');

it('setTransactionId propagates to completionData', function () {
    $provider = new dkMitID;
    $provider->completionData = new dkMitIDCompletionData;

    $provider->setTransactionId('tx-abc');

    expect($provider->completionData->transactionId)->toBe('tx-abc');
});

it('setTransactionId is a no-op when completionData is null', function () {
    $provider = new dkMitID;
    $provider->setTransactionId('tx-abc');

    expect($provider->completionData)->toBeNull();
});
