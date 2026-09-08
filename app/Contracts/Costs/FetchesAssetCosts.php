<?php

namespace App\Contracts\Costs;

use App\Data\FetchedCostPeriod;
use App\Models\CloudTenant;
use App\Models\Software;
use Carbon\CarbonInterface;
use RuntimeException;

interface FetchesAssetCosts
{
    public function supports(Software|CloudTenant $asset): bool;

    /**
     * @return list<FetchedCostPeriod>
     *
     * @throws RuntimeException
     */
    public function fetch(Software|CloudTenant $asset, CarbonInterface $from, CarbonInterface $to): array;
}
