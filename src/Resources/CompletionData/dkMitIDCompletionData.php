<?php

declare(strict_types=1);

namespace KalnaLab\Scrive\Resources\CompletionData;

use Carbon\Carbon;
use KalnaLab\Scrive\Exceptions\ScriveValidationException;
use KalnaLab\Scrive\Scrive;

class dkMitIDCompletionData extends CompletionData
{
    public ?string $cpr = null;
    public ?Carbon $dateOfBirth = null;
    public ?string $employeeData = null;
    public ?string $ial = null;
    public ?string $identityName = null;
    public string $userId = '';

    /**
     * Verify that the given CPR matches the one returned in the completed
     * transaction. Delegates to {@see Scrive::validateCpr()}.
     *
     * @throws ScriveValidationException when the transactionId has not been set.
     */
    public function validateCPR(string $cpr, ?Scrive $scrive = null): bool
    {
        if ($this->transactionId === null) {
            throw new ScriveValidationException('Cannot validate CPR without a transactionId');
        }

        $scrive ??= new Scrive;

        return $scrive->validateCpr($this->transactionId, $cpr);
    }
}
