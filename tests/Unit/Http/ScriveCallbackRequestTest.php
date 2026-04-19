<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use KalnaLab\Scrive\Events\ScriveDocumentCallbackReceived;
use KalnaLab\Scrive\Exceptions\ScriveValidationException;
use KalnaLab\Scrive\Http\Requests\ScriveCallbackRequest;

beforeEach(function () {
    Http::preventStrayRequests();

    // A bare route that resolves the FormRequest but does not assert on
    // the result — the FormRequest's lifecycle hooks are what we're testing.
    Route::post('/_test/bare-callback', function (ScriveCallbackRequest $request) {
        return response()->noContent();
    })->name('test.bare.callback');
});

it('rejects a callback payload without a document_id', function () {
    $response = $this->postJson('/_test/bare-callback', []);

    // failedValidation() throws ScriveValidationException; our base
    // ScriveException does not self-render, so Laravel's handler
    // reports it as a 500. That is acceptable for unit-level coverage;
    // the contract under test is that the exception is raised at all.
    $response->assertStatus(500);
});

it('throws ScriveValidationException when document_id is missing (isolated)', function () {
    $this->withoutExceptionHandling();

    expect(fn () => $this->postJson('/_test/bare-callback', []))
        ->toThrow(ScriveValidationException::class);
});

it('throws when verify_against_api=false and document_json is missing', function () {
    config(['scrive.document.callback.verify_against_api' => false]);

    $this->withoutExceptionHandling();

    expect(fn () => $this->postJson('/_test/bare-callback', ['document_id' => 'doc-1']))
        ->toThrow(ScriveValidationException::class, 'missing document_json');
});

it('throws when verify_against_api=false and document_json is malformed', function () {
    config(['scrive.document.callback.verify_against_api' => false]);

    $this->withoutExceptionHandling();

    expect(fn () => $this->postJson('/_test/bare-callback', [
        'document_id' => 'doc-1',
        'document_json' => '{not-valid-json',
    ]))->toThrow(ScriveValidationException::class, 'not valid JSON');
});

it('dispatches ScriveDocumentCallbackReceived with the resolved document', function () {
    Event::fake();

    Http::fake([
        'docs.test.scrive.example/api/v2/documents/doc-evt/get' => Http::response(
            scrive_document('doc-evt'),
        ),
    ]);

    $this->postJson('/_test/bare-callback', ['document_id' => 'doc-evt']);

    Event::assertDispatched(
        ScriveDocumentCallbackReceived::class,
        fn (ScriveDocumentCallbackReceived $e) => $e->document->id === 'doc-evt'
            && $e->request->input('document_id') === 'doc-evt',
    );
});
