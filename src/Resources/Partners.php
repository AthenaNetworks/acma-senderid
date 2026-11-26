<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Resources;

use AthenaNetworks\AcmaSenderId\Data\Partners\NewPartner;
use AthenaNetworks\AcmaSenderId\Data\Partners\Partner;
use AthenaNetworks\AcmaSenderId\Data\Partners\PartnerSummary;
use AthenaNetworks\AcmaSenderId\Data\Partners\PartnerUpdate;
use AthenaNetworks\AcmaSenderId\Enums\VerificationStatus;
use AthenaNetworks\AcmaSenderId\Exceptions\ApiException;
use AthenaNetworks\AcmaSenderId\Http\Transport;
use AthenaNetworks\AcmaSenderId\Support\Attributes;

/**
 * Partners: telco partners (EMSP / international) and entity associates.
 */
final class Partners
{
    public function __construct(private readonly Transport $transport)
    {
    }

    /**
     * @param list<VerificationStatus|string> $verified Filter: "not-complete", "pending", "complete".
     * @return list<PartnerSummary>
     *
     * @throws ApiException
     */
    public function list(array $verified = []): array
    {
        $data = $this->transport->get($this->transport->path('partners'), ['verified' => Filters::statuses($verified)]);

        return array_values(array_map(
            PartnerSummary::fromArray(...),
            array_filter(Attributes::of($data)->array('Partners'), is_array(...)),
        ));
    }

    /**
     * @throws ApiException
     */
    public function get(string $partnerId): Partner
    {
        return Partner::fromArray($this->transport->get($this->transport->path('partners', $partnerId)), $partnerId);
    }

    /**
     * Create a partner and return its ID.
     *
     * @throws ApiException
     */
    public function create(NewPartner $partner): string
    {
        return Ids::extract(
            $this->transport->post($this->transport->path('partners'), $partner->toArray(), $partner->queryParameters()),
            'PartnerId',
        );
    }

    /**
     * @throws ApiException
     */
    public function update(string $partnerId, PartnerUpdate $changes): string
    {
        return Ids::extract($this->transport->patch($this->transport->path('partners', $partnerId), $changes->toArray()), 'PartnerId', $partnerId);
    }

    /**
     * Delete a partner. Fails if the partner has an active chatbot.
     *
     * @throws ApiException
     */
    public function delete(string $partnerId): string
    {
        return Ids::extract($this->transport->delete($this->transport->path('partners', $partnerId)), 'PartnerId', $partnerId);
    }
}
