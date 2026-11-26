<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Resources;

use AthenaNetworks\AcmaSenderId\Data\NetworkProvider;
use AthenaNetworks\AcmaSenderId\Exceptions\ApiException;
use AthenaNetworks\AcmaSenderId\Http\Transport;
use AthenaNetworks\AcmaSenderId\Support\Attributes;

/**
 * Mobile Network Operators. Exists for RCS compatibility only; ACMA always
 * returns a placeholder with ID {@see NetworkProvider::DEFAULT_ID}.
 */
final class NetworkProviders
{
    public function __construct(private readonly Transport $transport)
    {
    }

    /**
     * @return list<NetworkProvider>
     *
     * @throws ApiException
     */
    public function list(): array
    {
        // This path is not versioned with {v} in the spec.
        $data = $this->transport->get('rcsva/v1/util/network_providers');

        return array_values(array_map(
            NetworkProvider::fromArray(...),
            array_filter(Attributes::of($data)->array('NetworkProviders'), is_array(...)),
        ));
    }
}
