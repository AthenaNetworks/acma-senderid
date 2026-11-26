<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Resources;

use AthenaNetworks\AcmaSenderId\Data\Brands\Brand;
use AthenaNetworks\AcmaSenderId\Data\Brands\BrandSummary;
use AthenaNetworks\AcmaSenderId\Data\Brands\BrandUpdate;
use AthenaNetworks\AcmaSenderId\Data\Brands\NewBrand;
use AthenaNetworks\AcmaSenderId\Enums\VerificationStatus;
use AthenaNetworks\AcmaSenderId\Exceptions\ApiException;
use AthenaNetworks\AcmaSenderId\Http\Transport;
use AthenaNetworks\AcmaSenderId\Support\Attributes;

/**
 * Brands: the entities registering Sender IDs.
 */
final class Brands
{
    public function __construct(private readonly Transport $transport)
    {
    }

    /**
     * List brands owned by your account.
     *
     * @param list<VerificationStatus|string> $verified Filter: "not-complete", "pending", "complete".
     * @return list<BrandSummary>
     *
     * @throws ApiException
     */
    public function list(array $verified = []): array
    {
        $data = $this->transport->get($this->transport->path('brands'), ['verified' => Filters::statuses($verified)]);

        return array_values(array_map(
            BrandSummary::fromArray(...),
            array_filter(Attributes::of($data)->array('Brands'), is_array(...)),
        ));
    }

    /**
     * @throws ApiException
     */
    public function get(string $brandId): Brand
    {
        return Brand::fromArray($this->transport->get($this->transport->path('brands', $brandId)), $brandId);
    }

    /**
     * Create a brand and return its ID.
     *
     * @throws ApiException
     */
    public function create(NewBrand $brand): string
    {
        return Ids::extract($this->transport->post($this->transport->path('brands'), $brand->toArray()), 'BrandId');
    }

    /**
     * Partially update a brand and return its ID.
     *
     * @throws ApiException
     */
    public function update(string $brandId, BrandUpdate $changes): string
    {
        return Ids::extract($this->transport->patch($this->transport->path('brands', $brandId), $changes->toArray()), 'BrandId', $brandId);
    }

    /**
     * Delete a brand. Fails if the brand has an active chatbot.
     *
     * @throws ApiException
     */
    public function delete(string $brandId): string
    {
        return Ids::extract($this->transport->delete($this->transport->path('brands', $brandId)), 'BrandId', $brandId);
    }
}
