# Changelog

All notable changes to `kalna-lab/scrive` are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/) and the
project adheres to [Semantic Versioning](https://semver.org/).

Entries prior to 2.0.0 were reconstructed from the git history and may be
terser than entries curated at release time.

## [2.0.0] - 2026-04-15

This release is a substantial rewrite focused on security, testability and
error ergonomics. Public method signatures are largely preserved, but the
error-handling contract has changed — review the **BREAKING** section below
before upgrading.

### Breaking

- **TLS verification is now enforced.** Previous versions disabled both
  `CURLOPT_SSL_VERIFYPEER` and `CURLOPT_SSL_VERIFYHOST`. If you relied on
  this for connecting to a non-public test host, provision a proper certificate.
- **Methods no longer silently return `null` on errors.** `getSignUrl()`,
  `getData()`, `getPdf()` and `getBase64Pdf()` now throw one of the new
  `ScriveException` subclasses on failure.
- **Custom exceptions replace generic `\Exception`.** See
  `KalnaLab\Scrive\Exceptions\*`. Legacy `catch (\Exception $e)` blocks continue
  to work because the new exceptions extend `\RuntimeException`.
- **Public object state is now private.** `Scrive::$headers`, `Scrive::$body`,
  `Scrive::$endpoint` and the equivalents on `ScriveDocument` are no longer
  accessible from outside the class. Credentials can no longer leak via
  `dd()`/logging.
- **Composer facade alias fixed.** Previously registered as
  `KalnaLab\Scrive\Facade\Scrive` (singular); now correctly
  `KalnaLab\Scrive\Facades\Scrive`.
- **`ScriveDocument::getPdf()` return type narrowed** from `mixed` to `string`.
- **`ScriveDocument::getData()` return type narrowed** from `?object` to `\stdClass`.
- **`ScriveDocument::getSignUrl()` return type narrowed** from `?string` to `string`.
- **`ScriveDocument::getBase64Pdf()` return type narrowed** from `?string` to `string`.
- **Laravel is now a hard dependency.** `composer.json` now requires
  `illuminate/support`, `illuminate/http` and `nesbot/carbon` explicitly.
- **Minimum Laravel version is 11.** Minimum PHP version remains 8.2.

### Added

- New `KalnaLab\Scrive\Http\ScriveHttpClient` wraps Laravel's HTTP client.
  All requests flow through it, so tests can use `Http::fake()` without
  monkey-patching `curl_*`.
- Typed exception hierarchy under `KalnaLab\Scrive\Exceptions\`:
  `ScriveException`, `ScriveApiException`, `ScriveAuthenticationException`,
  `ScriveNetworkException`, `ScriveValidationException`.
- `Scrive::validateCpr(string $transactionId, string $cpr)` – moves the
  mutation-heavy CPR check out of `dkMitIDCompletionData` and exposes it
  as a first-class method.
- Pest test suite covering the public surface of `Scrive`, `ScriveDocument`,
  the exception hierarchy, and the `dkMitID` provider parser.
- `declare(strict_types=1)` on every source file.

### Fixed

- `ScriveDocument::executeCall()` no longer overwrites `$response` mid
  error-handling, and no longer throws a spurious "empty response" error
  after a legitimate 2xx result.
- `ScriveDocument::setAttachment()` now surfaces API errors instead of
  silently logging them.
- `ScriveDocument::getBase64Pdf()` no longer calls `base64_encode(null)`
  when the PDF download fails.
- Invalid JSON responses are now reported as `ScriveValidationException`
  instead of triggering an "undefined property on null" fatal error.
- `ScriveServiceProvider` no longer imports non-existent classes (dead
  `use` lines for `App\Events\FileCreated`, `Console\Install`, etc.).
- The `scrive` container binding now returns a working `Scrive` instance
  instead of a bare `Facade` subclass with no methods.
- `routes.php` no longer imports the non-existent `PostingController`.

### Removed

- The empty `Scrive::sign()` stub method.
- Unused public `\CurlHandle` properties on `Scrive` and `ScriveDocument`.
- Direct `curl_*` usage across the package.

## [1.0.26] - 2026-01-27

### Changed

- `executeCall` now conditionally returns the raw response and handles
  verbose logging more predictably.

## [1.0.25] - 2026-01-27

### Changed

- Split `getBase64Pdf` and `getPdf` in `ScriveDocument` so the raw PDF
  fetch is isolated from base64 encoding and error handling is tighter.

## [1.0.24] - 2026-01-27

### Changed

- Streamlined the cURL configuration in `ScriveDocument::executeCall`.
- Renamed the internal `$expectBinary` flag to `$returnRawBody` for clarity.

## [1.0.23] - 2026-01-27

### Fixed

- `executeCall` now tolerates a null response body instead of fataling.

## [1.0.22] - 2026-01-27

### Added

- `ScriveDocument::getBase64Pdf()` — fetch the signed PDF as base64.

### Changed

- `executeCall` can now carry binary payloads.

## [1.0.21] - 2025-12-16

### Changed

- Simplified `extractName` index handling; dropped the `Arr` helper dependency.

## [1.0.20] - 2025-12-16

### Changed

- Renamed the `SIGNATORY_ROLE` constant to `ROLE` and adjusted the party
  matching logic in `update()` accordingly.

## [1.0.19] - 2025-12-16

### Added

- `author` role in `SIGNATORY_ROLE`; `update()` now honours `is_author`
  when matching parties.

## [1.0.18] - 2025-12-16

### Added

- `update()` accepts an optional `partyIndex` and a customisable
  `signatory_role` selector.

## [1.0.17] - 2025-11-27

### Fixed

- `ScriveController::authenticate` now reads the transaction id with
  `input()` instead of `get()`.

## [1.0.16] - 2025-11-19

### Changed

- `update()` reads the name directly from the `values` array and drops
  the redundant dedicated parameter.

## [1.0.15] - 2025-11-17

### Added

- `ScriveDocument::setRejectRedirectUrl()` — set a redirect URL used when
  a signer rejects.

## [1.0.14] - 2025-11-13

### Added

- `ScriveDocument::setTitle()` — change the document title.
- `ScriveDocument::setAttachment()` — upload a supporting PDF to the
  signing party.

## [1.0.13] - 2025-11-12

### Fixed

- `getPdfUrl()` now uses the supplied `$documentId` argument instead of
  the instance property.

## [1.0.12] - 2025-11-12

### Changed

- `getData()` return type tightened to `object`.

## [1.0.11] - 2025-11-12

### Changed

- `executeCall()` made `private`; callers must go through the public API.

## [1.0.10] - 2025-11-12

### Added

- `ScriveDocument::getData()` — fetch the full document JSON.
- `ScriveDocument::getPdfUrl()` — build the canonical PDF URL.

## [1.0.9] - 2025-11-11

### Fixed

- Field-value validation in `ScriveDocument` now checks that the `name`
  property exists before reading it.

## [1.0.8] - 2025-11-06

### Changed

- Removed a redundant parameter from the success redirect flow in
  `ScriveController`.

## [1.0.7] - 2025-11-06

### Changed

- Extracted `redirectSuccessfulAuth()` in `ScriveController` to centralise
  the post-authentication redirect logic.

## [1.0.6] - 2025-10-28

### Fixed

- All field values sent to `ScriveDocument` are now cast to strings, as
  required by the Scrive API spec.

## [1.0.5] - 2025-10-28

### Changed

- Updated the default Scrive `base-path` to the current production URL.

## [1.0.4] - 2025-10-08

### Added

- `ScriveDocument::setCallbackUrl()` — set the `api_callback_url` on the
  document.

## [1.0.3] - 2025-10-06

### Changed

- Split `scrive.env` into `scrive.auth.env` and `scrive.document.env`;
  `Scrive` and `ScriveDocument` now pick the appropriate environment
  independently.

## [1.0.2] - 2025-10-03

### Changed

- `ScriveDocument` refactored for better method chaining and clearer
  exception messages; removed noisy debug logging.

## [1.0.1] - 2025-10-02

### Changed

- `ScriveDocument` mutating methods now return `self` to enable chaining.

## [1.0.0] - 2025-10-02

First stable release. Consolidates the document-signing flow into a
coherent client.

### Changed

- Major `ScriveDocument` refactor: consolidated endpoint management,
  header setup and exception handling.
- Config restructured to align with the new base paths and callback
  settings.

## [0.2.6] - 2025-04-09

### Changed

- Simplified the array handling in `dkMitID`.

## [0.2.5] - 2025-04-09

### Changed

- Cleaned up the internal Scrive data structure and tightened imports.

## [0.2.4] - 2025-04-08

### Added

- Scrive API responses are now logged for debugging purposes.

## [0.2.3] - 2025-02-19

### Changed

- CPR validation now uses a dedicated `Scrive` instance instead of ad-hoc
  HTTP plumbing inside the completion-data class.

## [0.2.2] - 2025-02-19

### Changed

- Restructured the initialization logic in the `CompletionData` classes.

## [0.2.1] - 2025-02-19

### Added

- Constructor initialising the Scrive environment config on `Scrive`.

## [0.2.0] - 2025-02-19

### Added

- CPR validation flow for providers, including transaction-id handling.

## [0.1.9] - 2024-08-30

### Added

- Failure handling when Scrive authentication does not complete.

## [0.1.8] - 2024-08-16

### Fixed

- Graceful error handling when the auth payload is missing completion data.

## [0.1.7] - 2024-04-29

### Added

- Default reference text in the auth-provider config.

## [0.1.6] - 2024-01-08

### Fixed

- Missing `RedirectResponse` import in `ScriveController`.

## [0.1.5] - 2024-01-04

### Added

- Session check in `ScriveController::dkMitID` to short-circuit already
  authenticated users.

## [0.1.4] - 2024-01-04

### Changed

- Tidied up `ScriveController` and `Scrive` method signatures.

## [0.1.3] - 2024-01-04

### Added

- `ScriveCompletionData` is stored in the session after a successful
  authentication.

## [0.1.2] - 2024-01-04

### Changed

- Refactored the `NewScriveSignInEvent` constructor.

## [0.1.1] - 2024-01-04

### Changed

- Restructured the completion data under `Resources/AuthProviders`.

## [0.1.0] - 2024-01-04

### Fixed

- `Scrive.php` now sets a proper `Content-Type` header on outgoing
  requests.

## [0.0.8] - 2024-01-04

### Changed

- Refactored `setHeaders()` in `Scrive.php`.

## [0.0.7] - 2024-01-04

### Changed

- Adjusted Scrive instantiation and header configuration.

## [0.0.6] - 2024-01-04

### Changed

- Moved and renamed `ScriveFacade` into the `Facades/` directory.

## [0.0.5] - 2024-01-04

### Added

- Missing exception annotation on the `authenticate` method.

## [0.0.4] - 2024-01-04

### Added

- `dkMitID` method on `ScriveController`.

## [0.0.3] - 2024-01-04

### Added

- `ScriveController` and matching route registration.

## [0.0.2] - 2024-01-04

### Changed

- Renamed the service provider and facade to align with Laravel
  conventions.

## [0.0.1] - 2024-01-04

Initial scaffolding of the Scrive Laravel plugin and supporting files.
