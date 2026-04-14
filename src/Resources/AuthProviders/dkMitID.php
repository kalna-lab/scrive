<?php

declare(strict_types=1);

namespace KalnaLab\Scrive\Resources\AuthProviders;

use Carbon\Carbon;
use KalnaLab\Scrive\Exceptions\ScriveValidationException;
use KalnaLab\Scrive\Resources\AuthProviders\Enums\dkMitIDAction;
use KalnaLab\Scrive\Resources\AuthProviders\Enums\dkMitIDLanguage;
use KalnaLab\Scrive\Resources\AuthProviders\Enums\dkMitIDLevel;
use KalnaLab\Scrive\Resources\CompletionData\CompletionData;
use KalnaLab\Scrive\Resources\CompletionData\dkMitIDCompletionData;

class dkMitID extends Provider
{
    /** @var dkMitIDCompletionData|null */
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
        if ($this->referenceText === '') {
            $this->referenceText = (string)config('scrive.auth.reference-text') ?: '-';
        }
    }

    public static function getProviderName(): string
    {
        return 'dkMitID';
    }

    /**
     * @throws ScriveValidationException
     */
    public static function parse(object $payload): self
    {
        if (!property_exists($payload, 'providerInfo') || !property_exists($payload->providerInfo, self::getProviderName())) {
            throw new ScriveValidationException('Scrive auth payload is missing providerInfo.' . self::getProviderName());
        }

        $instance = new self;
        $instance->completionData = new dkMitIDCompletionData;
        $instance->completionData->providerName = self::getProviderName();

        if (($payload->status ?? null) === 'failed') {
            return $instance;
        }

        try {
            $completionData = $payload->providerInfo->{self::getProviderName()}->completionData;
            $instance->completionData->cpr = $completionData->cpr ?? null;
            $instance->completionData->dateOfBirth = isset($completionData->dateOfBirth)
                ? Carbon::parse($completionData->dateOfBirth)
                : null;
            $instance->completionData->employeeData = $completionData->employeeData ?? null;
            $instance->completionData->ial = $completionData->ial ?? null;
            $instance->completionData->identityName = $completionData->identityName ?? null;
            $instance->completionData->userId = (string)$completionData->userId;
            $instance->success = true;
        } catch (\Throwable $e) {
            throw new ScriveValidationException(
                'Unable to parse completionData for ' . self::getProviderName() . ': ' . $e->getMessage(),
                previous: $e,
            );
        }

        return $instance;
    }

    /**
     * @return array<string, mixed>
     */
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
        if ($this->cpr !== '') {
            $array['cpr'] = $this->cpr;
        }

        return $array;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
