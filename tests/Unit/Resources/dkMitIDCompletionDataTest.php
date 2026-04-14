<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use KalnaLab\Scrive\Exceptions\ScriveValidationException;
use KalnaLab\Scrive\Resources\CompletionData\dkMitIDCompletionData;
use KalnaLab\Scrive\Scrive;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('delegates CPR validation to the Scrive client', function () {
    Http::fake([
        'eid.test.scrive.example/api/v1/transaction/tx-1/dk/cpr-match' => Http::response(['isMatch' => true]),
    ]);

    $data = new dkMitIDCompletionData;
    $data->transactionId = 'tx-1';

    expect($data->validateCPR('010180-1234', new Scrive))->toBeTrue();
});

it('throws when called without a transactionId', function () {
    $data = new dkMitIDCompletionData;

    $data->validateCPR('010180-1234');
})->throws(ScriveValidationException::class, 'without a transactionId');
