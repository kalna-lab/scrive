<?php

namespace KalnaLab\Scrive\Resources\CompletionData;

abstract class CompletionData
{
    public string $providerName;
    public ?string $transactionId = null;
}
