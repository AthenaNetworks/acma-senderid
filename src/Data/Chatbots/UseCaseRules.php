<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data\Chatbots;

use AthenaNetworks\AcmaSenderId\Enums\UseCaseCategory;
use AthenaNetworks\AcmaSenderId\Enums\Verify;
use AthenaNetworks\AcmaSenderId\Exceptions\InvalidArgumentException;

/**
 * Valid use case category rules for submitting a Sender ID for verification.
 *
 * @internal
 */
final class UseCaseRules
{
    public static function check(Verify $verify, ChatbotInfo $info, bool $requireCategory): void
    {
        if ($verify === Verify::NotStarted) {
            return;
        }

        $categories = $info->category ?? [];
        if ($categories === [] && ! $requireCategory) {
            // Permitted when ACMA already holds a valid use case for this
            // entity and Sender ID (see SenderIdClient::hasValidUseCase()).
            return;
        }

        $valid = array_map(static fn (UseCaseCategory $c): string => $c->value, UseCaseCategory::validFor($verify));
        if (array_intersect($categories, $valid) === []) {
            throw InvalidArgumentException::forField(
                'ChatbotInfo.Category',
                sprintf('when Verify is "%s" it must include at least one of: %s', $verify->value, implode(', ', $valid)),
            );
        }
    }
}
