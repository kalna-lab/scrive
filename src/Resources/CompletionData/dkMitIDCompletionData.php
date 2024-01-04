<?php

namespace KalnaLab\Scrive\Resources\CompletionData;

use Carbon\Carbon;

class dkMitIDCompletionData extends CompletionData
{
    public ?string $cpr;
    public ?Carbon $dateOfBirth;
    public ?string $employeeData;
    public ?string $ial;
    public ?string $identityName;
    public string $userId;
}