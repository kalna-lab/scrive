<?php

namespace KalnaLab\Scrive\Resources\AuthProviders;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use KalnaLab\Scrive\Resources\AuthProviders\Enums\dkMitIDAction;
use KalnaLab\Scrive\Resources\AuthProviders\Enums\dkMitIDLanguage;
use KalnaLab\Scrive\Resources\AuthProviders\Enums\dkMitIDLevel;
use KalnaLab\Scrive\Resources\CompletionData\CompletionData;
use KalnaLab\Scrive\Resources\CompletionData\dkMitIDCompletionData;

class dkMitID extends Provider
{
    /** @var dkMitIDCompletionData */
    public ?CompletionData $completionData = null;

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
            $this->referenceText = config('scrive.auth.reference-text') ?: '-';
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
        $instance->completionData = new dkMitIDCompletionData();
        $instance->completionData->providerName = self::getProviderName();

        if ($payload->status == 'failed') {
            return $instance;
        }

        try {
            $completionData = $payload->providerInfo->{self::getProviderName()}->completionData;
            $instance->completionData->cpr = $completionData->cpr;
            $instance->completionData->dateOfBirth = Carbon::parse($completionData->dateOfBirth);
            $instance->completionData->employeeData = $completionData->employeeData;
            $instance->completionData->ial = $completionData->ial;
            $instance->completionData->identityName = $completionData->identityName;
            $instance->completionData->userId = $completionData->userId;
            $instance->success = true;
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ' (' . __LINE__ . '): ' . $e->getMessage() . "\nProviderName: " . self::getProviderName() . "\nPayload: " . json_encode($payload, JSON_PRETTY_PRINT));
            throw new \Exception('No completionData found for ' . self::getProviderName());
        }

        return $instance;
    }

    public function toArray(): array
    {
        $array = [
            'action' => $this->action,
            'employeeLogin' => $this->employeeLogin,
            'language' => $this->language,
            'level' => $this->level,
            'referenceText' => $this->referenceText,
            'requestCPR' => $this->requestCPR,
            'success' => $this->success,
        ];
        if ($this->cpr) {
            $array['cpr'] = $this->cpr;
        }
        return $array;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
