<?php

declare(strict_types=1);

namespace KalnaLab\Scrive;

use Illuminate\Http\Client\Factory;
use KalnaLab\Scrive\Exceptions\ScriveValidationException;
use KalnaLab\Scrive\Http\ScriveHttpClient;

/**
 * Client for the Scrive document API v2 (https://apidocs.scrive.com/).
 *
 * Wraps the subset of endpoints needed for the template-based signing flow:
 *
 *   - Create a document from a template ({@see newFromTemplate()}).
 *   - Populate signatory fields ({@see update()}).
 *   - Configure callbacks, redirects and title.
 *   - Attach a supporting PDF ({@see setAttachment()}).
 *   - Start signing and produce a signing URL ({@see getSignUrl()}).
 *   - Retrieve metadata and the final PDF.
 *
 * All public methods throw a {@see Exceptions\ScriveException}
 * (or one of its subclasses) on failure. Previous versions returned `null` for
 * several error paths; this is intentionally no longer the case.
 *
 * Authentication uses OAuth 1.0 PLAINTEXT as supported by Scrive's document
 * API. Credentials are read from `config/scrive.php` under
 * `document.<env>.{api-token,api-secret,access-token,access-secret}`.
 */
class ScriveDocument
{
    public const ROLE_AUTHOR = 'author';
    public const ROLE_VIEWER = 'viewer';
    public const ROLE_SIGNING_PARTY = 'signing_party';

    /** @deprecated Use the ROLE_* class constants instead. Kept for backwards compatibility. */
    public const ROLE = [
        'author' => self::ROLE_AUTHOR,
        'viewer' => self::ROLE_VIEWER,
        'signing_party' => self::ROLE_SIGNING_PARTY,
    ];

    private readonly string $env;
    private readonly string $baseUrl;
    private readonly ScriveHttpClient $client;
    private ?string $documentId = null;
    private ?\stdClass $documentJson = null;

    public function __construct(?Factory $http = null)
    {
        $this->env = config('scrive.document.env') === 'live' ? 'live' : 'test';
        $this->baseUrl = rtrim((string)config('scrive.document.' . $this->env . '.base-path'), '/') . '/api/v2/documents/';

        $this->client = new ScriveHttpClient(
            http: $http ?? app(Factory::class),
            baseUrl: $this->baseUrl,
            authHeaders: ['Authorization' => $this->buildOAuthHeader()],
        );
    }

    /**
     * Create a new document from a template and load its JSON representation
     * so subsequent methods can mutate it locally before sending `update`.
     */
    public function newFromTemplate(string $documentId): self
    {
        $payload = $this->client->postForm('newfromtemplate/' . $documentId, []);

        $this->loadDocument($payload);

        return $this;
    }

    /**
     * Populate signatory fields on the currently loaded document. Supports:
     *
     *   - `name` as `string` ("First Last") or `[first, last]` array.
     *   - `email`, `personal_number` (aliased as `cpr`),
     *     `company_number` (aliased as `cvr`).
     *   - Arbitrary custom fields keyed by the Scrive field name.
     *
     * By default the signing party is updated. Pass `$role = self::ROLE_AUTHOR`
     * to target the author, or a zero-based `$partyIndex` to target a specific
     * party slot.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(array $values = [], ?int $partyIndex = null, string $role = self::ROLE_SIGNING_PARTY): self
    {
        if ($values === []) {
            return $this;
        }

        $document = $this->requireDocument();
        [$firstName, $lastName, $fullName] = $this->parseName($values['name'] ?? null);
        $partyFound = false;

        foreach ($document->parties as $pIdx => $party) {
            if (!$this->matchesParty($party, $pIdx, $partyIndex, $role)) {
                continue;
            }

            $partyFound = true;

            foreach ($party->fields as $fIdx => $field) {
                $newValue = $this->resolveFieldValue($field, $values, $firstName, $lastName, $fullName);
                if ($newValue !== null) {
                    $document->parties[$pIdx]->fields[$fIdx]->value = $newValue;
                }
            }

            $document->parties[$pIdx]->delivery_method = 'api';
        }

        if (!$partyFound) {
            return $this;
        }

        $this->loadDocument(
            $this->client->postForm($this->documentId . '/update', [
                'document' => json_encode($document, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ])
        );

        return $this;
    }

    public function setCallbackUrl(string $url): self
    {
        $document = $this->requireDocument();
        $document->api_callback_url = $url;

        return $this->pushDocument($document);
    }

    /**
     * Set `api_callback_url` to a named route, automatically appending the
     * shared-secret signature expected by the `scrive.callback` middleware.
     *
     * The secret is read from `scrive.document.callback.secret`. If it is
     * empty, {@see ScriveValidationException} is thrown — calling this
     * method without a configured secret would produce a URL the middleware
     * can never accept, which is always a bug at the call site.
     *
     * @param  array<string, mixed>  $parameters  Extra route parameters
     */
    public function setVerifiedCallbackUrl(string $routeName, array $parameters = []): self
    {
        $secret = (string)config('scrive.document.callback.secret', '');
        if ($secret === '') {
            throw new ScriveValidationException(
                'Cannot build a verified callback URL: '
                . 'scrive.document.callback.secret is empty. '
                . 'Set the SCRIVE_CALLBACK_SECRET env var before calling setVerifiedCallbackUrl().',
            );
        }

        $url = route($routeName, $parameters + ['signature' => $secret], absolute: true);

        return $this->setCallbackUrl($url);
    }

    public function setSuccessRedirectUrl(string $url): self
    {
        $document = $this->requireDocument();
        foreach ($document->parties as $party) {
            if (($party->signatory_role ?? null) === self::ROLE_SIGNING_PARTY) {
                $party->sign_success_redirect_url = $url;
            }
        }

        return $this->pushDocument($document);
    }

    public function setRejectRedirectUrl(string $url): self
    {
        $document = $this->requireDocument();
        foreach ($document->parties as $party) {
            if (($party->signatory_role ?? null) === self::ROLE_SIGNING_PARTY) {
                $party->reject_redirect_url = $url;
            }
        }

        return $this->pushDocument($document);
    }

    public function setTitle(string $title): self
    {
        $document = $this->requireDocument();
        $document->title = $title;

        return $this->pushDocument($document);
    }

    /**
     * Upload an attachment file to the signing party.
     */
    public function setAttachment(string $attachmentFieldName, string $filePath): void
    {
        $document = $this->requireDocument();
        $signatoryId = null;

        foreach ($document->parties as $party) {
            if (($party->signatory_role ?? null) === self::ROLE_SIGNING_PARTY) {
                $signatoryId = $party->id ?? null;
                break;
            }
        }

        if ($signatoryId === null) {
            throw new ScriveValidationException('No signing party found on document when uploading attachment');
        }

        $this->client->postMultipart(
            path: $this->documentId . '/' . $signatoryId . '/setattachment',
            fieldName: 'attachment',
            filePath: $filePath,
            attachmentName: $attachmentFieldName,
        );
    }

    /**
     * Start the signing process and return the signing URL for the
     * signing party. Requires that `update()` has been called first.
     */
    public function getSignUrl(): string
    {
        $payload = $this->client->postForm($this->requireDocumentId() . '/start', []);
        $this->loadDocument($payload);

        foreach ($this->documentJson->parties as $party) {
            if (($party->signatory_role ?? null) === self::ROLE_SIGNING_PARTY) {
                if (!property_exists($party, 'api_delivery_url') || !is_string($party->api_delivery_url)) {
                    throw new ScriveValidationException('Signing party missing api_delivery_url after starting document');
                }

                return rtrim((string)config('scrive.document.' . $this->env . '.base-path'), '/') . $party->api_delivery_url;
            }
        }

        throw new ScriveValidationException('No signing party found on document after starting');
    }

    /**
     * Fetch the full document JSON from Scrive.
     */
    public function getData(string $documentId): \stdClass
    {
        return $this->client->getJson($documentId . '/get');
    }

    /**
     * Fetch the signed PDF as base64.
     */
    public function getBase64Pdf(string $documentId): string
    {
        return base64_encode($this->getPdf($documentId));
    }

    /**
     * Fetch the signed PDF as raw binary.
     */
    public function getPdf(string $documentId): string
    {
        return $this->client->getRaw($this->pdfPath($documentId));
    }

    /**
     * Build the URL used by Scrive to serve the signed PDF.
     */
    public function getPdfUrl(string $documentId, ?string $fileName = null): string
    {
        return $this->baseUrl . $this->pdfPath($documentId, $fileName);
    }

    public function documentId(): ?string
    {
        return $this->documentId;
    }

    public function document(): ?\stdClass
    {
        return $this->documentJson;
    }

    public function httpClient(): ScriveHttpClient
    {
        return $this->client;
    }

    private function pdfPath(string $documentId, ?string $fileName = null): string
    {
        $fileName ??= $documentId . '.pdf';

        return $documentId . '/files/main/' . $fileName;
    }

    private function pushDocument(\stdClass $document): self
    {
        $this->loadDocument(
            $this->client->postForm($this->requireDocumentId() . '/update', [
                'document' => json_encode($document, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ])
        );

        return $this;
    }

    private function loadDocument(\stdClass $payload): void
    {
        if (!property_exists($payload, 'id') || !is_string($payload->id)) {
            throw new ScriveValidationException('Scrive document response missing id');
        }
        if (!property_exists($payload, 'parties') || !is_array($payload->parties)) {
            throw new ScriveValidationException('Scrive document response missing parties');
        }

        $this->documentJson = $payload;
        $this->documentId = $payload->id;
    }

    private function requireDocument(): \stdClass
    {
        if ($this->documentJson === null) {
            throw new ScriveValidationException('No document loaded – call newFromTemplate() first');
        }

        return $this->documentJson;
    }

    private function requireDocumentId(): string
    {
        if ($this->documentId === null) {
            throw new ScriveValidationException('No document loaded – call newFromTemplate() first');
        }

        return $this->documentId;
    }

    /**
     * Decide whether a party matches the selectors passed to `update()`.
     */
    private function matchesParty(\stdClass $party, int $pIdx, ?int $partyIndex, string $role): bool
    {
        if ($partyIndex !== null) {
            return $partyIndex === $pIdx;
        }

        if ($role === self::ROLE_AUTHOR) {
            return ($party->is_author ?? false) === true;
        }

        return ($party->signatory_role ?? null) === $role;
    }

    /**
     * Parse an incoming name value into [first, last, full].
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function parseName(mixed $name): array
    {
        if ($name === null) {
            return ['', '', ''];
        }

        if (is_array($name)) {
            $full = implode(' ', array_map('strval', $name));
            if (count($name) === 2) {
                return [(string)$name[0], (string)$name[1], $full];
            }
            $parts = $name;
        } elseif (is_string($name)) {
            $full = $name;
            $parts = explode(' ', $name);
        } else {
            return ['', '', ''];
        }

        $last = (string)array_pop($parts);
        $first = implode(' ', $parts);

        return [$first, $last, $full];
    }

    /**
     * Compute the new value for a field based on the submitted values.
     * Returns `null` when the field should not be changed.
     *
     * @param  array<string, mixed>  $values
     */
    private function resolveFieldValue(
        \stdClass $field,
        array $values,
        string $firstName,
        string $lastName,
        string $fullName,
    ): ?string {
        $type = $field->type ?? null;
        $order = $field->order ?? null;

        if ($type === 'name' && $order === 1) {
            return $firstName;
        }
        if ($type === 'name' && $order === 2) {
            return $lastName;
        }
        if ($type === 'full_name') {
            return $fullName;
        }
        if ($type === 'email') {
            return (string)($values['email'] ?? '');
        }
        if ($type === 'personal_number') {
            return (string)($values['personal_number'] ?? $values['cpr'] ?? '');
        }
        if ($type === 'company_number') {
            return (string)($values['company_number'] ?? $values['cvr'] ?? '');
        }

        $customName = $field->name ?? null;
        if (is_string($customName) && array_key_exists($customName, $values)) {
            return (string)$values[$customName];
        }

        return null;
    }

    private function buildOAuthHeader(): string
    {
        $prefix = 'scrive.document.' . ($this->env === 'live' ? 'live' : 'test') . '.';

        return 'oauth_signature_method="PLAINTEXT",'
            . 'oauth_consumer_key="' . (string)config($prefix . 'api-token') . '",'
            . 'oauth_token="' . (string)config($prefix . 'access-token') . '",'
            . 'oauth_signature="' . (string)config($prefix . 'api-secret')
            . '&' . (string)config($prefix . 'access-secret') . '"';
    }
}
