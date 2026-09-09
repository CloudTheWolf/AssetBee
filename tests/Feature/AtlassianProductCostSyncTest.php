<?php

use App\Actions\Assets\SyncAssetCosts;
use App\Actions\Assets\UpdateSoftwareCostSyncSettings;
use App\Enums\SoftwareCostSyncProvider;
use App\Enums\SoftwareLicenseType;
use App\Models\CostSnapshot;
use App\Models\Software;
use App\Services\Costs\AtlassianCostFetcher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('atlassian fetcher calculates cost from seats times price per product', function () {
    Http::fake([
        'https://api.atlassian.com/admin/v1/orgs/org-1/users' => Http::response([
            'data' => [
                [
                    'account_id' => 'u1',
                    'account_status' => 'active',
                    'product_access' => [
                        ['key' => 'jira-software', 'name' => 'Jira'],
                        ['key' => 'confluence', 'name' => 'Confluence'],
                    ],
                ],
                [
                    'account_id' => 'u2',
                    'account_status' => 'active',
                    'product_access' => [
                        ['key' => 'jira-software', 'name' => 'Jira'],
                        ['key' => 'com.bigbrassband.jira-git-plugin', 'name' => 'Git Integration for Jira'],
                    ],
                ],
                [
                    'account_id' => 'u3',
                    'account_status' => 'active',
                    'product_access' => [
                        ['key' => 'compass', 'name' => 'Compass'],
                    ],
                ],
            ],
            'links' => ['next' => null],
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
                'compass' => ['price_per_seat' => 2],
            ],
            'addons' => [
                [
                    'slug' => 'addon_git_integration_for_jira',
                    'label' => 'Git Integration for Jira',
                    'price_per_seat' => 4,
                    'keys' => ['com.bigbrassband.jira-git-plugin'],
                    'name_contains' => 'Git Integration',
                ],
            ],
        ],
    ]);

    $periods = app(AtlassianCostFetcher::class)->fetch(
        $software,
        CarbonImmutable::now()->subMonth(),
        CarbonImmutable::now(),
    );

    $products = collect($periods[0]->meta['products'])->keyBy('slug');

    expect($periods[0]->amount)->toBe(31.0) // (2*10)+(1*5)+(1*2)+(1*4)
        ->and($periods[0]->seatCount)->toBe(3)
        ->and($products['jira']['seats'])->toBe(2)
        ->and($products['jira']['amount'])->toBe(20.0)
        ->and($products['confluence']['seats'])->toBe(1)
        ->and($products['confluence']['amount'])->toBe(5.0)
        ->and($products['compass']['seats'])->toBe(1)
        ->and($products['addon_git_integration_for_jira']['seats'])->toBe(1)
        ->and($products['addon_git_integration_for_jira']['custom'])->toBeTrue();
});

test('atlassian sync creates sub-software for marketplace add-ons', function () {
    [, $organization] = actingAsOrganizationMember();

    Http::fake([
        'https://api.atlassian.com/admin/v1/orgs/org-1/users' => Http::response([
            'data' => [
                [
                    'account_id' => 'u1',
                    'account_status' => 'active',
                    'product_access' => [
                        ['key' => 'jira-software', 'name' => 'Jira'],
                        ['key' => 'confluence', 'name' => 'Confluence'],
                    ],
                ],
                [
                    'account_id' => 'u2',
                    'account_status' => 'active',
                    'product_access' => [
                        ['key' => 'jira-software', 'name' => 'Jira'],
                        ['key' => 'com.bigbrassband.jira-git-plugin', 'name' => 'Git Integration for Jira'],
                    ],
                ],
            ],
            'links' => ['next' => null],
        ]),
    ]);

    $parent = Software::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Atlassian',
        'vendor' => 'Atlassian',
        'currency' => 'GBP',
        'cost_sync_provider' => SoftwareCostSyncProvider::Atlassian,
        'cost_sync_credentials' => [
            'organization_id' => 'org-1',
            'api_token' => 'org-api-key',
            'products' => [
                'jira' => ['price_per_seat' => 8.5],
                'confluence' => ['price_per_seat' => 6],
                'compass' => ['price_per_seat' => 0],
            ],
            'addons' => [
                [
                    'slug' => 'addon_git_integration_for_jira',
                    'label' => 'Git Integration for Jira',
                    'price_per_seat' => 3,
                    'keys' => ['com.bigbrassband.jira-git-plugin'],
                    'name_contains' => 'Git Integration',
                ],
            ],
        ],
    ]);

    $result = app(SyncAssetCosts::class)->handle($parent);
    $parent->refresh()->load('childSoftwares');

    $jira = $parent->childSoftwares->firstWhere('name', 'Jira');
    $confluence = $parent->childSoftwares->firstWhere('name', 'Confluence');
    $git = $parent->childSoftwares->firstWhere('name', 'Git Integration for Jira');

    expect($result['amount'])->toBe(26.0) // 2*8.5 + 1*6 + 1*3
        ->and((float) $parent->billing_amount)->toBe(26.0)
        ->and($parent->childSoftwares)->toHaveCount(4)
        ->and($jira)->not->toBeNull()
        ->and($jira->total_seats)->toBe(2)
        ->and((float) $jira->billing_amount)->toBe(17.0)
        ->and($jira->license_type)->toBe(SoftwareLicenseType::Seat)
        ->and($confluence->total_seats)->toBe(1)
        ->and((float) $confluence->billing_amount)->toBe(6.0)
        ->and($git)->not->toBeNull()
        ->and($git->vendor)->toBe('Atlassian Marketplace')
        ->and($git->total_seats)->toBe(1)
        ->and((float) $git->billing_amount)->toBe(3.0)
        ->and($parent->cost_sync_credentials['products']['jira']['child_software_id'])->toBe($jira->id)
        ->and(collect($parent->cost_sync_credentials['addons'])->firstWhere('slug', 'addon_git_integration_for_jira')['child_software_id'])->toBe($git->id)
        ->and(CostSnapshot::query()->where('costable_id', $jira->id)->count())->toBe(1);
});

test('owners can save marketplace add-ons on atlassian cost sync settings', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Livewire::test('pages::assets.software.show', ['software' => $software])
        ->set('cost_sync_provider', SoftwareCostSyncProvider::Atlassian->value)
        ->set('cost_organization_id', 'atlassian-org')
        ->set('cost_api_token', 'secret-token')
        ->set('cost_atlassian_products', [
            ['slug' => 'jira', 'label' => 'Jira', 'price_per_seat' => '9.99', 'keys' => 'jira-software', 'name_contains' => '', 'custom' => false],
            ['slug' => 'confluence', 'label' => 'Confluence', 'price_per_seat' => '5.50', 'keys' => 'confluence', 'name_contains' => '', 'custom' => false],
            ['slug' => 'compass', 'label' => 'Compass', 'price_per_seat' => '', 'keys' => 'compass', 'name_contains' => '', 'custom' => false],
            [
                'slug' => '',
                'label' => 'Git Integration for Jira',
                'price_per_seat' => '3',
                'keys' => 'com.bigbrassband.jira-git-plugin',
                'name_contains' => 'Git Integration',
                'custom' => true,
            ],
        ])
        ->call('saveCostSync')
        ->assertHasNoErrors();

    $software->refresh();
    $addon = collect($software->cost_sync_credentials['addons'])->first();

    expect((float) $software->cost_sync_credentials['products']['jira']['price_per_seat'])->toBe(9.99)
        ->and((float) $software->cost_sync_credentials['products']['confluence']['price_per_seat'])->toBe(5.5)
        ->and($addon['label'])->toBe('Git Integration for Jira')
        ->and((float) $addon['price_per_seat'])->toBe(3.0)
        ->and($addon['keys'])->toContain('com.bigbrassband.jira-git-plugin');
});

test('owners can add and remove marketplace add-on rows in the ui', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Livewire::test('pages::assets.software.show', ['software' => $software])
        ->set('cost_sync_provider', SoftwareCostSyncProvider::Atlassian->value)
        ->call('addAtlassianAddon')
        ->assertSet('cost_atlassian_products.3.custom', true)
        ->call('removeAtlassianAddon', 3)
        ->assertCount('cost_atlassian_products', 3);
});

test('atlassian settings require at least one product price', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->create([
        'organization_id' => $organization->id,
    ]);

    expect(fn () => app(UpdateSoftwareCostSyncSettings::class)->handle($software, [
        'cost_sync_provider' => SoftwareCostSyncProvider::Atlassian->value,
        'organization_id' => 'org-1',
        'api_token' => 'token',
        'products' => [
            ['slug' => 'jira', 'price_per_seat' => '', 'custom' => false],
            ['slug' => 'confluence', 'price_per_seat' => '0', 'custom' => false],
        ],
    ]))->toThrow(ValidationException::class);
});

test('legacy git integration product credentials migrate into addons', function () {
    $software = Software::factory()->create([
        'cost_sync_provider' => SoftwareCostSyncProvider::Atlassian,
        'cost_sync_credentials' => [
            'organization_id' => 'org-1',
            'api_token' => 'token',
            'products' => [
                'jira' => ['price_per_seat' => 1],
                'git_integration_for_jira' => [
                    'price_per_seat' => 4,
                    'keys' => ['com.bigbrassband.jira-git-plugin'],
                    'child_software_id' => 99,
                ],
            ],
        ],
    ]);

    $defaults = $software->costSyncCredentialFormDefaults();
    $addon = collect($defaults['products'])->firstWhere('custom', true);

    expect($addon)->not->toBeNull()
        ->and($addon['label'])->toBe('Git Integration for Jira')
        ->and($addon['price_per_seat'])->toBe('4');
});
