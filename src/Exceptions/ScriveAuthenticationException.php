<?php

declare(strict_types=1);

namespace KalnaLab\Scrive\Exceptions;

/**
 * Thrown when Scrive rejects the supplied credentials or auth headers
 * (e.g. invalid OAuth signature, expired bearer token, 401/403 responses).
 */
class ScriveAuthenticationException extends ScriveApiException {}
