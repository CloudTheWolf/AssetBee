<?php

use App\Enums\SoftwareCostSyncProvider;
use App\Models\Software;
use App\Services\Costs\CustomHttpCostFetcher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

test('custom http cost fetcher maps json paths to a cost period', function () {
    Http::fake([
        'https://billing.example.test/costs' => Http::response([
            'data' => [
                'total' => 249.99,
                'currency' => 'gbp',
                'seats' => 18,
            ],
        ]),
    ]);

    $software = Software::factory()->create([
        'cost_sync_provider' => SoftwareCostSyncProvider::CustomHttp,
        'cost_sync_credentials' => [
            'bearer_token' => 'secret-token',
        ],
        'cost_sync_request' => [
            'method' => 'GET',
            'url' => 'https://billing.example.test/costs',
            'headers' => [],
            'auth' => 'bearer',
            'body' => '',
            'response_amount_path' => 'data.total',
            'response_currency_path' => 'data.currency',
            'response_seats_path' => 'data.seats',
            'amount_period' => 'month',
        ],
    ]);

    $periods = app(CustomHttpCostFetcher::class)->fetch(
        $software,
        CarbonImmutable::now()->startOfMonth()->subMonths(2),
        CarbonImmutable::now(),
    );

    expect($periods)->toHaveCount(1)
        ->and($periods[0]->amount)->toBe(249.99)
        ->and($periods[0]->currency)->toBe('GBP')
        ->and($periods[0]->seatCount)->toBe(18);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret-token'));
});
