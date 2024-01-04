<?php

namespace KalnaLab\Scrive;

use Illuminate\Http\RedirectResponse;
use KalnaLab\Scrive\Events\NewScriveSignInEvent;
use KalnaLab\Scrive\Resources\AuthProviders\Provider;

class Scrive
{
    const METHOD = [
        'AUTH' => 'auth',
        'SIGN' => 'sign',
    ];

    private string $env = 'live';
    private string $method = '';
    private array $headers = [];
    private \CurlHandle $curlObject;
    private string $endpoint;
    private Provider $provider;

    public function __construct()
    {
        $this->env = config('scrive.env') == 'live' ? 'live' : 'test';
        $this->endpoint = config('scrive.' . $this->env . '.base-path');
    }

    public function authorize(Provider $provider): RedirectResponse
    {
        $this->provider = $provider;
        $this->endpoint .= 'new';
        $this->method = self::METHOD['AUTH'];

        $this->instantiateCurl();

        $result = $this->executeCall();

        return redirect($result->accessUrl);
    }

    /**
     * @throws \Exception
     */
    public function authenticate(object|string $payload): bool
    {
        if (is_string($payload)) {
            $payload = json_decode($payload);
        }
        $this->provider = Provider::parse($payload);

        NewScriveSignInEvent::dispatch($this->provider->completionData);

        return (bool)$this->provider->completionData->userId;
    }

    public function sign()
    {
        return redirect($provider->getAuthorizationUrl());
    }

    private function instantiateCurl(): void
    {
        $this->headers = [
            'Authorization' => config('scrive.' . $this->env . '.token'),
            'Content-type' => 'application/json',
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

    private function setHeaders($body): void
    {
        $headers = [];
        $this->headers['Content-length'] = strlen(json_encode($body, JSON_UNESCAPED_SLASHES));
        foreach ($this->headers as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        curl_setopt($this->curlObject, CURLOPT_HTTPHEADER, $headers);
    }

    private function executeCall(): array|object
    {
        $body = [
            'method' => $this->method,
            'provider' => $this->provider::getProviderName(),
            'providerParameters' => [
                $this->method => $this->provider->toArray(),
            ],
            'redirectUrl' => rtrim(config('app.url'), '/') . '/' . config('scrive.redirect-path'),
        ];
        $this->setHeaders($body);
        curl_setopt($this->curlObject, CURLOPT_URL, $this->endpoint);
        curl_setopt($this->curlObject, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->curlObject, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
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
