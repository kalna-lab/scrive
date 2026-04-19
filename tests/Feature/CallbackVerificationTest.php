<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use KalnaLab\Scrive\Events\ScriveDocumentCallbackReceived;
use KalnaLab\Scrive\Http\Requests\ScriveCallbackRequest;

beforeEach(function () {
    Http::preventStrayRequests();

    Route::post('/_test/scrive-callback', function (ScriveCallbackRequest $request) {
        return response()->json([
            'document_id' => $request->documentId(),
            'title' => $request->document()->title,
        ]);
    })
        ->middleware('scrive.callback')
        ->name('test.scrive.callback');
});

it('rejects callbacks with a missing signature', function () {
    Event::fake();

    $response = $this->postJson('/_test/scrive-callback', [
        'document_id' => 'doc-42',
    ]);

    $response->assertStatus(401);
    Event::assertNotDispatched(ScriveDocumentCallbackReceived::class);
});

it('rejects callbacks with a wrong signature', function () {
    Event::fake();

    $response = $this->postJson('/_test/scrive-callback?signature=not-the-secret', [
        'document_id' => 'doc-42',
    ]);

    $response->assertStatus(401);
    Event::assertNotDispatched(ScriveDocumentCallbackReceived::class);
});

it('fails closed when no secret is configured', function () {
    config(['scrive.document.callback.secret' => '']);

    $response = $this->postJson('/_test/scrive-callback?signature=anything', [
        'document_id' => 'doc-42',
    ]);

    $response->assertStatus(401);
});

it('accepts callbacks with a valid signature and fetches the authoritative document', function () {
    Event::fake();

    Http::fake([
        'docs.test.scrive.example/api/v2/documents/doc-42/get' => Http::response(
            scrive_document('doc-42'),
        ),
    ]);

    $response = $this->postJson('/_test/scrive-callback?signature=test-callback-secret', [
        'document_id' => 'doc-42',
    ]);

    $response->assertOk()
        ->assertJson([
            'document_id' => 'doc-42',
            'title' => 'Test document',
        ]);

    Http::assertSent(fn (HttpClientRequest $r) => str_contains($r->url(), '/documents/doc-42/get'));

    Event::assertDispatched(
        ScriveDocumentCallbackReceived::class,
        fn (ScriveDocumentCallbackReceived $e) => $e->document->id === 'doc-42',
    );
});

it('skips the api fetch when verify_against_api is false', function () {
    config(['scrive.document.callback.verify_against_api' => false]);

    $response = $this->postJson('/_test/scrive-callback?signature=test-callback-secret', [
        'document_id' => 'doc-42',
        'document_json' => json_encode(scrive_document('doc-42')),
    ]);

    $response->assertOk()
        ->assertJson([
            'document_id' => 'doc-42',
            'title' => 'Test document',
        ]);

    // Http::preventStrayRequests() would have failed if any request were made.
});

it('accepts documentid as an alias for document_id', function () {
    Http::fake([
        'docs.test.scrive.example/api/v2/documents/doc-99/get' => Http::response(
            scrive_document('doc-99'),
        ),
    ]);

    $response = $this->postJson('/_test/scrive-callback?signature=test-callback-secret', [
        'documentid' => 'doc-99',
    ]);

    $response->assertOk()->assertJson(['document_id' => 'doc-99']);
});
