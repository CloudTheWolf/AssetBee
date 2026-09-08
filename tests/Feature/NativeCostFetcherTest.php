<?php

use App\Enums\CloudTenantCostSyncProvider;
use App\Enums\CloudTenantProvider;
use App\Enums\SoftwareCostSyncProvider;
use App\Models\CloudTenant;
use App\Models\Software;
use App\Services\Costs\AtlassianCostFetcher;
use App\Services\Costs\AwsCostFetcher;
use App\Services\Costs\AzureCostFetcher;
use App\Services\Costs\CursorCostFetcher;
use App\Services\Costs\GoogleWorkspaceCostFetcher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

test('atlassian cost fetcher reads billing details', function () {
    Http::fake([
        'https://api.atlassian.com/admin/v1/orgs/org-1/billing-details' => Http::response([
            'currentBill' => [
                'amount' => 199.5,
                'currency' => 'USD',
            ],
            'seats' => 25,
        ]),
    ]);

    $software = Software::factory()->create([
        'cost_sync_provider' => SoftwareCostSyncProvider::Atlassian,
        'cost_sync_credentials' => [
            'organization_id' => 'org-1',
            'email' => 'admin@example.com',
            'api_token' => 'token',
        ],
    ]);

    $periods = app(AtlassianCostFetcher::class)->fetch(
        $software,
        CarbonImmutable::now()->subMonths(2),
        CarbonImmutable::now(),
    );

    expect($periods)->toHaveCount(1)
        ->and($periods[0]->amount)->toBe(199.5)
        ->and($periods[0]->seatCount)->toBe(25);
});

test('cursor cost fetcher maps team billing payload', function () {
    Http::fake([
        'https://api.cursor.com/teams/spend' => Http::response([
            'teamMemberSpend' => [
                [
                    'overallSpendCents' => 4500,
                    'spendCents' => 2000,
                ],
                [
                    'overallSpendCents' => 3500,
                    'spendCents' => 1500,
                ],
            ],
            'subscriptionCycleStart' => CarbonImmutable::now()->startOfMonth()->getTimestampMs(),
            'totalMembers' => 8,
            'totalPages' => 1,
        ]),
    ]);

    $software = Software::factory()->create([
        'cost_sync_provider' => SoftwareCostSyncProvider::Cursor,
        'cost_sync_credentials' => [
            'api_key' => 'key',
        ],
    ]);

    $periods = app(CursorCostFetcher::class)->fetch(
        $software,
        CarbonImmutable::now()->subMonths(1),
        CarbonImmutable::now(),
    );

    expect($periods[0]->amount)->toBe(80.0)
        ->and($periods[0]->seatCount)->toBe(8);
});

test('azure cost fetcher queries cost management after authenticating', function () {
    Http::fake([
        'https://login.microsoftonline.com/*/oauth2/v2.0/token' => Http::response([
            'access_token' => 'azure-token',
        ]),
        'https://management.azure.com/subscriptions/*/providers/Microsoft.CostManagement/query*' => Http::response([
            'properties' => [
                'columns' => [
                    ['name' => 'Cost'],
                    ['name' => 'BillingMonth'],
                    ['name' => 'Currency'],
                ],
                'rows' => [
                    [42.5, '2026-08-01T00:00:00Z', 'USD'],
                ],
            ],
        ]),
    ]);

    $tenant = CloudTenant::factory()->create([
        'provider' => CloudTenantProvider::Azure,
        'cost_sync_provider' => CloudTenantCostSyncProvider::Native,
        'credentials' => [
            'tenant_id' => 'tenant',
            'client_id' => 'client',
            'client_secret' => 'secret',
            'subscription_id' => 'sub-1',
        ],
    ]);

    $periods = app(AzureCostFetcher::class)->fetch(
        $tenant,
        CarbonImmutable::parse('2026-08-01'),
        CarbonImmutable::parse('2026-08-31'),
    );

    expect($periods)->toHaveCount(1)
        ->and($periods[0]->amount)->toBe(42.5)
        ->and($periods[0]->currency)->toBe('USD');
});

test('aws and google workspace fetchers advertise support correctly', function () {
    $aws = CloudTenant::factory()->aws()->withCredentials()->create([
        'cost_sync_provider' => CloudTenantCostSyncProvider::Native,
    ]);
    $workspace = CloudTenant::factory()->create([
        'provider' => CloudTenantProvider::GoogleWorkspace,
        'cost_sync_provider' => CloudTenantCostSyncProvider::Native,
        'credentials' => [
            'customer_id' => 'C1',
            'service_account_email' => 'sa@example.com',
            'service_account_json' => '{"type":"service_account"}',
            'admin_email' => 'admin@example.com',
        ],
    ]);

    expect(app(AwsCostFetcher::class)->supports($aws))->toBeTrue()
        ->and(app(GoogleWorkspaceCostFetcher::class)->supports($workspace))->toBeTrue()
        ->and(app(AwsCostFetcher::class)->supports($workspace))->toBeFalse();
});
