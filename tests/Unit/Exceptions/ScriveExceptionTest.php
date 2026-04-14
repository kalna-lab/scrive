<?php

declare(strict_types=1);

use Illuminate\Http\Client\Response;
use KalnaLab\Scrive\Exceptions\ScriveApiException;
use KalnaLab\Scrive\Exceptions\ScriveAuthenticationException;
use KalnaLab\Scrive\Exceptions\ScriveException;
use KalnaLab\Scrive\Exceptions\ScriveNetworkException;
use KalnaLab\Scrive\Exceptions\ScriveValidationException;

it('places every exception under the ScriveException root', function (string $class) {
    expect(new $class('boom'))->toBeInstanceOf(ScriveException::class);
})->with([
    [ScriveApiException::class],
    [ScriveAuthenticationException::class],
    [ScriveNetworkException::class],
    [ScriveValidationException::class],
]);

it('makes ScriveAuthenticationException a ScriveApiException', function () {
    expect(new ScriveAuthenticationException('nope'))
        ->toBeInstanceOf(ScriveApiException::class);
});

it('captures http status and response body on ScriveApiException', function () {
    $exception = new ScriveApiException('rejected', 422, '{"error":"bad"}');

    expect($exception->httpStatus)->toBe(422)
        ->and($exception->responseBody)->toBe('{"error":"bad"}')
        ->and($exception->getCode())->toBe(422);
});

it('builds ScriveApiException from an HTTP response', function () {
    $psrResponse = new GuzzleHttp\Psr7\Response(500, [], '{"msg":"boom"}');
    $response = new Response($psrResponse);

    $exception = ScriveApiException::fromResponse($response);

    expect($exception->httpStatus)->toBe(500)
        ->and($exception->responseBody)->toBe('{"msg":"boom"}')
        ->and($exception->getMessage())->toContain('Scrive API returned HTTP 500')
        ->and($exception->getMessage())->toContain('{"msg":"boom"}');
});

it('truncates very large response bodies in the message', function () {
    $body = str_repeat('A', 2000);
    $psrResponse = new GuzzleHttp\Psr7\Response(500, [], $body);
    $response = new Response($psrResponse);

    $exception = ScriveApiException::fromResponse($response);

    expect(strlen($exception->getMessage()))->toBeLessThan(1000)
        ->and($exception->responseBody)->toBe($body); // full body preserved
});
