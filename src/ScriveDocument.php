<?php

namespace KalnaLab\Scrive;

use Illuminate\Support\Facades\Log;

class ScriveDocument
{
    public const ROLE = [
        'author' => 'author',
        'viewer' => 'viewer',
        'signing_party' => 'signing_party',
    ];

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
        $this->env = config('scrive.document.env') == 'live' ? 'live' : 'test';
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
    public function newFromTemplate(string $documentId): self
    {
        $this->endpoint = $this->baseEndpoint . 'newfromtemplate/' . $documentId;
        $this->httpMethod = 'POST';

        $payload = $this->executeCall();

        if (is_object($payload) && property_exists($payload, 'id')) {
            $this->documentJson = $payload;
            $this->documentId = $this->documentJson->id;
        }

        return $this;
    }

    public function update(array $values = [], ?int $partyIndex = null, string $role = self::ROLE['signing_party']): self
    {
        if (empty($values)) {
            return $this;
        }
        $this->endpoint = $this->baseEndpoint . $this->documentId . '/update';
        $this->httpMethod = 'POST';

        $firstName = '';
        $lastName = '';
        $fullName = '';
        if (array_key_exists('name', $values)) {
            $name = $values['name'];
            $fullName = $name;
            if (is_array($name)) {
                $fullName = implode(' ', $name);
            }
            if (is_array($name) && count($name) == 2) {
                $firstName = $name[0];
                $lastName = $name[1];
            } else {
                if (is_string($name)) {
                    $name = explode(' ', $name);
                }
                if (is_array($name)) {
                    $lastName = array_pop($name);
                    $firstName = implode(' ', $name);
                }
            }
        }

        $documentJson = $this->documentJson;

        $partyFound = false;
        foreach ($documentJson->parties as $pIdx => $party) {
            if (($role == self::ROLE['author'] && $party->is_author) ||
                (is_null($partyIndex) && $party->signatory_role == $role) ||
                (!is_null($partyIndex) && $partyIndex == $pIdx)) {
                $partyFound = true;
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
                        $documentJson->parties[$pIdx]->fields[$fIdx]->value = (string)($values['personal_number'] ?? $values['cpr'] ?? '');

                        continue;
                    }
                    if ($field->type == 'company_number') {
                        $documentJson->parties[$pIdx]->fields[$fIdx]->value = (string)($values['company_number'] ?? $values['cvr'] ?? '');

                        continue;
                    }
                    if (property_exists($field, 'name') && array_key_exists($field->name, $values)) {
                        if ($field->type == 'text') {
                            $documentJson->parties[$pIdx]->fields[$fIdx]->value = (string)$values[$field->name];
                        } elseif ($field->type == 'multi_line_text') {
                            $documentJson->parties[$pIdx]->fields[$fIdx]->value = (string)$values[$field->name];
                        } else {
                            $documentJson->parties[$pIdx]->fields[$fIdx]->value = $values[$field->name];
                        }
                    }
                }
                $documentJson->parties[$pIdx]->delivery_method = 'api';
            }
        }
        if (!$partyFound) {
            return $this;
        }

        $this->body['document'] = json_encode($documentJson);

        $payload = $this->executeCall();

        if (is_object($payload) && property_exists($payload, 'id')) {
            $this->documentJson = $payload;
        }

        return $this;
    }

    public function setCallbackUrl(string $url): self
    {
        $this->endpoint = $this->baseEndpoint . $this->documentId . '/update';
        $this->httpMethod = 'POST';

        $documentJson = $this->documentJson;
        $documentJson->api_callback_url = $url;
        $this->body['document'] = json_encode($documentJson);

        $payload = $this->executeCall();

        if (is_object($payload) && property_exists($payload, 'id')) {
            $this->documentJson = $payload;
        }

        return $this;
    }

    public function setSuccessRedirectUrl(string $url): self
    {
        $this->endpoint = $this->baseEndpoint . $this->documentId . '/update';
        $this->httpMethod = 'POST';

        $documentJson = $this->documentJson;

        foreach ($documentJson->parties as $pIdx => $party) {
            if ($party->signatory_role == 'signing_party') {
                $party->sign_success_redirect_url = $url;
            }
        }
        $this->body['document'] = json_encode($documentJson);

        $payload = $this->executeCall();

        if (is_object($payload) && property_exists($payload, 'id')) {
            $this->documentJson = $payload;
        }

        return $this;
    }

    public function setRejectRedirectUrl(string $url): self
    {
        $this->endpoint = $this->baseEndpoint . $this->documentId . '/update';
        $this->httpMethod = 'POST';

        $documentJson = $this->documentJson;

        foreach ($documentJson->parties as $pIdx => $party) {
            if ($party->signatory_role == 'signing_party') {
                $party->reject_redirect_url = $url;
            }
        }
        $this->body['document'] = json_encode($documentJson);

        $payload = $this->executeCall();

        if (is_object($payload) && property_exists($payload, 'id')) {
            $this->documentJson = $payload;
        }

        return $this;
    }

    public function setTitle(string $title): self
    {
        $this->endpoint = $this->baseEndpoint . $this->documentId . '/update';
        $this->httpMethod = 'POST';

        $documentJson = $this->documentJson;
        $documentJson->title = $title;
        $this->body['document'] = json_encode($documentJson);

        $payload = $this->executeCall();

        if (is_object($payload) && property_exists($payload, 'id')) {
            $this->documentJson = $payload;
        }

        return $this;
    }

    public function setAttachment(string $attachmentFieldName, $filePath): void
    {
        $documentJson = $this->documentJson;
        $signatory_id = null;

        foreach ($documentJson->parties as $pIdx => $party) {
            if ($party->signatory_role == 'signing_party') {
                $signatory_id = $party->id;
            }
        }
        $this->endpoint = $this->baseEndpoint . $this->documentId . '/' . $signatory_id . '/setattachment';
        $this->httpMethod = 'POST';

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
        curl_setopt($curlObject, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curlObject, CURLOPT_POSTFIELDS, ['name' => $attachmentFieldName, 'attachment' => new \CURLFile($filePath)]);
        curl_setopt($curlObject, CURLOPT_VERBOSE, true);

        $response = curl_exec($curlObject);
        if ($response === false) {
            curl_close($curlObject);
            Log::error(__METHOD__ . ' (' . __LINE__ . ')' . "\n" . 'cURL Error: ' . curl_error($curlObject));
            throw new \Exception('cURL Error: ' . curl_error($curlObject));
        }
    }

    public function getSignUrl(): ?string
    {
        $this->endpoint = $this->baseEndpoint . $this->documentId . '/start';
        $this->httpMethod = 'POST';

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
                return rtrim(config('scrive.document.' . $this->env . '.base-path'), '/') . $party->api_delivery_url;
            }
        }

        return null;
    }

    public function getData(string $documentId): ?object
    {
        $this->endpoint = $this->baseEndpoint . $documentId . '/get';
        $this->httpMethod = 'GET';

        try {
            $payload = $this->executeCall();
        } catch (\Exception $e) {
            return null;
        }

        if (is_object($payload) && property_exists($payload, 'id')) {
            return $payload;
        }

        return null;
    }

    public function getBase64Pdf(string $documentId): ?string
    {
        $this->endpoint = $this->getPdfUrl($documentId);
        $this->httpMethod = 'GET';

        try {
            $binaryPdf = $this->executeCall(expectBinary: true);
        } catch (\Exception $e) {
            return null;
        }

        return base64_encode($binaryPdf);
    }

    public function getPdfUrl(string $documentId, ?string $fileName = null): string
    {
        $fileName ??= $documentId . '.pdf';

        return $this->baseEndpoint . $documentId . '/files/main/' . $fileName;
    }

    private function executeCall(bool $expectBinary = false): array|object|null
    {
        $postFields = http_build_query($this->body);
        $headers = [];
        foreach ($this->headers as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        $curlObject = curl_init();
        curl_setopt_array($curlObject, [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_URL => $this->endpoint,
            CURLOPT_TIMEOUT => 30,
        ]);
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

        $headerSize  = curl_getinfo($curlObject, CURLINFO_HEADER_SIZE);
        $rawHeaders  = substr($response, 0, $headerSize);
        $body        = substr($response, $headerSize);
        $statusCode  = curl_getinfo($curlObject, CURLINFO_RESPONSE_CODE);
        $contentType = curl_getinfo($curlObject, CURLINFO_CONTENT_TYPE);

        curl_close($curlObject);

        if ($statusCode >= 300) {
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
                preg_match('/WWW-Authenticate: (.+)/i', $rawHeaders, $matches);
                if (isset($matches[1])) {
                    Log::error(__METHOD__ . ' (' . __LINE__ . ')' . "\n" . 'WWW-Authenticate: ' . $matches[1]);
                    throw new \Exception('WWW-Authenticate: ' . $matches[1]);
                }
                Log::error(__METHOD__ . ' (' . __LINE__ . ')' . "\n" . 'Empty response ' . $body);
                throw new \Exception($statusCode . ': Empty response ' . $body);
            }
        }

        if (empty($response)) {
            curl_close($curlObject);
            throw new \Exception('curl_error: ' . curl_error($curlObject) . ', curl_errno: ' . curl_errno($curlObject));
        }
        // 👇 Hvis vi forventer binær data (PDF)
        if ($expectBinary || !str_contains((string)$contentType, 'application/json')) {
            return $body; // raw binary
        }

        return json_decode($response);
    }
}
