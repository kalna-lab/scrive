<?php

declare(strict_types=1);

namespace KalnaLab\Scrive\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thrown when Scrive rejects the supplied credentials or auth headers
 * (e.g. invalid OAuth signature, expired bearer token, 401/403 responses)
 * or when an inbound callback fails our own authentication checks
 * (invalid or missing signature).
 */
class ScriveAuthenticationException extends ScriveApiException
{
    public function render(Request $request): JsonResponse
    {
        $status = $this->httpStatus >= 400 && $this->httpStatus < 600
            ? $this->httpStatus
            : 401;

        return new JsonResponse(['message' => 'Unauthorized'], $status);
    }
}
