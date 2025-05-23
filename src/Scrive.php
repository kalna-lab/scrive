<?php

namespace KalnaLab\Scrive;

use KalnaLab\Scrive\Events\NewScriveSignInEvent;
use KalnaLab\Scrive\Resources\AuthProviders\Provider;

class Scrive
{
    const METHOD = [
        'AUTH' => 'auth',
        'SIGN' => 'sign',
    ];

    public string $httpMethod = 'POST';
    public string $env = 'live';
    public array $headers = [];
    public array $body = [];
    public \CurlHandle $curlObject;
    public string $endpoint;

    public function __construct()
    {
        $this->env = config('scrive.env') == 'live' ? 'live' : 'test';
        $this->endpoint = config('scrive.' . $this->env . '.base-path');
    }

    public function authorize(Provider $provider): string
    {
        $this->endpoint .= 'new';
        $this->httpMethod = 'POST';

        $this->instantiateCurl();

        $this->body = [
            'method' => self::METHOD['AUTH'],
            'provider' => $provider::getProviderName(),
            'providerParameters' => [
                self::METHOD['AUTH'] => [$provider::getProviderName() => $provider->toArray()],
            ],
            'redirectUrl' => rtrim(config('app.url'), '/') . '/' . ltrim(config('scrive.redirect-path'), '/'),
        ];

        $result = $this->executeCall();

        return $result->accessUrl;
    }

    /**
     * @throws \Exception
     */
    public function authenticate(string $transactionId): bool
    {
        $this->endpoint .= $transactionId;
        $this->httpMethod = 'GET';

        $this->instantiateCurl();

        $payload = $this->executeCall();

        $provider = Provider::parse($payload);
        if ($provider->success) {
            $provider->setTransactionId($transactionId);
            NewScriveSignInEvent::dispatch($provider->completionData);
        }

        return $provider->success;
    }

    public function sign()
    {
    }

    public function instantiateCurl(): void
    {
        $this->headers = [
            'Authorization' => 'Bearer ' . config('scrive.' . $this->env . '.token'),
        ];

        $this->curlObject = curl_init();
        curl_setopt($this->curlObject, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->curlObject, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->curlObject, CURLOPT_MAXREDIRS, 10);
        curl_setopt($this->curlObject, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlObject, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->curlObject, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($this->curlObject, CURLOPT_TIMEOUT, 30);
    }

    public function setHeaders(array $body = []): void
    {
        $headers = [];
        if ($body) {
            $this->headers['Content-length'] = strlen(json_encode($body, JSON_UNESCAPED_SLASHES));
            $this->headers['Content-Type'] = 'application/json';
        }
        foreach ($this->headers as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        curl_setopt($this->curlObject, CURLOPT_HTTPHEADER, $headers);
    }

    public function executeCall(): array|object
    {
        $this->setHeaders($this->body);
        curl_setopt($this->curlObject, CURLOPT_URL, $this->endpoint);
        if ($this->httpMethod === 'GET') {
            curl_setopt($this->curlObject, CURLOPT_CUSTOMREQUEST, $this->httpMethod);
        } elseif (in_array($this->httpMethod, ['POST', 'PATCH'])) {
            curl_setopt($this->curlObject, CURLOPT_CUSTOMREQUEST, $this->httpMethod);
            curl_setopt($this->curlObject, CURLOPT_POSTFIELDS, json_encode($this->body, JSON_UNESCAPED_SLASHES));
        }
        curl_setopt($this->curlObject, CURLOPT_VERBOSE, true);

        $response = curl_exec($this->curlObject);

        if (empty($response)) {
            curl_close($this->curlObject);
            throw new \Exception('curl_error: ' . curl_error($this->curlObject) . ', curl_errno: ' . curl_errno($this->curlObject));
        }
        curl_close($this->curlObject);

        return json_decode($response);
    }
}
