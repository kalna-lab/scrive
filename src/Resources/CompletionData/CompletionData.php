<?php

namespace KalnaLab\Scrive\Resources\CompletionData;

abstract class CompletionData
{
    protected string $httpMethod = 'POST';
    protected string $env = 'live';
    protected array $headers = [];
    protected array $body = [];
    protected \CurlHandle $curlObject;
    protected string $endpoint;
    public string $providerName;
    public ?string $transactionId = null;

    protected function instantiateCurl(): void
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

    protected function setHeaders(array $body = []): void
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

    protected function executeCall(): array|object
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
