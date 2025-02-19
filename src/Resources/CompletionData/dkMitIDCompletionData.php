<?php

namespace KalnaLab\Scrive\Resources\CompletionData;

use Carbon\Carbon;
use KalnaLab\Scrive\Scrive;

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
        $scriveApi = new Scrive();
        $scriveApi->endpoint .= $this->transactionId . '/dk/cpr-match';
        $scriveApi->httpMethod = 'POST';

        $scriveApi->instantiateCurl();

        $scriveApi->body = [
            'cpr' => $cpr,
        ];

        $result = $scriveApi->executeCall();

        if (property_exists($result, 'isMatch')) {
            return $result->isMatch;
        }

        if (property_exists($result, 'err')) {
            throw new \Exception($result->err);
        }

        return false;
    }
}
