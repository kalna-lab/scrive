<?php

declare(strict_types=1);

namespace KalnaLab\Scrive\Exceptions;

/**
 * Thrown when the HTTP transport fails to reach the Scrive API.
 *
 * Wraps connection errors, DNS errors, timeouts, and TLS failures.
 */
class ScriveNetworkException extends ScriveException {}
