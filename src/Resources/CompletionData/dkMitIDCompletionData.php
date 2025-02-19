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

    public function validateCPR(string $cpr): bool
    {
        $this->init();
        $this->endpoint .= $this->transactionId . '/dk/cpr-match';
        $this->httpMethod = 'POST';

        $this->instantiateCurl();

        $this->body = [
            'cpr' => $cpr,
        ];

        $result = $this->executeCall();

        return $result->isMatch;
    }
}
