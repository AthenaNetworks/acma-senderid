<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Enums;

/**
 * Valid use case categories for a Sender ID ("Category" on ChatbotInfo).
 */
enum UseCaseCategory: string
{
    // For entities with an ABN, when Verify is "complete".
    case RegisteredBusinessName = 'registered_business_name';
    case RegisteredCompanyName = 'registered_company_name';
    case RegisteredTradeMark = 'registered_trade_mark';
    case RegisteredDomainName = 'registered_domain_name';

    // Certified Participating Telcos, when Verify is "overwrite".
    case TrademarkRegister = 'trademark_register';
    case NationalRegistry = 'national_registry';

    // Either.
    case Exception = 'exception';

    /**
     * Categories that satisfy the valid use case requirement for the given Verify mode.
     *
     * @return list<self>
     */
    public static function validFor(Verify $verify): array
    {
        return match ($verify) {
            Verify::Complete => [self::RegisteredBusinessName, self::RegisteredCompanyName, self::RegisteredTradeMark, self::RegisteredDomainName, self::Exception],
            Verify::Overwrite => [self::TrademarkRegister, self::NationalRegistry, self::Exception],
            Verify::NotStarted => self::cases(),
        };
    }
}
