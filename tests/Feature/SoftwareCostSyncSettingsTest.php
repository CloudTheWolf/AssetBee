<?php

use App\Actions\Assets\ClearSoftwareCostSyncSettings;
use App\Actions\Assets\UpdateSoftwareCostSyncSettings;
use App\Enums\SoftwareCostSyncProvider;
use App\Models\Software;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('owners can save atlassian cost sync settings on software', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Livewire::test('pages::assets.software.show', ['software' => $software])
        ->set('cost_sync_provider', SoftwareCostSyncProvider::Atlassian->value)
        ->set('cost_organization_id', 'atlassian-org')
        ->set('cost_api_token', 'secret-token')
        ->call('saveCostSync')
        ->assertHasNoErrors();

    $software->refresh();

    expect($software->cost_sync_provider)->toBe(SoftwareCostSyncProvider::Atlassian)
        ->and($software->cost_sync_credentials['organization_id'])->toBe('atlassian-org')
        ->and($software->cost_sync_credentials['api_token'])->toBe('secret-token')
        ->and($software->cost_sync_credentials)->not->toHaveKey('email')
        ->and($organization->fresh()->cost_sync_enabled)->toBeTrue();
});

test('software cost sync credentials are encrypted at rest', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->create([
        'organization_id' => $organization->id,
    ]);

    app(UpdateSoftwareCostSyncSettings::class)->handle($software, [
        'cost_sync_provider' => SoftwareCostSyncProvider::Cursor->value,
        'team_id' => 'team-123',
        'api_key' => 'cursor-secret',
    ]);

    $raw = DB::table('softwares')->where('id', $software->id)->value('cost_sync_credentials');

    expect($raw)->not->toContain('cursor-secret')
        ->and($software->fresh()->cost_sync_credentials['api_key'])->toBe('cursor-secret');
});

test('clearing software cost sync removes credentials and request config', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->create([
        'organization_id' => $organization->id,
        'cost_sync_provider' => SoftwareCostSyncProvider::CustomHttp,
        'cost_sync_credentials' => ['bearer_token' => 'token'],
        'cost_sync_request' => [
            'method' => 'GET',
            'url' => 'https://example.test',
            'response_amount_path' => 'amount',
        ],
    ]);

    app(ClearSoftwareCostSyncSettings::class)->handle($software);

    expect($software->fresh()->cost_sync_provider)->toBe(SoftwareCostSyncProvider::None)
        ->and($software->fresh()->cost_sync_credentials)->toBeNull()
        ->and($software->fresh()->cost_sync_request)->toBeNull();
});

test('custom http cost sync requires a url and amount path', function () {
    [, $organization] = actingAsOrganizationMember();

    $software = Software::factory()->create([
        'organization_id' => $organization->id,
    ]);

    app(UpdateSoftwareCostSyncSettings::class)->handle($software, [
        'cost_sync_provider' => SoftwareCostSyncProvider::CustomHttp->value,
        'cost_sync_request' => [
            'method' => 'GET',
            'url' => '',
            'response_amount_path' => '',
        ],
    ]);
})->throws(ValidationException::class);
