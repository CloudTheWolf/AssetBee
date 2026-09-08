<?php

namespace App\Services\Costs;

use App\Contracts\Costs\FetchesAssetCosts;
use App\Data\FetchedCostPeriod;
use App\Models\CloudTenant;
use App\Models\Software;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use RuntimeException;

class CostFetchManager
{
    /**
     * @param  iterable<FetchesAssetCosts>  $fetchers
     */
    public function __construct(
        protected iterable $fetchers,
    ) {}

    public function fetcherFor(Software|CloudTenant $asset): FetchesAssetCosts
    {
        foreach ($this->fetchers as $fetcher) {
            if ($fetcher->supports($asset)) {
                return $fetcher;
            }
        }

        throw new RuntimeException(__('No cost sync service is available for this asset.'));
    }

    public function supports(Software|CloudTenant $asset): bool
    {
        foreach ($this->fetchers as $fetcher) {
            if ($fetcher->supports($asset)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<FetchedCostPeriod>
     */
    public function fetch(Software|CloudTenant $asset, CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->fetcherFor($asset)->fetch($asset, $from, $to);
    }

    /**
     * @return Collection<int, FetchesAssetCosts>
     */
    public function fetchers(): Collection
    {
        return collect($this->fetchers);
    }
}
