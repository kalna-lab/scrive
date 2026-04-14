<?php

declare(strict_types=1);

namespace KalnaLab\Scrive\Resources\AuthProviders;

use KalnaLab\Scrive\Exceptions\ScriveValidationException;
use KalnaLab\Scrive\Resources\CompletionData\CompletionData;

abstract class Provider
{
    public ?CompletionData $completionData = null;
    public bool $success = false;

    abstract public static function getProviderName(): string;

    /**
     * Resolve the concrete provider class from the API payload and parse it.
     *
     * @throws ScriveValidationException
     */
    public static function parse(object $payload): self
    {
        if (!property_exists($payload, 'provider') || !is_string($payload->provider)) {
            throw new ScriveValidationException('Scrive auth payload is missing the `provider` field');
        }

        $class = __NAMESPACE__ . '\\' . $payload->provider;
        if (!class_exists($class)) {
            throw new ScriveValidationException('Unsupported Scrive auth provider: ' . $payload->provider);
        }

        /** @var self $provider */
        $provider = app($class);

        return $provider::parse($payload);
    }

    public function setTransactionId(string $transactionId): void
    {
        if ($this->completionData === null) {
            return;
        }
        $this->completionData->transactionId = $transactionId;
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    abstract public function toJson(): string;
}
