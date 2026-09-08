<?php

namespace App\Data;

use App\Enums\CostSyncSource;
use Carbon\CarbonInterface;

readonly class FetchedCostPeriod
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public CarbonInterface $periodStart,
        public CarbonInterface $periodEnd,
        public float $amount,
        public string $currency,
        public CostSyncSource $provider,
        public ?int $seatCount = null,
        public array $meta = [],
    ) {}
}
