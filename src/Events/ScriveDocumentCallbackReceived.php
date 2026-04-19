<?php

declare(strict_types=1);

namespace KalnaLab\Scrive\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

/**
 * Fired after an inbound Scrive document callback has been authenticated
 * (middleware pass) and resolved to an authoritative document payload
 * (either by fetching it from the API or by trusting the body when
 * `scrive.document.callback.verify_against_api` is disabled).
 *
 * Listeners receive the verified document alongside the original HTTP
 * request so they can route on route names, URLs or headers without
 * needing to re-parse the body.
 */
class ScriveDocumentCallbackReceived
{
    use Dispatchable;

    public function __construct(
        public readonly \stdClass $document,
        public readonly Request $request,
    ) {}
}
