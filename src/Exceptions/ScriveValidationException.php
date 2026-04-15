<?php

declare(strict_types=1);

namespace KalnaLab\Scrive\Exceptions;

/**
 * Thrown when a request or response cannot be processed because the
 * data shape is invalid (malformed JSON, missing required fields,
 * unexpected provider in an auth callback, etc.).
 */
class ScriveValidationException extends ScriveException {}
