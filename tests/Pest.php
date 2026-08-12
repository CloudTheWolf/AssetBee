<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function createOrganizationMember(
    OrganizationRole $role = OrganizationRole::Owner,
    ?Organization $organization = null,
    ?User $user = null,
): array {
    $organization ??= Organization::factory()->create();
    $user ??= User::factory()->create();

    $organization->users()->attach($user->id, [
        'role' => $role->value,
    ]);

    return [$user, $organization];
}

function actingAsOrganizationMember(
    OrganizationRole $role = OrganizationRole::Owner,
    ?Organization $organization = null,
    ?User $user = null,
): array {
    [$user, $organization] = createOrganizationMember($role, $organization, $user);

    test()->actingAs($user);
    CurrentOrganization::set($organization);

    return [$user, $organization];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function inventoryPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'schemaVersion' => '1.0',
        'collectedAtUtc' => '2026-08-06T10:50:48.6327507+00:00',
        'platform' => 'windows',
        'type' => 'hardware',
        'hardwareType' => ['status' => 'available', 'value' => 'laptop'],
        'deviceName' => ['status' => 'available', 'value' => 'UKMICHAELH25'],
        'serialNumber' => ['status' => 'available', 'value' => 'PF5FH9HR'],
        'manufacturer' => ['status' => 'available', 'value' => 'Lenovo'],
        'model' => ['status' => 'available', 'value' => 'ThinkPad P1 Gen 7'],
        'operatingSystem' => [
            'status' => 'available',
            'value' => [
                'name' => 'Microsoft Windows 11 Pro',
                'version' => '10.0.26200',
                'displayVersion' => '24H2',
                'build' => '26200',
                'kernel' => null,
            ],
        ],
        'cpu' => [
            'status' => 'available',
            'value' => [
                'model' => 'Intel(R) Core(TM) Ultra 7 155H',
                'logicalProcessors' => 22,
                'physicalCores' => 16,
            ],
        ],
        'memory' => ['status' => 'available', 'value' => ['totalBytes' => 51012263936]],
        'disks' => [
            'status' => 'available',
            'value' => [
                [
                    'name' => 'C:',
                    'mountPoint' => 'C:',
                    'totalBytes' => 1021821579264,
                    'availableBytes' => 470782820352,
                    'fileSystem' => 'NTFS',
                ],
            ],
        ],
        'diskEncryption' => [
            'status' => 'available',
            'value' => [
                [
                    'volume' => 'C:',
                    'technology' => 'BitLocker',
                    'state' => 'FullyEncrypted',
                    'recoveryKeys' => [],
                    'keyProtectors' => [
                        [
                            'keyProtectorId' => '{A8414EAF-085F-4A35-AB76-D4F4B88B5607}',
                            'recoveryKey' => '016148-202037-546898-706926-484627-138688-405482-446105',
                        ],
                    ],
                ],
            ],
        ],
        'domainWorkspace' => [
            'status' => 'available',
            'value' => [
                'domain' => 'WORKGROUP',
                'domainJoined' => false,
                'workspace' => 'CCPro Solutions',
                'workspaceJoined' => true,
            ],
        ],
        'loginProviders' => [
            'status' => 'available',
            'value' => [
                ['name' => 'Windows Credential Provider', 'state' => 'available'],
            ],
        ],
        'antivirus' => [
            'status' => 'available',
            'value' => [
                [
                    'name' => 'WatchGuard EPDR',
                    'state' => 'enabled',
                    'enabled' => true,
                    'upToDate' => true,
                    'detail' => 'Security Center state 0x071000',
                ],
            ],
        ],
        'updates' => [
            'status' => 'available',
            'value' => [
                'installed' => [
                    [
                        'id' => 'KB5062660',
                        'title' => '2026-07 Cumulative Update',
                        'category' => 'Security Updates',
                        'installedAtUtc' => '2026-08-01T10:00:00+00:00',
                        'kbArticle' => 'KB5062660',
                    ],
                ],
                'available' => [],
            ],
        ],
        'sbom' => [
            'status' => 'available',
            'value' => [
                'format' => 'CycloneDX',
                'specVersion' => '1.6',
                'generatedAtUtc' => '2026-08-11T14:44:20+00:00',
                'targets' => [
                    [
                        'bomRef' => 'host',
                        'kind' => 'host',
                        'name' => 'UKMICHAELH25',
                        'components' => [
                            [
                                'name' => 'WatchGuard EPDR',
                                'version' => '8.1.0',
                                'type' => 'application',
                                'purl' => 'pkg:generic/WatchGuard%20EPDR@8.1.0',
                                'publisher' => 'WatchGuard',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ], $overrides);
}
