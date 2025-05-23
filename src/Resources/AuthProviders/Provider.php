<?php

namespace KalnaLab\Scrive\Resources\AuthProviders;

use KalnaLab\Scrive\Resources\CompletionData\CompletionData;

abstract class Provider
{
    public ?CompletionData $completionData = null;
    public bool $success = false;

    abstract public static function getProviderName();

    /**
     * @throws \Exception
     */
    public static function parse(object $payload): self
    {
        if (!property_exists($payload, 'provider')) {
            throw new \Exception('No provider found');
        }
        if (!class_exists('KalnaLab\\Scrive\\Resources\\AuthProviders\\' . $payload->provider)) {
            throw new \Exception('No valid provider found');
        }

        $provider = app('KalnaLab\\Scrive\\Resources\\AuthProviders\\' . $payload->provider);

        return $provider::parse($payload);
    }

    public function setTransactionId(string $transactionId): void
    {
        if (!$this->completionData) {
            return;
        }
        $this->completionData->transactionId = $transactionId;
    }

    abstract public function toArray(): array;

    abstract public function toJson(): string;
}
