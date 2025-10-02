<?php

namespace KalnaLab\Scrive;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ScriveDocument
{
    public string $documentId;
    public object $documentJson;
    public string $httpMethod = 'POST';
    public string $env = 'live';
    public array $headers = [];
    public array $body = [];
    public \CurlHandle $curlObject;
    public string $baseEndpoint;
    public string $endpoint;

    public function __construct()
    {
        $this->env = config('scrive.env') == 'live' ? 'live' : 'test';
        $this->baseEndpoint = rtrim(config('scrive.document.' . $this->env . '.base-path'), '/') . '/api/v2/documents/';
        $this->headers = [
            'Authorization' => 'oauth_signature_method="PLAINTEXT",' .
                'oauth_consumer_key="' . config('scrive.document.' . $this->env . '.api-token') . '",' .
                'oauth_token="' . config('scrive.document.' . $this->env . '.access-token') . '",' .
                'oauth_signature="' . config('scrive.document.' . $this->env . '.api-secret') . '&' . config('scrive.document.' . $this->env . '.access-secret') . '"',
        ];
    }

    /**
     * @throws \Exception
     */
    public function newFromTemplate(string $documentId): string
    {
        $this->endpoint = $this->baseEndpoint . 'newfromtemplate/' . $documentId;
        $this->httpMethod = 'POST';

        $payload = $this->executeCall();

        if (is_object($payload) && property_exists($payload, 'id')) {
            $this->documentJson = $payload;
            $this->documentId = $this->documentJson->id;
        }

        return $this->documentId;
    }

    public function update(array|string $name, array $values = []): void
    {
        $this->endpoint = $this->baseEndpoint . '/' . $this->documentId . '/update';
        $this->httpMethod = 'POST';

        $firstName = '';
        $lastName = '';
        $fullName = $name;
        if (is_array($name)) {
            $fullName = implode(' ', $name);
        }
        if (is_string($name)) {
            $name = explode(' ', $name);
        }
        if (is_array($name)) {
            $firstName = Arr::first($name);
            $lastName = Arr::last($name);
        }

        $documentJson = $this->documentJson;

        foreach ($documentJson->parties as $pIdx => $party) {
            if ($party->signatory_role == 'signing_party') {
                foreach ($party->fields as $fIdx => $field) {
                    if ($field->type == 'name' && $field->order == 1) {
                        $documentJson->parties[$pIdx]->fields[$fIdx]->value = $firstName;

                        continue;
                    }
                    if ($field->type == 'name' && $field->order == 2) {
                        $documentJson->parties[$pIdx]->fields[$fIdx]->value = $lastName;

                        continue;
                    }
                    if ($field->type == 'full_name') {
                        $documentJson->parties[$pIdx]->fields[$fIdx]->value = $fullName;

                        continue;
                    }
                    if ($field->type == 'email') {
                        $documentJson->parties[$pIdx]->fields[$fIdx]->value = $values['email'] ?? '';

                        continue;
                    }
                    if ($field->type == 'personal_number') {
                        $documentJson->parties[$pIdx]->fields[$fIdx]->value = $values['personal_number'] ?? $values['cpr'] ?? '';

                        continue;
                    }
                    if ($field->type == 'company_number') {
                        $documentJson->parties[$pIdx]->fields[$fIdx]->value = $values['company_number'] ?? $values['cvr'] ?? '';

                        continue;
                    }
                    if (array_key_exists($field->name, $values)) {
                        $documentJson->parties[$pIdx]->fields[$fIdx]->value = $values[$field->name];
                    }
                }
                $documentJson->parties[$pIdx]->delivery_method = 'api';
            }
        }

        $documentJson->api_callback_url = ''; //TODO: set callback url
        $this->body['document'] = json_encode($documentJson);

        $payload = $this->executeCall();

        if (is_object($payload) && property_exists($payload, 'id')) {
            $this->documentJson = $payload;
        }
    }

    public function getSignUrl(): ?string
    {
        $this->endpoint = $this->baseEndpoint . '/' . $this->documentId . '/start';
        $this->httpMethod = 'POST';
        $this->body['strict_validations'] = true;

        try {
            $payload = $this->executeCall();
        } catch (\Exception $e) {
            return null;
        }

        if (is_object($payload) && property_exists($payload, 'id')) {
            $this->documentJson = $payload;
        }

        $documentJson = $this->documentJson;

        foreach ($documentJson->parties as $pIdx => $party) {
            if ($party->signatory_role == 'signing_party') {
                Log::info(__METHOD__ . ' (' . __LINE__ . ')' . ' party:' . "\n" . json_encode($party, JSON_PRETTY_PRINT));
                return rtrim(config('scrive.document.' . $this->env . '.base-path'), '/') . $party->api_delivery_url;
            }
        }

        return null;
    }

    public function executeCall(): array|object
    {
        $postFields = http_build_query($this->body);
        $headers = [];
        foreach ($this->headers as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        $curlObject = curl_init();
        curl_setopt($curlObject, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curlObject, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curlObject, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curlObject, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlObject, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curlObject, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curlObject, CURLOPT_TIMEOUT, 30);
        curl_setopt($curlObject, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curlObject, CURLOPT_URL, $this->endpoint);
        if ($this->httpMethod === 'GET') {
            curl_setopt($curlObject, CURLOPT_CUSTOMREQUEST, $this->httpMethod);
        } elseif (in_array($this->httpMethod, ['POST', 'PATCH'])) {
            curl_setopt($curlObject, CURLOPT_CUSTOMREQUEST, $this->httpMethod);
            curl_setopt($curlObject, CURLOPT_POSTFIELDS, $postFields);
        }
        curl_setopt($curlObject, CURLOPT_VERBOSE, true);

        $response = curl_exec($curlObject);
        if ($response === false) {
            curl_close($curlObject);
            Log::error(__METHOD__ . ' (' . __LINE__ . ')' . "\n" . 'cURL Error: ' . curl_error($curlObject));
            throw new \Exception('cURL Error: ' . curl_error($curlObject));
        }
        $header_size = curl_getinfo($curlObject, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        $httpStatus = curl_getinfo($curlObject, CURLINFO_RESPONSE_CODE) ?? null;
        if ($httpStatus > 299) {
            $curl_error = curl_error($curlObject);

            if ($curl_error) {
                curl_close($curlObject);
                Log::error(__METHOD__ . ' (' . __LINE__ . ')' . "\n" . 'cURL Error: ' . curl_error($curlObject));
                throw new \Exception('cURL Error: ' . curl_error($curlObject));
            } elseif ($body && $response = json_decode($body)) {
                curl_close($curlObject);
                Log::error(__METHOD__ . ' (' . __LINE__ . ')' . "\n" . 'Body Error: ' . $body);
                throw new \Exception('Body Error: ' . $body);
            } else {
                curl_close($curlObject);
                // Find WWW-Authenticate header
                preg_match('/WWW-Authenticate: (.+)/i', $headers, $matches);
                if (isset($matches[1])) {
                    Log::error(__METHOD__ . ' (' . __LINE__ . ')' . "\n" . 'WWW-Authenticate: ' . $matches[1]);
                    throw new \Exception('WWW-Authenticate: ' . $matches[1]);
                }
                Log::error(__METHOD__ . ' (' . __LINE__ . ')' . "\n" . 'Empty response ' . $body);
                throw new \Exception('Empty response ' . $body);
            }
        }

        if (empty($response)) {
            curl_close($curlObject);
            throw new \Exception('curl_error: ' . curl_error($curlObject) . ', curl_errno: ' . curl_errno($curlObject));
        }
        curl_close($curlObject);

        return json_decode($response);
    }
}
