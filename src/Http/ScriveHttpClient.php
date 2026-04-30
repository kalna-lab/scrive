<?php

declare(strict_types=1);

namespace KalnaLab\Scrive\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use KalnaLab\Scrive\Exceptions\ScriveApiException;
use KalnaLab\Scrive\Exceptions\ScriveAuthenticationException;
use KalnaLab\Scrive\Exceptions\ScriveNetworkException;
use KalnaLab\Scrive\Exceptions\ScriveValidationException;

/**
 * Thin wrapper around Laravel's HTTP client that:
 *
 * - Injects Scrive auth headers (Bearer for eID, OAuth 1.0 PLAINTEXT for documents).
 * - Translates transport and API errors into the typed Scrive* exceptions.
 * - Centralises timeout and TLS configuration so it can't drift between call sites.
 *
 * This class is intentionally not `final` so consumers can extend it when
 * integrating custom middleware, retry policies, or logging.
 */
class ScriveHttpClient
{
    public function __construct(
        private readonly Factory $http,
        private readonly string $baseUrl,
        /** @var array<string, string> */
        private readonly array $authHeaders,
        private readonly int $timeoutSeconds = 30,
    ) {}

    /**
     * POST a JSON body and decode the response as an object.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ScriveApiException
     * @throws ScriveAuthenticationException
     * @throws ScriveNetworkException
     * @throws ScriveValidationException
     */
    public function postJson(string $path, array $payload): \stdClass
    {
        $response = $this->send(
            fn (PendingRequest $req) => $req
                ->withBody(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'application/json')
                ->post($this->url($path))
        );

        return $this->decodeJson($response);
    }

    /**
     * POST a form-encoded body (application/x-www-form-urlencoded).
     *
     * Scrive's document API expects `document` + other fields as form data.
     *
     * @param  array<string, scalar|array<string, mixed>>  $form
     *
     * @throws ScriveApiException
     * @throws ScriveAuthenticationException
     * @throws ScriveNetworkException
     * @throws ScriveValidationException
     */
    public function postForm(string $path, array $form): \stdClass
    {
        $response = $this->send(
            fn (PendingRequest $req) => $req
                ->asForm()
                ->post($this->url($path), $form)
        );

        return $this->decodeJson($response);
    }

    /**
     * POST a multipart/form-data request with an attached file.
     *
     * @throws ScriveApiException
     * @throws ScriveAuthenticationException
     * @throws ScriveNetworkException
     * @throws ScriveValidationException
     */
    public function postMultipart(string $path, string $fieldName, string $filePath, string $attachmentName): \stdClass
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new ScriveValidationException("Attachment file not found or not readable: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new ScriveValidationException("Unable to open attachment file: {$filePath}");
        }

        try {
            $response = $this->send(
                fn (PendingRequest $req) => $req
                    ->attach('attachment', $handle, basename($filePath))
                    ->asMultipart()
                    ->post($this->url($path), ['name' => $attachmentName])
            );
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return $this->decodeJson($response);
    }

    /**
     * GET a resource and decode it as an object.
     *
     * @param  array<string, scalar>  $query
     *
     * @throws ScriveApiException
     * @throws ScriveAuthenticationException
     * @throws ScriveNetworkException
     * @throws ScriveValidationException
     */
    public function getJson(string $path, array $query = []): \stdClass
    {
        $response = $this->send(
            fn (PendingRequest $req) => $req->get($this->url($path), $query)
        );

        return $this->decodeJson($response);
    }

    /**
     * GET a raw binary response (used for PDF downloads).
     *
     * @throws ScriveApiException
     * @throws ScriveAuthenticationException
     * @throws ScriveNetworkException
     */
    public function getRaw(string $path): string
    {
        $response = $this->send(
            fn (PendingRequest $req) => $req->get($this->url($path))
        );

        return $response->body();
    }

    /**
     * Build a fresh, pre-configured PendingRequest.
     *
     * Exposed so integrators can add custom middleware via `tap()` if needed.
     */
    public function pending(): PendingRequest
    {
        return $this->http
            ->withHeaders($this->authHeaders)
            ->timeout($this->timeoutSeconds)
            ->acceptJson();
    }

    /**
     * Execute a request closure and translate errors into Scrive exceptions.
     *
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        try {
            $response = $call($this->pending());
        } catch (ConnectionException $e) {
            throw new ScriveNetworkException(
                'Unable to reach Scrive API: ' . $e->getMessage(),
                previous: $e,
            );
        }

        $this->guard($response);

        return $response;
    }

    private function guard(Response $response): void
    {
        $status = $response->status();

        if ($status < 400) {
            return;
        }

        if ($status === 401 || $status === 403) {
            Log::error(sprintf('Scrive API rejected credentials (HTTP %d)', $status) . "\n" . json_encode($response, JSON_PRETTY_PRINT));
            throw new ScriveAuthenticationException(
                sprintf('Scrive API rejected credentials (HTTP %d)', $status),
                $status,
                $response->body(),
            );
        }

        throw ScriveApiException::fromResponse($response);
    }

    private function decodeJson(Response $response): \stdClass
    {
        $body = $response->body();

        if ($body === '') {
            throw new ScriveValidationException('Scrive API returned an empty response body');
        }

        try {
            $decoded = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ScriveValidationException(
                'Scrive API returned invalid JSON: ' . $e->getMessage(),
                previous: $e,
            );
        }

        if (!$decoded instanceof \stdClass) {
            throw new ScriveValidationException(
                'Expected JSON object in Scrive API response, got ' . get_debug_type($decoded)
            );
        }

        return $decoded;
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }
}
