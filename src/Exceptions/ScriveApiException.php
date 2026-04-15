<?php

declare(strict_types=1);

namespace KalnaLab\Scrive\Exceptions;

use Illuminate\Http\Client\Response;

/**
 * Thrown when the Scrive API responds with a non-2xx status code.
 *
 * Exposes the HTTP status and raw response body for inspection.
 */
class ScriveApiException extends ScriveException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly ?string $responseBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    public static function fromResponse(Response $response, ?string $context = null): self
    {
        $body = $response->body();
        $status = $response->status();
        $message = $context
            ? sprintf('%s: Scrive API returned HTTP %d', $context, $status)
            : sprintf('Scrive API returned HTTP %d', $status);

        if ($body !== '') {
            $message .= ' – ' . mb_substr($body, 0, 500);
        }

        return new self($message, $status, $body);
    }
}
