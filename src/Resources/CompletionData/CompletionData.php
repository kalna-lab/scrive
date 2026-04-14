<?php

declare(strict_types=1);

namespace KalnaLab\Scrive\Resources\CompletionData;

abstract class CompletionData
{
    public string $providerName;
    public ?string $transactionId = null;
}
