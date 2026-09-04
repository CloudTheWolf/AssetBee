<?php

use App\Actions\Assets\ClearHardwareProxmoxCredentials;
use App\Actions\Assets\UpdateHardwareProxmoxCredentials;
use App\Models\Hardware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('owners can save proxmox credentials on a vm host', function () {
    [, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->vmHost()->create([
        'organization_id' => $organization->id,
    ]);

    Livewire::test('pages::assets.hardware.show', ['hardware' => $hardware])
        ->set('proxmox_api_url', 'https://pve.example:8006')
        ->set('proxmox_token_id', 'assetbee@pve!inventory')
        ->set('proxmox_token_secret', 'super-secret-token')
        ->set('proxmox_verify_tls', true)
        ->set('proxmox_node', 'pve1')
        ->call('saveProxmoxCredentials')
        ->assertHasNoErrors();

    $hardware->refresh();

    expect($hardware->hasProxmoxCredentials())->toBeTrue()
        ->and($hardware->proxmox_credentials['api_url'])->toBe('https://pve.example:8006')
        ->and($hardware->proxmox_credentials['token_id'])->toBe('assetbee@pve!inventory')
        ->and($hardware->proxmox_credentials['token_secret'])->toBe('super-secret-token')
        ->and($hardware->proxmox_credentials['node'])->toBe('pve1')
        ->and($hardware->proxmox_credentials['verify_tls'])->toBeTrue();
});

test('updating proxmox credentials can keep the existing secret', function () {
    [, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->withProxmoxCredentials([
        'token_secret' => 'original-secret',
        'node' => 'pve1',
    ])->create([
        'organization_id' => $organization->id,
    ]);

    app(UpdateHardwareProxmoxCredentials::class)->handle($hardware, [
        'api_url' => 'https://pve-new.example:8006',
        'token_id' => 'assetbee@pve!inventory',
        'token_secret' => null,
        'verify_tls' => false,
        'node' => 'pve2',
    ]);

    expect($hardware->fresh()->proxmox_credentials)->toMatchArray([
        'api_url' => 'https://pve-new.example:8006',
        'token_id' => 'assetbee@pve!inventory',
        'token_secret' => 'original-secret',
        'verify_tls' => false,
        'node' => 'pve2',
    ]);
});

test('first-time proxmox credentials require a token secret', function () {
    [, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->vmHost()->create([
        'organization_id' => $organization->id,
    ]);

    app(UpdateHardwareProxmoxCredentials::class)->handle($hardware, [
        'api_url' => 'https://pve.example:8006',
        'token_id' => 'assetbee@pve!inventory',
        'token_secret' => null,
        'verify_tls' => true,
        'node' => 'pve1',
    ]);
})->throws(ValidationException::class);

test('proxmox credentials are rejected for non vm hosts', function () {
    [, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'is_vm_host' => false,
    ]);

    app(UpdateHardwareProxmoxCredentials::class)->handle($hardware, [
        'api_url' => 'https://pve.example:8006',
        'token_id' => 'assetbee@pve!inventory',
        'token_secret' => 'secret',
        'verify_tls' => true,
        'node' => 'pve1',
    ]);
})->throws(ValidationException::class);

test('owners can clear proxmox credentials', function () {
    [, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->withProxmoxCredentials()->create([
        'organization_id' => $organization->id,
    ]);

    app(ClearHardwareProxmoxCredentials::class)->handle($hardware);

    expect($hardware->fresh()->hasProxmoxCredentials())->toBeFalse()
        ->and($hardware->fresh()->proxmox_credentials)->toBeNull();
});

test('proxmox credentials are encrypted at rest', function () {
    [, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->vmHost()->create([
        'organization_id' => $organization->id,
    ]);

    $hardware->update([
        'proxmox_credentials' => [
            'api_url' => 'https://pve.example:8006',
            'token_id' => 'assetbee@pve!inventory',
            'token_secret' => 'super-secret-token',
            'verify_tls' => true,
            'node' => 'pve1',
        ],
    ]);

    $raw = DB::table('hardwares')->where('id', $hardware->id)->value('proxmox_credentials');

    expect($raw)->not->toBeNull()
        ->and($raw)->not->toContain('super-secret-token')
        ->and($hardware->fresh()->proxmox_credentials['token_secret'])->toBe('super-secret-token');
});
