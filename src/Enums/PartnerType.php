<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Enums;

enum PartnerType: string
{
    /** An EMSP or international partner on whose behalf the telco applies. */
    case TelcoPartner = 'telco-partner';

    /** An entity associate (Australian ABN holder). This is the API default. */
    case EntityAssociate = 'entity-associate';
}
