<?php

declare(strict_types=1);

namespace KalnaLab\Scrive;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Log;
use KalnaLab\Scrive\Events\NewScriveSignInEvent;
use KalnaLab\Scrive\Exceptions\ScriveValidationException;
use KalnaLab\Scrive\Http\ScriveHttpClient;
use KalnaLab\Scrive\Resources\AuthProviders\Provider;

/**
 * Client for the Scrive eID API (https://eid.scrive.com/documentation/api/v1/).
 *
 * Handles the three-step authentication flow:
 *
 *   1. {@see authorize()} creates a new transaction and returns the URL
 *      the user should be redirected to.
 *   2. The user completes the flow in their browser and is redirected back
 *      to the callback route with a `transaction_id`.
 *   3. {@see authenticate()} exchanges the transaction id for the
 *      completion data and dispatches {@see NewScriveSignInEvent}.
 *
 * Authentication uses a Bearer token configured in `config/scrive.php` under
 * `auth.<env>.token`.
 */
class Scrive
{
    private const API_PREFIX = '/api/v1/transaction/';

    private readonly string $env;
    private readonly ScriveHttpClient $client;

    public function __construct(?Factory $http = null)
    {
        $this->env = config('scrive.auth.env') === 'live' ? 'live' : 'test';

        $baseUrl = rtrim((string)config('scrive.auth.' . $this->env . '.base-path'), '/') . self::API_PREFIX;
        $token = (string)config('scrive.auth.' . $this->env . '.token');

        $this->client = new ScriveHttpClient(
            http: $http ?? app(Factory::class),
            baseUrl: $baseUrl,
            authHeaders: ['Authorization' => 'Bearer ' . $token],
        );
    }

    /**
     * Open a new eID transaction with the given provider and return the
     * access URL the end user should be redirected to.
     */
    public function authorize(Provider $provider): string
    {
        $payload = [
            'method' => 'auth',
            'provider' => $provider::getProviderName(),
            'providerParameters' => [
                'auth' => [$provider::getProviderName() => $provider->toArray()],
            ],
            'redirectUrl' => rtrim((string)config('app.url'), '/')
                . '/' . ltrim((string)config('scrive.auth.redirect-path'), '/'),
        ];

        $result = $this->client->postJson('new', $payload);

        if (!property_exists($result, 'accessUrl') || !is_string($result->accessUrl)) {
            Log::error('Scrive eID response missing accessUrl' . "\n" . json_encode($result, JSON_PRETTY_PRINT));
            throw new ScriveValidationException('Scrive eID response missing accessUrl');
        }

        return $result->accessUrl;
    }

    /**
     * Complete an eID transaction. Dispatches {@see NewScriveSignInEvent}
     * on success. Returns whether the provider reports success.
     */
    public function authenticate(string $transactionId): bool
    {
        $payload = $this->client->getJson($transactionId);

        $provider = Provider::parse($payload);

        if ($provider->success) {
            $provider->setTransactionId($transactionId);
            if ($provider->completionData !== null) {
                NewScriveSignInEvent::dispatch($provider->completionData);
            }
        }

        return $provider->success;
    }

    /**
     * Verify that a supplied CPR matches the one returned in the completed
     * Danish MitID transaction. Scrive returns `{ isMatch: bool }` for the
     * check endpoint, or `{ err: ... }` on failure.
     */
    public function validateCpr(string $transactionId, string $cpr): bool
    {
        $result = $this->client->postJson($transactionId . '/dk/cpr-match', ['cpr' => $cpr]);

        if (property_exists($result, 'err')) {
            throw new ScriveValidationException('CPR match failed: ' . (string)$result->err);
        }

        return property_exists($result, 'isMatch') && $result->isMatch === true;
    }

    public function httpClient(): ScriveHttpClient
    {
        return $this->client;
    }
}
