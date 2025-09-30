<?php

namespace KalnaLab\Scrive;

use Illuminate\Support\Arr;

class ScriveDocument
{
    public string $documentId;
    public object $documentJson;
    public string $httpMethod = 'POST';
    public string $env = 'live';
    public array $headers = [];
    public array $body = [];
    public \CurlHandle $curlObject;
    public string $endpoint;

    public function __construct()
    {
        $this->env = config('scrive.env') == 'live' ? 'live' : 'test';
        $this->endpoint = config('scrive.document.' . $this->env . '.base-path');
    }

    /**
     * @throws \Exception
     */
    public function newFromTemplate(string $documentId): string
    {
        $this->endpoint .= 'newfromtemplate/' . $documentId;
        $this->httpMethod = 'POST';

        $this->instantiateCurl();

        $payload = $this->executeCall();

        if (is_object($payload) && property_exists($payload, 'id')) {
            $this->documentJson = $payload;
            $this->documentId = $this->documentJson->id;
        }

        return $this->documentId;
    }

    public function update(array|string $name, array $values = []): void
    {
        $this->endpoint .= '/' . $this->documentId . '/update';
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

        $this->instantiateCurl();

        $payload = $this->executeCall();

        if (is_object($payload) && property_exists($payload, 'id')) {
            $this->documentJson = $payload;
        }
    }

    public function instantiateCurl(): void
    {
        $this->headers = [
            'Authorization' => 'oauth_signature_method="PLAINTEXT",' .
                'oauth_consumer_key="' . config('scrive.document.' . $this->env . '.api-token') . '",' .
                'oauth_token="' . config('scrive.document.' . $this->env . '.access-token') . '",' .
                'oauth_signature="' . config('scrive.document.' . $this->env . '.api-secret') . '&' . config('scrive.document.' . $this->env . '.access-secret') . '"',
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

    public function setHeaders(): void
    {
        $headers = [];
        foreach ($this->headers as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        curl_setopt($this->curlObject, CURLOPT_HTTPHEADER, $headers);
    }

    public function executeCall(): array|object
    {
        $postFields = http_build_query($this->body);
        $this->setHeaders();
        curl_setopt($this->curlObject, CURLOPT_URL, $this->endpoint);
        if ($this->httpMethod === 'GET') {
            curl_setopt($this->curlObject, CURLOPT_CUSTOMREQUEST, $this->httpMethod);
        } elseif (in_array($this->httpMethod, ['POST', 'PATCH'])) {
            curl_setopt($this->curlObject, CURLOPT_CUSTOMREQUEST, $this->httpMethod);
            curl_setopt($this->curlObject, CURLOPT_POSTFIELDS, $postFields);
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
