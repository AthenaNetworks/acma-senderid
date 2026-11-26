<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data;

use AthenaNetworks\AcmaSenderId\Support\Assert;
use AthenaNetworks\AcmaSenderId\Support\Attributes;
use AthenaNetworks\AcmaSenderId\Support\Payload;

/**
 * A postal address (BrandAddress / PartnerAddress).
 *
 * All fields are optional so the same class can be used for partial updates;
 * when creating a record, StreetAddress1, City, PostalCode and Country are required.
 */
final class Address extends Payload
{
    public function __construct(
        public readonly ?string $streetAddress1 = null,
        public readonly ?string $city = null,
        public readonly ?string $postalCode = null,
        public readonly ?string $country = null,
        public readonly ?string $streetAddress2 = null,
        public readonly ?string $governingDistrict = null,
    ) {
    }

    /**
     * Validate against the spec's constraints. Called by request objects.
     *
     * @internal
     */
    public function validate(string $prefix, bool $complete): void
    {
        if ($complete) {
            Assert::required("{$prefix}.StreetAddress1", $this->streetAddress1);
            Assert::required("{$prefix}.City", $this->city);
            Assert::required("{$prefix}.PostalCode", $this->postalCode);
            Assert::required("{$prefix}.Country", $this->country);
        }

        Assert::length("{$prefix}.StreetAddress1", $this->streetAddress1, 1, 80);
        Assert::length("{$prefix}.StreetAddress2", $this->streetAddress2, 0, 80);
        Assert::length("{$prefix}.City", $this->city, 1, 50);
        Assert::length("{$prefix}.GoverningDistrict", $this->governingDistrict, 0, 50);
        Assert::length("{$prefix}.PostalCode", $this->postalCode, 1, 10);
        Assert::countryCode("{$prefix}.Country", $this->country);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $a = Attributes::of($data);

        return new self(
            streetAddress1: $a->string('StreetAddress1'),
            city: $a->string('City'),
            postalCode: $a->string('PostalCode'),
            country: $a->string('Country'),
            streetAddress2: $a->string('StreetAddress2'),
            governingDistrict: $a->string('GoverningDistrict'),
        );
    }

    protected function fields(): array
    {
        return [
            'StreetAddress1' => $this->streetAddress1,
            'StreetAddress2' => $this->streetAddress2,
            'City' => $this->city,
            'GoverningDistrict' => $this->governingDistrict,
            'PostalCode' => $this->postalCode,
            'Country' => $this->country === null ? null : strtoupper($this->country),
        ];
    }
}
