<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data\Brands;

use AthenaNetworks\AcmaSenderId\Support\Assert;
use AthenaNetworks\AcmaSenderId\Support\Attributes;
use AthenaNetworks\AcmaSenderId\Support\Payload;

/**
 * Identifying details of a brand (the entity registering a Sender ID).
 *
 * When creating a brand, BrandName, RegNumber, RegNumberType, Website and
 * CountryOfIncorp are required. RegNumberType should be "ABN" unless your
 * account is a Certified Participating Telco.
 */
final class BrandInfo extends Payload
{
    public function __construct(
        public readonly ?string $brandName = null,
        public readonly ?string $regNumber = null,
        public readonly ?string $website = null,
        public readonly ?string $regNumberType = null,
        public readonly ?string $countryOfIncorp = null,
        public readonly ?string $stateOfIncorp = null,
        public readonly ?string $refNumber = null,
        public readonly ?string $refNumberType = null,
        public readonly ?string $defaultIcon = null,
        public readonly ?string $serviceIconSN = null,
        public readonly ?string $snJurisdiction = null,
        public readonly ?string $serviceIconOwner = null,
    ) {
    }

    /**
     * Convenience constructor for an Australian entity identified by ABN.
     */
    public static function australian(string $brandName, string $abn, string $website, ?string $stateOfIncorp = null): self
    {
        return new self(
            brandName: $brandName,
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
            Assert::required("{$prefix}.BrandName", $this->brandName);
            Assert::required("{$prefix}.RegNumber", $this->regNumber);
            Assert::required("{$prefix}.RegNumberType", $this->regNumberType);
            Assert::required("{$prefix}.Website", $this->website);
            Assert::required("{$prefix}.CountryOfIncorp", $this->countryOfIncorp);
        }

        Assert::length("{$prefix}.BrandName", $this->brandName, 1, 80);
        Assert::length("{$prefix}.RegNumber", $this->regNumber, 1, 21);
        Assert::length("{$prefix}.RegNumberType", $this->regNumberType, 1, 20);
        Assert::length("{$prefix}.Website", $this->website, 5, 128);
        Assert::httpsUrl("{$prefix}.Website", $this->website);
        Assert::countryCode("{$prefix}.CountryOfIncorp", $this->countryOfIncorp);
        Assert::length("{$prefix}.StateOfIncorp", $this->stateOfIncorp, 0, 3);
        Assert::length("{$prefix}.RefNumber", $this->refNumber, 0, 20);
        Assert::length("{$prefix}.RefNumberType", $this->refNumberType, 0, 20);
        Assert::length("{$prefix}.ServiceIconSN", $this->serviceIconSN, 0, 8);
        Assert::length("{$prefix}.SNJurisdiction", $this->snJurisdiction, 0, 2);
        Assert::length("{$prefix}.ServiceIconOwner", $this->serviceIconOwner, 0, 128);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $a = Attributes::of($data);

        return new self(
            brandName: $a->string('BrandName'),
            regNumber: $a->string('RegNumber'),
            website: $a->string('Website'),
            regNumberType: $a->string('RegNumberType'),
            countryOfIncorp: $a->string('CountryOfIncorp'),
            stateOfIncorp: $a->string('StateOfIncorp'),
            refNumber: $a->string('RefNumber'),
            refNumberType: $a->string('RefNumberType'),
            defaultIcon: $a->string('DefaultIcon'),
            serviceIconSN: $a->string('ServiceIconSN'),
            snJurisdiction: $a->string('SNJurisdiction'),
            serviceIconOwner: $a->string('ServiceIconOwner'),
        );
    }

    protected function fields(): array
    {
        return [
            'BrandName' => $this->brandName,
            'Website' => $this->website,
            'RegNumber' => $this->regNumber,
            'RegNumberType' => $this->regNumberType,
            'CountryOfIncorp' => $this->countryOfIncorp === null ? null : strtoupper($this->countryOfIncorp),
            'StateOfIncorp' => $this->stateOfIncorp,
            'RefNumber' => $this->refNumber,
            'RefNumberType' => $this->refNumberType,
            'DefaultIcon' => $this->defaultIcon,
            'ServiceIconSN' => $this->serviceIconSN,
            'SNJurisdiction' => $this->snJurisdiction,
            'ServiceIconOwner' => $this->serviceIconOwner,
        ];
    }
}
