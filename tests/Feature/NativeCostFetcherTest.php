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

test('atlassian cost fetcher counts billable seats from admin users', function () {
    Http::fake([
        'https://api.atlassian.com/admin/v1/orgs/org-1/users' => Http::response([
            'data' => [
                [
                    'account_id' => '1',
                    'account_status' => 'active',
                    'access_billable' => true,
                    'product_access' => [['key' => 'jira-software', 'name' => 'Jira']],
                ],
                [
                    'account_id' => '2',
                    'account_status' => 'active',
                    'access_billable' => false,
                    'product_access' => [],
                ],
                [
                    'account_id' => '3',
                    'account_status' => 'inactive',
                    'access_billable' => true,
                    'product_access' => [['key' => 'confluence', 'name' => 'Confluence']],
                ],
            ],
            'links' => [
                'next' => null,
            ],
        ]),
    ]);

    $software = Software::factory()->create([
        'currency' => 'USD',
        'cost_sync_provider' => SoftwareCostSyncProvider::Atlassian,
        'cost_sync_credentials' => [
            'organization_id' => 'org-1',
            'api_token' => 'org-api-key',
            'products' => [
                'jira' => ['price_per_seat' => 10],
                'confluence' => ['price_per_seat' => 5],
            ],
        ],
    ]);

    $periods = app(AtlassianCostFetcher::class)->fetch(
        $software,
        CarbonImmutable::now()->subMonths(2),
        CarbonImmutable::now(),
    );

    expect($periods)->toHaveCount(1)
        ->and($periods[0]->amount)->toBe(10.0)
        ->and($periods[0]->seatCount)->toBe(1)
        ->and($periods[0]->meta['source'])->toBe('atlassian_product_seats');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.atlassian.com/admin/v1/orgs/org-1/users'
            && $request->hasHeader('Authorization', 'Bearer org-api-key');
    });
});

test('atlassian cost fetcher follows cursor pagination for seat counts', function () {
    Http::fake([
        'https://api.atlassian.com/admin/v1/orgs/org-1/users' => Http::response([
            'data' => [
                [
                    'account_id' => '1',
                    'account_status' => 'active',
                    'product_access' => [['key' => 'jira-software', 'name' => 'Jira']],
                ],
            ],
            'links' => [
                'next' => 'cursor-2',
            ],
        ]),
        'https://api.atlassian.com/admin/v1/orgs/org-1/users?cursor=cursor-2' => Http::response([
            'data' => [
                [
                    'account_id' => '2',
                    'account_status' => 'active',
                    'product_access' => [['key' => 'jira-software', 'name' => 'Jira']],
                ],
            ],
            'links' => [
                'next' => null,
            ],
        ]),
    ]);

    $software = Software::factory()->create([
        'currency' => 'GBP',
        'cost_sync_provider' => SoftwareCostSyncProvider::Atlassian,
        'cost_sync_credentials' => [
            'organization_id' => 'org-1',
            'api_token' => 'org-api-key',
            'products' => [
                'jira' => ['price_per_seat' => 5],
            ],
        ],
    ]);

    $periods = app(AtlassianCostFetcher::class)->fetch(
        $software,
        CarbonImmutable::now()->subMonth(),
        CarbonImmutable::now(),
    );

    expect($periods[0]->seatCount)->toBe(2)
        ->and($periods[0]->amount)->toBe(10.0);

    Http::assertSentCount(2);
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

test('google workspace cost fetcher counts licensed seats', function () {
    Http::fake([
        'https://licensing.googleapis.com/apps/licensing/v1/product/Google-Apps/users*' => Http::response([
            'items' => [
                ['userId' => 'user-1'],
                ['userId' => 'user-2'],
                ['userId' => 'user-3'],
            ],
        ]),
    ]);

    $software = Software::factory()->create([
        'billing_amount' => 120.5,
        'currency' => 'USD',
        'cost_sync_provider' => SoftwareCostSyncProvider::GoogleWorkspace,
        'cost_sync_credentials' => [
            'customer_id' => 'C01234567',
            'service_account_email' => 'sa@example.com',
            'admin_email' => 'admin@example.com',
            'service_account_json' => '{"type":"service_account","client_email":"sa@example.com","private_key":"unused-in-test"}',
        ],
    ]);

    $fetcher = new class extends GoogleWorkspaceCostFetcher
    {
        protected function accessToken(array $credentials): string
        {
            return 'workspace-token';
        }
    };

    $periods = $fetcher->fetch(
        $software,
        CarbonImmutable::now()->subMonths(1),
        CarbonImmutable::now(),
    );

    expect($periods)->toHaveCount(1)
        ->and($periods[0]->amount)->toBe(120.5)
        ->and($periods[0]->seatCount)->toBe(3)
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
