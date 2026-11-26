<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Webhook;

/**
 * Source IP addresses ACMA may use for callback notifications (per the API docs).
 */
final class AcmaIpAddresses
{
    /** @var list<string> */
    public const array ALL = [
        '52.189.234.100',
        '52.243.67.193',
        '52.189.252.152',
        '4.200.88.109',
        '4.200.88.51',
        '4.200.88.144',
    ];
}
