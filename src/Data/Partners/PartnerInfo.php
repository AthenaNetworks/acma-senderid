<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data\Partners;

use AthenaNetworks\AcmaSenderId\Support\Assert;
use AthenaNetworks\AcmaSenderId\Support\Attributes;
use AthenaNetworks\AcmaSenderId\Support\Payload;

/**
 * Identifying details of a partner (telco partner or entity associate).
 *
 * When creating, PartnerName, RegNumber, RegNumberType, Website and CountryOfIncorp are required.
 */
final class PartnerInfo extends Payload
{
    public function __construct(
        public readonly ?string $partnerName = null,
        public readonly ?string $regNumber = null,
        public readonly ?string $website = null,
        public readonly ?string $regNumberType = null,
        public readonly ?string $countryOfIncorp = null,
        public readonly ?string $stateOfIncorp = null,
        public readonly ?string $refNumber = null,
        public readonly ?string $refNumberType = null,
    ) {
    }

    /**
     * Convenience constructor for an Australian partner identified by ABN.
     */
    public static function australian(string $partnerName, string $abn, string $website, ?string $stateOfIncorp = null): self
    {
        return new self(
            partnerName: $partnerName,
            regNumber: preg_replace('/\s+/', '', $abn) ?? $abn,
            website: $website,
            regNumberType: 'ABN',
            countryOfIncorp: 'AU',
            stateOfIncorp: $stateOfIncorp,
        );
    }

    /**
     * @internal
     */
    public function validate(string $prefix, bool $complete): void
    {
        if ($complete) {
            Assert::required("{$prefix}.PartnerName", $this->partnerName);
            Assert::required("{$prefix}.RegNumber", $this->regNumber);
            Assert::required("{$prefix}.RegNumberType", $this->regNumberType);
            Assert::required("{$prefix}.Website", $this->website);
            Assert::required("{$prefix}.CountryOfIncorp", $this->countryOfIncorp);
        }

        Assert::length("{$prefix}.PartnerName", $this->partnerName, 1, 80);
        Assert::length("{$prefix}.RegNumber", $this->regNumber, 1, 21);
        Assert::length("{$prefix}.RegNumberType", $this->regNumberType, 1, 20);
        Assert::length("{$prefix}.Website", $this->website, 5, 128);
        Assert::httpsUrl("{$prefix}.Website", $this->website);
        Assert::countryCode("{$prefix}.CountryOfIncorp", $this->countryOfIncorp);
        Assert::length("{$prefix}.StateOfIncorp", $this->stateOfIncorp, 0, 3);
        Assert::length("{$prefix}.RefNumber", $this->refNumber, 0, 20);
        Assert::length("{$prefix}.RefNumberType", $this->refNumberType, 0, 20);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $a = Attributes::of($data);

        return new self(
            partnerName: $a->string('PartnerName'),
            regNumber: $a->string('RegNumber'),
            website: $a->string('Website'),
            regNumberType: $a->string('RegNumberType'),
            countryOfIncorp: $a->string('CountryOfIncorp'),
            stateOfIncorp: $a->string('StateOfIncorp'),
            refNumber: $a->string('RefNumber'),
            refNumberType: $a->string('RefNumberType'),
        );
    }

    protected function fields(): array
    {
        return [
            'PartnerName' => $this->partnerName,
            'Website' => $this->website,
            'RegNumber' => $this->regNumber,
            'RegNumberType' => $this->regNumberType,
            'CountryOfIncorp' => $this->countryOfIncorp === null ? null : strtoupper($this->countryOfIncorp),
            'StateOfIncorp' => $this->stateOfIncorp,
            'RefNumber' => $this->refNumber,
            'RefNumberType' => $this->refNumberType,
        ];
    }
}
