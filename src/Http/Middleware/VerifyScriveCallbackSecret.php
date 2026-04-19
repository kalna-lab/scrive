<?php

declare(strict_types=1);

namespace KalnaLab\Scrive\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use KalnaLab\Scrive\Exceptions\ScriveAuthenticationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards inbound Scrive document callback endpoints with a shared secret.
 *
 * The secret is configured in `scrive.document.callback.secret` and is
 * expected on incoming requests as the `signature` query parameter (or
 * form field). A constant-time `hash_equals()` comparison is used.
 *
 * Fails closed: if the configured secret is empty, every request is
 * rejected. This prevents a misconfigured deployment from accepting
 * unauthenticated callbacks.
 */
class VerifyScriveCallbackSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string)config('scrive.document.callback.secret', '');
        $provided = (string)$request->input('signature', '');

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            throw new ScriveAuthenticationException(
                'Invalid or missing Scrive callback signature.',
                401,
            );
        }

        return $next($request);
    }
}
