<?php

declare(strict_types=1);

namespace KalnaLab\Scrive\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use KalnaLab\Scrive\Events\ScriveDocumentCallbackReceived;
use KalnaLab\Scrive\Exceptions\ScriveValidationException;
use KalnaLab\Scrive\ScriveDocument;

/**
 * Form request for inbound Scrive document callbacks.
 *
 * Assumes the `scrive.callback` middleware has already verified the
 * shared-secret signature. After Laravel validates the body shape, this
 * request resolves the authoritative document — either by fetching it
 * from the Scrive API (default) or by decoding `document_json` from the
 * body when `scrive.document.callback.verify_against_api` is false.
 *
 * Controllers type-hint this request and retrieve the verified document
 * via {@see document()}.
 *
 * The {@see ScriveDocumentCallbackReceived} event is dispatched once the
 * document has been resolved, so passive listeners (auditing, logging,
 * side-effects) can react without touching the controller.
 */
class ScriveCallbackRequest extends FormRequest
{
    private ?\stdClass $resolvedDocument = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'document_id' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('document_id') && $this->has('documentid')) {
            $this->merge(['document_id' => (string)$this->input('documentid')]);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ScriveValidationException(
            'Invalid Scrive callback payload: ' . $validator->errors()->first(),
        );
    }

    protected function passedValidation(): void
    {
        $documentId = (string)$this->validated('document_id');
        $this->resolvedDocument = $this->resolveDocument($documentId);

        ScriveDocumentCallbackReceived::dispatch($this->resolvedDocument, $this);
    }

    public function document(): \stdClass
    {
        if ($this->resolvedDocument === null) {
            throw new ScriveValidationException(
                'Scrive callback document has not been resolved yet. '
                . 'Did validation run?',
            );
        }

        return $this->resolvedDocument;
    }

    public function documentId(): string
    {
        return (string)$this->validated('document_id');
    }

    private function resolveDocument(string $documentId): \stdClass
    {
        if ((bool)config('scrive.document.callback.verify_against_api', true)) {
            return (new ScriveDocument)->getData($documentId);
        }

        $raw = (string)$this->input('document_json', '');
        if ($raw === '') {
            throw new ScriveValidationException(
                'Scrive callback body is missing document_json and '
                . 'verify_against_api is disabled.',
            );
        }

        try {
            $decoded = json_decode($raw, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ScriveValidationException(
                'Scrive callback document_json is not valid JSON: ' . $e->getMessage(),
                previous: $e,
            );
        }

        if (!$decoded instanceof \stdClass) {
            throw new ScriveValidationException(
                'Scrive callback document_json did not decode to an object.',
            );
        }

        return $decoded;
    }
}
