# kalna-lab/scrive

Laravel package for integrating with the [Scrive eID](https://eid.scrive.com/documentation/api/v1/)
and [Scrive document signing API](https://apidocs.scrive.com/).

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Scrive account with API credentials (eID bearer token and/or document OAuth 1.0 tokens)

## Installation

```bash
composer require kalna-lab/scrive
```

Publish the config file:

```bash
php artisan vendor:publish --tag=scrive-config
```

## Configuration

Set the following variables in your `.env` file (only the ones you need):

```dotenv
# eID (authentication) API
SCRIVE_ENV=test                       # live | test
SCRIVE_TEST_TOKEN=your-bearer-token
SCRIVE_TOKEN=your-live-bearer-token

# Document API (OAuth 1.0 PLAINTEXT)
SCRIVE_TEST_API_TOKEN=
SCRIVE_TEST_API_SECRET=
SCRIVE_TEST_ACCESS_TOKEN=
SCRIVE_TEST_ACCESS_SECRET=
```

See `config/scrive.php` for the full list of configurable keys and default endpoints.

## Usage

### Authenticate with Danish MitID (eID)

```php
use KalnaLab\Scrive\Resources\AuthProviders\dkMitID;
use KalnaLab\Scrive\Scrive;

// Step 1 — start a transaction and redirect the user to Scrive
$accessUrl = (new Scrive())->authorize(new dkMitID());
return redirect($accessUrl);

// Step 2 — Scrive calls back with ?transaction_id=...
//          The bundled ScriveController handles this automatically.
//          Listen for NewScriveSignInEvent to react to successful logins.
```

Listen for successful authentications:

```php
use KalnaLab\Scrive\Events\NewScriveSignInEvent;

Event::listen(NewScriveSignInEvent::class, function (NewScriveSignInEvent $event) {
    // $event->payload is a CompletionData instance (e.g. dkMitIDCompletionData)
    logger()->info('User signed in', ['cpr' => $event->payload->cpr ?? null]);
});
```

### Create and sign a document from a template

```php
use KalnaLab\Scrive\ScriveDocument;

$signUrl = (new ScriveDocument())
    ->newFromTemplate('template-id-from-scrive')
    ->update([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'cpr' => '010180-1234',
    ])
    ->setTitle('NDA for Jane')
    ->setCallbackUrl(route('scrive.document.callback'))
    ->setSuccessRedirectUrl(route('signing.success'))
    ->setRejectRedirectUrl(route('signing.rejected'))
    ->getSignUrl();

return redirect($signUrl);
```

### Verifying incoming callbacks

Scrive's document API v2 does not sign callback POSTs — they arrive at your
endpoint as plain HTTP requests. Anyone who can reach your callback route can
fabricate one. The package provides two primitives to close this gap:

1. A `scrive.callback` middleware that checks a shared-secret signature on
   the callback URL.
2. A `ScriveCallbackRequest` form request that, once the signature is
   verified, fetches the authoritative document from Scrive using the
   `document_id` from the body (verify-by-fetch) and hands your controller
   a trusted `\stdClass` instead of the untrusted request body.

**Step 1 — configure a secret.** Pick a random ≥32-character opaque string
and set it in your `.env`:

```dotenv
SCRIVE_CALLBACK_SECRET=replace-with-a-long-random-hex-string
# optional: disable the verify-by-fetch round-trip (not recommended)
# SCRIVE_CALLBACK_VERIFY=false
```

If `SCRIVE_CALLBACK_SECRET` is empty, the middleware rejects every request
(fail-closed).

**Step 2 — protect your callback routes.** Attach the `scrive.callback`
middleware and type-hint `ScriveCallbackRequest` in the controller:

```php
use KalnaLab\Scrive\Http\Requests\ScriveCallbackRequest;

Route::post('/callbacks/cancel-membership', CancelMembershipController::class)
    ->middleware('scrive.callback')
    ->name('scrive.cancel-membership');

class CancelMembershipController
{
    public function __invoke(ScriveCallbackRequest $request): Response
    {
        $document = $request->document(); // verified \stdClass fetched from Scrive
        // ... your business logic on the trusted payload
        return response()->noContent();
    }
}
```

**Step 3 — build the callback URL with the signature baked in.** Replace
`setCallbackUrl(route(...))` with `setVerifiedCallbackUrl($routeName)`:

```php
(new ScriveDocument())
    ->newFromTemplate($templateId)
    ->update($values)
    ->setVerifiedCallbackUrl('scrive.cancel-membership')
    ->setSuccessRedirectUrl(route('profile', ['scrive' => 'success']))
    ->setRejectRedirectUrl(route('profile', ['scrive' => 'rejected']))
    ->getSignUrl();
```

`setVerifiedCallbackUrl()` appends `?signature=<secret>` to the generated
URL. Scrive stores the URL verbatim and plays it back on every callback,
which is what the middleware matches against.

**Passive listeners.** If you prefer an event-driven style — e.g. to log
or audit every callback — listen for `ScriveDocumentCallbackReceived`:

```php
use KalnaLab\Scrive\Events\ScriveDocumentCallbackReceived;

Event::listen(function (ScriveDocumentCallbackReceived $event) {
    logger()->info('Scrive callback', [
        'route' => $event->request->route()?->getName(),
        'document_id' => $event->document->id,
    ]);
});
```

The event fires automatically after `ScriveCallbackRequest` has verified
the signature and resolved the document, so listeners always see a trusted
payload.

### Fetch a signed PDF

```php
$scrive = new ScriveDocument();
$binary = $scrive->getPdf('document-id');            // raw bytes
$base64 = $scrive->getBase64Pdf('document-id');      // base64
$data   = $scrive->getData('document-id');           // full JSON as stdClass
```

### Error handling

All methods throw exceptions from the `KalnaLab\Scrive\Exceptions` namespace:

| Exception                           | When it fires                                      |
| ----------------------------------- | -------------------------------------------------- |
| `ScriveNetworkException`            | Connection error, DNS failure, timeout, TLS error  |
| `ScriveAuthenticationException`     | HTTP 401/403 (bad credentials, expired token)      |
| `ScriveApiException`                | Any other 4xx/5xx from the Scrive API              |
| `ScriveValidationException`         | Malformed request data, missing response fields    |
| `ScriveException`                   | Base class — catch this to handle any Scrive error |

```php
use KalnaLab\Scrive\Exceptions\ScriveApiException;
use KalnaLab\Scrive\Exceptions\ScriveException;

try {
    $signUrl = (new ScriveDocument())->newFromTemplate($id)->getSignUrl();
} catch (ScriveApiException $e) {
    logger()->error('Scrive rejected request', [
        'status' => $e->httpStatus,
        'body' => $e->responseBody,
    ]);
} catch (ScriveException $e) {
    // catches network/validation/etc
}
```

## Testing

The package ships with a Pest test suite. Run it with:

```bash
composer test
```

When testing code that depends on Scrive, use Laravel's `Http::fake()` to mock
responses — `ScriveHttpClient` is a thin wrapper around `Illuminate\Http\Client\Factory`:

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    '*/api/v2/documents/newfromtemplate/*' => Http::response(['id' => 'doc-1', 'parties' => []]),
]);
```

## Security

TLS verification is enabled. Credentials are kept in private properties so they
don't leak through `dd()`, logs, or serialization. If you discover a security
issue, please email claus@kalna.it rather than opening a public issue.

## License

MIT © [Claus Hjort Bube @ KalnaIT](https://github.com/kalna-lab)
