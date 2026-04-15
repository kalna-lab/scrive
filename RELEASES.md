# Releases

High-level release notes for `kalna-lab/scrive`. For the full per-version
technical log, see [`CHANGELOG.md`](CHANGELOG.md).

Versions before 2.0.0 are reconstructed from the git history; the
milestone narrative below groups the 50+ patch releases into the themes
they were actually solving.

---

## 2.0.0 — 2026-04-15

Security, testability and error-handling overhaul. Recommended upgrade for
every 1.x user.

### Highlights

- **TLS is now enforced on every request.** 1.x disabled certificate
  verification, which meant any network path between you and Scrive could
  have intercepted OAuth credentials. 2.0 uses Laravel's HTTP client with
  standard TLS verification.
- **Errors are no longer silent.** Every public method either returns a
  valid result or throws a typed exception. No more `null` returns hiding
  404s, network failures or malformed responses.
- **Credentials are now private.** OAuth tokens and bearer tokens live
  behind `private readonly` properties, so they cannot leak through
  `dd()`, `var_dump()`, logging or serialization.
- **Test-friendly.** `Http::fake()` works out of the box — the package is
  now a thin layer over `Illuminate\Http\Client\Factory` instead of raw
  cURL. A full Pest test suite ships alongside.

### Upgrade guide (1.x → 2.0)

1. **Bump the constraint**
   ```bash
   composer require kalna-lab/scrive:^2.0
   ```

2. **Replace `null`-checks with `try/catch`**
   ```php
   // Before (1.x)
   $pdf = (new ScriveDocument())->getPdf($id);
   if ($pdf === null) {
       abort(502, 'Scrive is down');
   }

   // After (2.0)
   use KalnaLab\Scrive\Exceptions\ScriveException;

   try {
       $pdf = (new ScriveDocument())->getPdf($id);
   } catch (ScriveException $e) {
       abort(502, $e->getMessage());
   }
   ```

3. **Provision a real TLS cert for any custom Scrive hostname.** If you
   pointed `SCRIVE_*_BASE_PATH` at a dev server with a self-signed
   certificate, either trust the CA on the machine running the Laravel
   app or switch to a proper cert.

4. **Update the facade alias** in any `config/app.php` that manually
   registered it:
   ```php
   // Before
   'Scrive' => KalnaLab\Scrive\Facade\Scrive::class,   // singular — wrong

   // After
   'Scrive' => KalnaLab\Scrive\Facades\Scrive::class,  // plural — correct
   ```
   Package auto-discovery handles this for you if you didn't override it.

5. **Stop poking at public state.** `Scrive::$headers`, `Scrive::$body`,
   `Scrive::$endpoint` (and equivalents on `ScriveDocument`) are now
   private. If you relied on them, file an issue describing the use case.

6. **Switch CPR validation to the new entrypoint**
   ```php
   // Before (1.x) — lived on the data class, had to mutate state
   $ok = $completionData->validateCPR('010180-1234');

   // After (2.0) — first-class method on Scrive
   $ok = (new Scrive())->validateCpr($transactionId, '010180-1234');
   ```
   The old `dkMitIDCompletionData::validateCPR()` still exists and now
   delegates to `Scrive::validateCpr()`, but prefer the explicit form.

### Migration risk

| Area                     | Risk   | Why                                                   |
| ------------------------ | ------ | ----------------------------------------------------- |
| TLS cert failure         | Medium | Only if you pointed Scrive at a non-trusted host      |
| Exception type change    | Low    | New exceptions extend `\RuntimeException`; old catches work |
| `null` → exception       | Low    | Compiler won't catch it, but it's a fast runtime fail |
| Private state            | Low    | No documented use case for the old public fields      |

### Known limitations

- API coverage is still ~16% of Scrive's full v2 surface. Commonly-asked
  endpoints such as `documents/list`, `documents/{id}/cancel`,
  `documents/{id}/remind`, and `documents/new` (upload from PDF) are **not
  yet implemented** — they are planned for a follow-up 2.1 release.
- Document responses are returned as `\stdClass`. Typed DTOs are on the
  roadmap but did not land in 2.0.

---

## 1.0.x series — October 2025 to January 2026

The 1.0 line focused on filling in the document-signing surface one
method at a time. Over 26 patch releases it grew from a bare template
flow into a usable document client. No breaking changes were ever
introduced within the series — everything additive.

### What landed across 1.0.1 → 1.0.26

**Document lifecycle control**
- `setCallbackUrl()` (1.0.4), `setTitle()` (1.0.14),
  `setAttachment()` (1.0.14), `setRejectRedirectUrl()` (1.0.15).
- Method chaining throughout — every mutating method returns `self`
  (1.0.1).

**PDF and data retrieval**
- `getData()` and `getPdfUrl()` (1.0.10), with `getData()` return type
  tightened to `object` in 1.0.12.
- Binary PDF handling in `executeCall` (1.0.22) and a dedicated
  `getBase64Pdf()` alongside a split `getPdf()` (1.0.22 / 1.0.25).

**Party-matching flexibility**
- `update()` gained an optional `partyIndex` and a customisable
  `signatory_role` selector (1.0.18).
- `author` role added to the role constants, with `is_author` respected
  during party matching (1.0.19).
- Internal constant renamed from `SIGNATORY_ROLE` to `ROLE` (1.0.20),
  and the name-parsing simplified to drop the `Arr` dependency (1.0.21).

**Reliability**
- String-cast all field values before sending to Scrive (1.0.6).
- Field validation guards against missing `name` properties (1.0.9).
- `getPdfUrl()` actually uses the argument it's given (1.0.13).
- `ScriveController::authenticate` reads the transaction id via
  `input()` instead of `get()` (1.0.17).
- Config split into `scrive.auth.env` and `scrive.document.env` so the
  two APIs can target different environments (1.0.3).

**Known weakness in the 1.0 line** (addressed in 2.0): the `executeCall`
error-handling logic was revised several times (1.0.23 → 1.0.26) but
kept returning `null` on failure, which hid real errors from callers.
2.0 replaces the entire HTTP layer.

---

## 1.0.0 — 2025-10-02

First stable release. Consolidated the document-signing flow after
six months of iteration on the 0.x line.

### Highlights

- `ScriveDocument` refactored around a coherent endpoint-management
  model, uniform header setup and exception handling.
- Config reshaped to match the new base-paths and callback layout.
- Method chaining introduced throughout the document client (formalised
  in 1.0.1 the same day).

This was the first release that anyone could reasonably recommend
deploying. It is *not* the release we still recommend — jump straight
to 2.0 when it ships.

---

## 0.2.x series — February to April 2025

The "completion data" and CPR-validation pass. The eID authentication
flow learned how to surface Danish personal numbers safely.

### Highlights

- CPR validation for providers, with transaction-id propagation (0.2.0).
- `Scrive` gained a constructor that reads the environment config (0.2.1).
- `CompletionData` classes restructured for clarity (0.2.2).
- CPR validation migrated off ad-hoc HTTP plumbing onto a shared
  `Scrive` instance (0.2.3).
- Debug logging of Scrive API responses (0.2.4).
- `dkMitID` array handling simplified (0.2.5 → 0.2.6).

---

## 0.1.x series — January to August 2024

The authentication flow stabilises. The package goes from "it compiles"
to "it can complete a MitID login end-to-end".

### Highlights

- `Content-Type` header on outgoing requests (0.1.0).
- Completion data restructured under `Resources/AuthProviders` (0.1.1).
- `NewScriveSignInEvent` gets its final constructor shape (0.1.2).
- Session-persisted `ScriveCompletionData` after successful auth (0.1.3).
- Session short-circuit in `ScriveController::dkMitID` so already-
  authenticated users aren't re-prompted (0.1.5).
- Missing `RedirectResponse` import fixed (0.1.6).
- Default reference text configurable (0.1.7).
- Graceful failure when completion data is missing or authentication
  never completes (0.1.8 / 0.1.9).

---

## 0.0.x series — January 2024

Initial scaffolding. Everything was built in a single day (0.0.1
through 0.1.5 all tagged on 2024-01-04), so the version numbers here
are more like numbered checkpoints than independent releases.

### Highlights

- Scrive Laravel plugin and supporting files (0.0.1).
- Service provider and facade renamed to Laravel conventions (0.0.2).
- `ScriveController` and routing (0.0.3 / 0.0.4).
- `ScriveFacade` moved into the correct `Facades/` directory (0.0.6).
- `setHeaders()` iterated on several times (0.0.7 / 0.0.8).

None of the 0.0.x releases should be used. They exist in the tag list
for historical completeness only.
