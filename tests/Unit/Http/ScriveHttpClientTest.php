<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use KalnaLab\Scrive\Exceptions\ScriveApiException;
use KalnaLab\Scrive\Exceptions\ScriveAuthenticationException;
use KalnaLab\Scrive\Exceptions\ScriveNetworkException;
use KalnaLab\Scrive\Exceptions\ScriveValidationException;
use KalnaLab\Scrive\Http\ScriveHttpClient;

function make_client(Factory $http, string $base = 'https://api.example/'): ScriveHttpClient
{
    return new ScriveHttpClient(
        http: $http,
        baseUrl: $base,
        authHeaders: ['Authorization' => 'Bearer token'],
    );
}

it('sends Authorization header on every request', function () {
    $factory = new Factory;
    $factory->fake(['*' => $factory->response(['ok' => true])]);

    make_client($factory)->getJson('ping');

    $factory->assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer token'));
});

it('throws ScriveAuthenticationException on 401', function () {
    $factory = new Factory;
    $factory->fake(['*' => $factory->response('no', 401)]);

    make_client($factory)->getJson('ping');
})->throws(ScriveAuthenticationException::class);

it('throws ScriveAuthenticationException on 403', function () {
    $factory = new Factory;
    $factory->fake(['*' => $factory->response('forbidden', 403)]);

    make_client($factory)->getJson('ping');
})->throws(ScriveAuthenticationException::class);

it('throws ScriveApiException with status and body on other 4xx/5xx', function () {
    $factory = new Factory;
    $factory->fake(['*' => $factory->response('{"err":"bad"}', 422)]);

    try {
        make_client($factory)->postJson('x', ['foo' => 'bar']);
        fail('Expected ScriveApiException');
    } catch (ScriveApiException $e) {
        expect($e->httpStatus)->toBe(422)
            ->and($e->responseBody)->toBe('{"err":"bad"}');
    }
});

it('throws ScriveNetworkException when the transport fails', function () {
    $factory = new Factory;
    $factory->fake(function () {
        throw new Illuminate\Http\Client\ConnectionException('timeout');
    });

    make_client($factory)->getJson('ping');
})->throws(ScriveNetworkException::class, 'Unable to reach Scrive API');

it('rejects empty JSON response bodies', function () {
    $factory = new Factory;
    $factory->fake(['*' => $factory->response('', 200)]);

    make_client($factory)->getJson('ping');
})->throws(ScriveValidationException::class, 'empty response body');

it('rejects invalid JSON', function () {
    $factory = new Factory;
    $factory->fake(['*' => $factory->response('not-json', 200)]);

    make_client($factory)->getJson('ping');
})->throws(ScriveValidationException::class, 'invalid JSON');

it('rejects non-object JSON values', function () {
    $factory = new Factory;
    $factory->fake(['*' => $factory->response('[1,2,3]', 200)]);

    make_client($factory)->getJson('ping');
})->throws(ScriveValidationException::class, 'Expected JSON object');

it('returns raw body as-is for getRaw', function () {
    $factory = new Factory;
    $factory->fake(['*' => $factory->response('raw-binary', 200)]);

    expect(make_client($factory)->getRaw('file'))->toBe('raw-binary');
});

it('does not swallow connection errors on getRaw', function () {
    $factory = new Factory;
    $factory->fake(function () {
        throw new Illuminate\Http\Client\ConnectionException('dropped');
    });

    make_client($factory)->getRaw('file');
})->throws(ScriveNetworkException::class);

it('rejects attachment paths that do not exist', function () {
    $factory = new Factory;

    make_client($factory)->postMultipart('upload', 'attachment', '/does/not/exist.pdf', 'Proof');
})->throws(ScriveValidationException::class, 'not found or not readable');

it('joins base URL and path without duplicating slashes', function () {
    $factory = new Factory;
    $factory->fake(['*' => $factory->response(['ok' => true])]);

    make_client($factory, 'https://api.example/')->getJson('/path/to/thing');

    $factory->assertSent(fn ($request) => $request->url() === 'https://api.example/path/to/thing');
});
