<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use KalnaLab\Scrive\Exceptions\ScriveApiException;
use KalnaLab\Scrive\Exceptions\ScriveValidationException;
use KalnaLab\Scrive\ScriveDocument;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('creates a document from a template and stores the id', function () {
    Http::fake([
        'docs.test.scrive.example/api/v2/documents/newfromtemplate/tpl-1' => Http::response(scrive_document('doc-42')),
    ]);

    $scrive = (new ScriveDocument)->newFromTemplate('tpl-1');

    expect($scrive->documentId())->toBe('doc-42');

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && str_starts_with($request->header('Authorization')[0], 'oauth_signature_method="PLAINTEXT"')
            && str_contains($request->header('Authorization')[0], 'oauth_consumer_key="api-token"')
            && str_contains($request->header('Authorization')[0], 'oauth_signature="api-secret&access-secret"');
    });
});

it('updates signing-party fields using a string name', function () {
    Http::fakeSequence()
        ->push(scrive_document('doc-1'))
        ->push(scrive_document('doc-1'));

    $scrive = (new ScriveDocument)
        ->newFromTemplate('tpl-1')
        ->update([
            'name' => 'Jane Ann Doe',
            'email' => 'jane@example.com',
            'cpr' => '010180-1234',
        ]);

    $updateRequest = Http::recorded()[1][0];
    parse_str($updateRequest->body(), $parsed);
    $document = json_decode($parsed['document']);

    expect($scrive->documentId())->toBe('doc-1');

    $fields = collect($document->parties[0]->fields);
    expect($fields->firstWhere('type', 'name')->value)->toBe('Jane Ann')
        ->and($fields->where('type', 'name')->values()->get(1)->value)->toBe('Doe')
        ->and($fields->firstWhere('type', 'email')->value)->toBe('jane@example.com')
        ->and($fields->firstWhere('type', 'personal_number')->value)->toBe('010180-1234')
        ->and($document->parties[0]->delivery_method)->toBe('api');
});

it('updates using an explicit [first, last] name array', function () {
    Http::fakeSequence()
        ->push(scrive_document('doc-2'))
        ->push(scrive_document('doc-2'));

    (new ScriveDocument)
        ->newFromTemplate('tpl-2')
        ->update(['name' => ['Erik', 'Hansen']]);

    $updateRequest = Http::recorded()[1][0];
    parse_str($updateRequest->body(), $parsed);
    $document = json_decode($parsed['document']);
    $names = collect($document->parties[0]->fields)->where('type', 'name')->values();

    expect($names->get(0)->value)->toBe('Erik')
        ->and($names->get(1)->value)->toBe('Hansen');
});

it('populates a full_name field when present', function () {
    $parties = [scrive_signing_party('party-1', [
        ['type' => 'full_name', 'order' => 1, 'value' => ''],
    ])];

    Http::fakeSequence()
        ->push(scrive_document('doc-3', $parties))
        ->push(scrive_document('doc-3', $parties));

    (new ScriveDocument)
        ->newFromTemplate('tpl-3')
        ->update(['name' => 'Jane Doe']);

    $updateRequest = Http::recorded()[1][0];
    parse_str($updateRequest->body(), $parsed);
    $document = json_decode($parsed['document']);

    expect($document->parties[0]->fields[0]->value)->toBe('Jane Doe');
});

it('sets a company number on the matching field', function () {
    $parties = [scrive_signing_party('party-1', [
        ['type' => 'company_number', 'order' => 1, 'value' => ''],
    ])];

    Http::fakeSequence()
        ->push(scrive_document('doc-4', $parties))
        ->push(scrive_document('doc-4', $parties));

    (new ScriveDocument)
        ->newFromTemplate('tpl-4')
        ->update(['cvr' => '12345678']);

    $updateRequest = Http::recorded()[1][0];
    parse_str($updateRequest->body(), $parsed);
    $document = json_decode($parsed['document']);

    expect($document->parties[0]->fields[0]->value)->toBe('12345678');
});

it('populates custom text fields by name', function () {
    $parties = [scrive_signing_party('party-1', [
        (object)['type' => 'text', 'name' => 'invoice_no', 'order' => 1, 'value' => ''],
    ])];

    Http::fakeSequence()
        ->push(scrive_document('doc-5', $parties))
        ->push(scrive_document('doc-5', $parties));

    (new ScriveDocument)
        ->newFromTemplate('tpl-5')
        ->update(['invoice_no' => 'INV-42']);

    $updateRequest = Http::recorded()[1][0];
    parse_str($updateRequest->body(), $parsed);
    $document = json_decode($parsed['document']);

    expect($document->parties[0]->fields[0]->value)->toBe('INV-42');
});

it('is a no-op when update() is called with empty values', function () {
    Http::fake([
        '*' => Http::response(scrive_document('doc-6')),
    ]);

    $scrive = (new ScriveDocument)->newFromTemplate('tpl-6');
    $scrive->update([]);

    Http::assertSentCount(1); // newFromTemplate only, no update call
});

it('requires a loaded document before update()', function () {
    (new ScriveDocument)->update(['name' => 'Jane']);
})->throws(ScriveValidationException::class, 'No document loaded');

it('applies setTitle through the update endpoint', function () {
    Http::fakeSequence()
        ->push(scrive_document('doc-7'))
        ->push(scrive_document('doc-7'));

    (new ScriveDocument)
        ->newFromTemplate('tpl-7')
        ->setTitle('New title');

    $request = Http::recorded()[1][0];
    parse_str($request->body(), $parsed);
    $document = json_decode($parsed['document']);

    expect($request->url())->toContain('/documents/doc-7/update')
        ->and($document->title)->toBe('New title');
});

it('sets callback URL on the document', function () {
    Http::fakeSequence()
        ->push(scrive_document('doc-8'))
        ->push(scrive_document('doc-8'));

    (new ScriveDocument)
        ->newFromTemplate('tpl-8')
        ->setCallbackUrl('https://app.test/hooks/scrive');

    $request = Http::recorded()[1][0];
    parse_str($request->body(), $parsed);
    $document = json_decode($parsed['document']);

    expect($document->api_callback_url)->toBe('https://app.test/hooks/scrive');
});

it('sets success and reject redirect URLs only on the signing party', function () {
    $parties = [
        ['id' => 'author', 'signatory_role' => 'author', 'is_author' => true, 'fields' => []],
        scrive_signing_party('signer'),
    ];

    Http::fakeSequence()
        ->push(scrive_document('doc-9', $parties))
        ->push(scrive_document('doc-9', $parties))
        ->push(scrive_document('doc-9', $parties));

    (new ScriveDocument)
        ->newFromTemplate('tpl-9')
        ->setSuccessRedirectUrl('https://app.test/success')
        ->setRejectRedirectUrl('https://app.test/rejected');

    $successRequest = Http::recorded()[1][0];
    parse_str($successRequest->body(), $parsed);
    $document = json_decode($parsed['document']);

    $signer = collect($document->parties)->firstWhere('signatory_role', 'signing_party');
    $author = collect($document->parties)->firstWhere('signatory_role', 'author');

    expect($signer->sign_success_redirect_url)->toBe('https://app.test/success')
        ->and(property_exists($author, 'sign_success_redirect_url'))->toBeFalse();
});

it('getSignUrl returns the full URL combining base-path and api_delivery_url', function () {
    Http::fakeSequence()
        ->push(scrive_document('doc-10'))
        ->push(scrive_document('doc-10'));

    $url = (new ScriveDocument)
        ->newFromTemplate('tpl-10')
        ->getSignUrl();

    expect($url)->toBe('https://docs.test.scrive.example/d/party-1/access');
});

it('getSignUrl throws when the signing party has no api_delivery_url', function () {
    $partyNoUrl = scrive_signing_party('p-1');
    unset($partyNoUrl['api_delivery_url']);

    Http::fakeSequence()
        ->push(scrive_document('doc-11', [$partyNoUrl]))
        ->push(scrive_document('doc-11', [$partyNoUrl]));

    (new ScriveDocument)->newFromTemplate('tpl-11')->getSignUrl();
})->throws(ScriveValidationException::class, 'missing api_delivery_url');

it('getData returns the document as an object', function () {
    Http::fake([
        'docs.test.scrive.example/api/v2/documents/doc-12/get' => Http::response(scrive_document('doc-12')),
    ]);

    $data = (new ScriveDocument)->getData('doc-12');

    expect($data)->toBeObject()->and($data->id)->toBe('doc-12');
});

it('getPdf returns the raw response body', function () {
    Http::fake([
        'docs.test.scrive.example/api/v2/documents/doc-13/files/main/doc-13.pdf' => Http::response('%PDF-binary', 200, ['Content-Type' => 'application/pdf']),
    ]);

    expect((new ScriveDocument)->getPdf('doc-13'))->toBe('%PDF-binary');
});

it('getBase64Pdf returns base64 of the binary PDF', function () {
    Http::fake([
        '*/files/main/*' => Http::response('%PDF-binary'),
    ]);

    expect((new ScriveDocument)->getBase64Pdf('doc-14'))->toBe(base64_encode('%PDF-binary'));
});

it('getPdfUrl builds the expected path', function () {
    $url = (new ScriveDocument)->getPdfUrl('doc-15');

    expect($url)->toBe('https://docs.test.scrive.example/api/v2/documents/doc-15/files/main/doc-15.pdf');
});

it('getPdf propagates ScriveApiException on HTTP errors', function () {
    Http::fake([
        '*' => Http::response('not found', 404),
    ]);

    (new ScriveDocument)->getPdf('missing');
})->throws(ScriveApiException::class);

it('setAttachment uploads the file as multipart', function () {
    $parties = [scrive_signing_party('signer')];

    Http::fakeSequence()
        ->push(scrive_document('doc-16', $parties))
        ->push(scrive_document('doc-16', $parties));

    $tmp = tempnam(sys_get_temp_dir(), 'scrive-test-');
    file_put_contents($tmp, 'fake-pdf-bytes');

    try {
        (new ScriveDocument)
            ->newFromTemplate('tpl-16')
            ->setAttachment('Proof of ID', $tmp);
    } finally {
        @unlink($tmp);
    }

    $uploadRequest = Http::recorded()[1][0];
    expect($uploadRequest->url())->toContain('/doc-16/signer/setattachment')
        ->and($uploadRequest->method())->toBe('POST')
        ->and($uploadRequest->header('Content-Type')[0])->toStartWith('multipart/form-data');
});

it('setAttachment throws when the file does not exist', function () {
    Http::fake([
        '*' => Http::response(scrive_document('doc-17')),
    ]);

    (new ScriveDocument)
        ->newFromTemplate('tpl-17')
        ->setAttachment('Proof', '/no/such/file.pdf');
})->throws(ScriveValidationException::class, 'not found or not readable');

it('rejects documents that are missing required fields', function () {
    Http::fake([
        '*' => Http::response(['id' => 'doc-18']), // missing parties
    ]);

    (new ScriveDocument)->newFromTemplate('tpl-18');
})->throws(ScriveValidationException::class, 'missing parties');
