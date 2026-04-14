<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use KalnaLab\Scrive\Events\NewScriveSignInEvent;
use KalnaLab\Scrive\Exceptions\ScriveApiException;
use KalnaLab\Scrive\Exceptions\ScriveAuthenticationException;
use KalnaLab\Scrive\Exceptions\ScriveNetworkException;
use KalnaLab\Scrive\Exceptions\ScriveValidationException;
use KalnaLab\Scrive\Resources\AuthProviders\dkMitID;
use KalnaLab\Scrive\Resources\CompletionData\dkMitIDCompletionData;
use KalnaLab\Scrive\Scrive;

beforeEach(function () {
    Http::preventStrayRequests();
});

describe('authorize()', function () {
    it('opens a new transaction and returns the access URL', function () {
        Http::fake([
            'eid.test.scrive.example/api/v1/transaction/new' => Http::response(['accessUrl' => 'https://eid.test.scrive.example/d/xyz']),
        ]);

        $url = (new Scrive)->authorize(new dkMitID);

        expect($url)->toBe('https://eid.test.scrive.example/d/xyz');

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $request->method() === 'POST'
                && $request->url() === 'https://eid.test.scrive.example/api/v1/transaction/new'
                && $request->hasHeader('Authorization', 'Bearer bearer-token')
                && $body['method'] === 'auth'
                && $body['provider'] === 'dkMitID'
                && $body['redirectUrl'] === 'https://app.test/login'
                && isset($body['providerParameters']['auth']['dkMitID']);
        });
    });

    it('throws ScriveValidationException when the response is missing accessUrl', function () {
        Http::fake([
            '*' => Http::response(['unexpected' => 'shape']),
        ]);

        (new Scrive)->authorize(new dkMitID);
    })->throws(ScriveValidationException::class, 'missing accessUrl');

    it('throws ScriveAuthenticationException on 401', function () {
        Http::fake([
            '*' => Http::response('unauthorized', 401),
        ]);

        (new Scrive)->authorize(new dkMitID);
    })->throws(ScriveAuthenticationException::class);

    it('throws ScriveApiException on 500', function () {
        Http::fake([
            '*' => Http::response('oops', 500),
        ]);

        (new Scrive)->authorize(new dkMitID);
    })->throws(ScriveApiException::class);

    it('throws ScriveNetworkException on connection failure', function () {
        Http::fake(function () {
            throw new Illuminate\Http\Client\ConnectionException('DNS failure');
        });

        (new Scrive)->authorize(new dkMitID);
    })->throws(ScriveNetworkException::class, 'Unable to reach Scrive API');
});

describe('authenticate()', function () {
    it('dispatches NewScriveSignInEvent when the transaction succeeded', function () {
        Event::fake([NewScriveSignInEvent::class]);
        Http::fake([
            'eid.test.scrive.example/api/v1/transaction/tx-123' => Http::response([
                'provider' => 'dkMitID',
                'status' => 'completed',
                'providerInfo' => [
                    'dkMitID' => [
                        'completionData' => [
                            'cpr' => '010180-1234',
                            'dateOfBirth' => '1980-01-01',
                            'employeeData' => null,
                            'ial' => 'Substantial',
                            'identityName' => 'Jane Doe',
                            'userId' => 'user-9',
                        ],
                    ],
                ],
            ]),
        ]);

        $result = (new Scrive)->authenticate('tx-123');

        expect($result)->toBeTrue();
        Event::assertDispatched(NewScriveSignInEvent::class, function (NewScriveSignInEvent $event) {
            /** @var dkMitIDCompletionData $payload */
            $payload = $event->payload;

            return $payload->transactionId === 'tx-123'
                && $payload->cpr === '010180-1234'
                && $payload->identityName === 'Jane Doe';
        });
    });

    it('does not dispatch the event when the transaction failed', function () {
        Event::fake([NewScriveSignInEvent::class]);
        Http::fake([
            '*' => Http::response([
                'provider' => 'dkMitID',
                'status' => 'failed',
                'providerInfo' => ['dkMitID' => new stdClass],
            ]),
        ]);

        $result = (new Scrive)->authenticate('tx-failed');

        expect($result)->toBeFalse();
        Event::assertNotDispatched(NewScriveSignInEvent::class);
    });

    it('throws ScriveValidationException when the provider field is missing', function () {
        Http::fake([
            '*' => Http::response(['providerInfo' => new stdClass]),
        ]);

        (new Scrive)->authenticate('tx-bad');
    })->throws(ScriveValidationException::class, 'missing the `provider`');
});

describe('validateCpr()', function () {
    it('returns true when isMatch is true', function () {
        Http::fake([
            'eid.test.scrive.example/api/v1/transaction/tx-1/dk/cpr-match' => Http::response(['isMatch' => true]),
        ]);

        expect((new Scrive)->validateCpr('tx-1', '010180-1234'))->toBeTrue();

        Http::assertSent(fn ($request) => json_decode($request->body(), true) === ['cpr' => '010180-1234']);
    });

    it('returns false when isMatch is false', function () {
        Http::fake(['*' => Http::response(['isMatch' => false])]);

        expect((new Scrive)->validateCpr('tx-1', '010180-1234'))->toBeFalse();
    });

    it('throws ScriveValidationException when Scrive returns an err field', function () {
        Http::fake(['*' => Http::response(['err' => 'transaction not finished'])]);

        (new Scrive)->validateCpr('tx-1', '010180-1234');
    })->throws(ScriveValidationException::class, 'CPR match failed');
});
