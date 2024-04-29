<?php

namespace KalnaLab\Scrive\Resources\AuthProviders;

use Carbon\Carbon;
use KalnaLab\Scrive\Resources\AuthProviders\Enums\dkMitIDAction;
use KalnaLab\Scrive\Resources\AuthProviders\Enums\dkMitIDLanguage;
use KalnaLab\Scrive\Resources\AuthProviders\Enums\dkMitIDLevel;
use KalnaLab\Scrive\Resources\CompletionData\CompletionData;
use KalnaLab\Scrive\Resources\CompletionData\dkMitIDCompletionData;

class dkMitID extends Provider
{
    /** @var dkMitIDCompletionData */
    public CompletionData $completionData;

    public function __construct(
        public dkMitIDAction $action = dkMitIDAction::LogOn,
        public string $cpr = '',
        public bool $employeeLogin = false,
        public dkMitIDLanguage $language = dkMitIDLanguage::Da,
        public dkMitIDLevel $level = dkMitIDLevel::Substantial,
        public string $referenceText = '',
        public bool $requestCPR = false,
    ) {
        if (!$this->referenceText) {
            $this->referenceText = config('scrive.reference-text');
        }
    }

    public static function getProviderName(): string
    {
        return 'dkMitID';
    }

    /**
     * @throws \Exception
     */
    public static function parse(object $payload): self
    {
        if (!property_exists($payload->providerInfo, self::getProviderName())) {
            throw new \Exception('No providerInfo found');
        }

        $instance = new self();
        $completionData = $payload->providerInfo->{self::getProviderName()}->completionData;

        $instance->completionData = new dkMitIDCompletionData();
        $instance->completionData->providerName = self::getProviderName();
        $instance->completionData->cpr = $completionData->cpr;
        $instance->completionData->dateOfBirth = Carbon::parse($completionData->dateOfBirth);
        $instance->completionData->employeeData = $completionData->employeeData;
        $instance->completionData->ial = $completionData->ial;
        $instance->completionData->identityName = $completionData->identityName;
        $instance->completionData->userId = $completionData->userId;

        return $instance;
    }

    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'cpr' => $this->cpr,
            'employeeLogin' => $this->employeeLogin,
            'language' => $this->language,
            'level' => $this->level,
            'referenceText' => $this->referenceText,
            'requestCPR' => $this->requestCPR,
        ];
    }

    public function toJson(): string
    {
        return json_encode([
            'action' => $this->action,
            'cpr' => $this->cpr,
            'employeeLogin' => $this->employeeLogin,
            'language' => $this->language,
            'level' => $this->level,
            'referenceText' => $this->referenceText,
            'requestCPR' => $this->requestCPR,
        ]);
    }
}
