<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data;

use AthenaNetworks\AcmaSenderId\Exceptions\InvalidArgumentException;
use AthenaNetworks\AcmaSenderId\Support\Assert;
use AthenaNetworks\AcmaSenderId\Support\Attributes;
use AthenaNetworks\AcmaSenderId\Support\Payload;

/**
 * Contact details (BrandContactInfo on a chatbot, PartnerContactInfo on a partner).
 *
 * The Sender ID Register emails this contact, so make sure the address is valid:
 * an "Email Bounce" status is reported when it is not.
 */
final class ContactInfo extends Payload
{
    public function __construct(
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $emailAddress = null,
        public readonly ?string $telephoneNumber = null,
        public readonly ?string $title = null,
    ) {
    }

    /**
     * Validate against the spec's constraints. Called by request objects.
     *
     * @internal
     */
    public function validate(string $prefix, bool $complete, bool $requireTitle = false): void
    {
        if ($complete) {
            Assert::required("{$prefix}.FirstName", $this->firstName);
            Assert::required("{$prefix}.LastName", $this->lastName);
            Assert::required("{$prefix}.EmailAddress", $this->emailAddress);
            Assert::required("{$prefix}.TelephoneNumber", $this->telephoneNumber);
            if ($requireTitle) {
                Assert::required("{$prefix}.Title", $this->title);
            }
        }

        Assert::length("{$prefix}.FirstName", $this->firstName, 1, 32);
        Assert::length("{$prefix}.LastName", $this->lastName, 1, 32);
        Assert::length("{$prefix}.EmailAddress", $this->emailAddress, 1, 252);
        Assert::length("{$prefix}.TelephoneNumber", $this->telephoneNumber, 1, 15);
        Assert::length("{$prefix}.Title", $this->title, $requireTitle ? 1 : 0, 128);

        if ($this->emailAddress !== null && filter_var($this->emailAddress, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidArgumentException::forField("{$prefix}.EmailAddress", "must be a valid email address (got \"{$this->emailAddress}\")");
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $a = Attributes::of($data);

        return new self(
            firstName: $a->string('FirstName'),
            lastName: $a->string('LastName'),
            emailAddress: $a->string('EmailAddress'),
            telephoneNumber: $a->string('TelephoneNumber'),
            title: $a->string('Title'),
        );
    }

    protected function fields(): array
    {
        return [
            'FirstName' => $this->firstName,
            'LastName' => $this->lastName,
            'EmailAddress' => $this->emailAddress,
            'Title' => $this->title,
            'TelephoneNumber' => $this->telephoneNumber,
        ];
    }
}
