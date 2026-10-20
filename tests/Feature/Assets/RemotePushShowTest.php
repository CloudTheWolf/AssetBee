<?php

use App\Enums\RemotePushTargetStatus;
use App\Enums\RemotePushType;
use App\Models\Hardware;
use App\Models\RemotePush;
use App\Models\RemotePushTarget;
use Livewire\Livewire;

test('remote push show opens target detail modal from device name', function () {
    [, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'LAPTOP-DETAIL-1',
        'serial_number' => 'SN-DETAIL-1',
    ]);

    $push = RemotePush::factory()->create([
        'organization_id' => $organization->id,
        'type' => RemotePushType::BitlockerStatus,
    ]);

    $target = RemotePushTarget::factory()->create([
        'remote_push_id' => $push->id,
        'hardware_id' => $hardware->id,
        'status' => RemotePushTargetStatus::Succeeded,
        'result_json' => [
            'volumes' => [
                [
                    'volume' => 'C:',
                    'state' => 'FullyEncrypted',
                    'hasRecoveryKey' => true,
                ],
            ],
        ],
        'completed_at' => now(),
    ]);

    Livewire::test('pages::assets.remote-pushes.show', ['remotePush' => $push])
        ->set('incompleteOnly', false)
        ->assertSee('LAPTOP-DETAIL-1')
        ->assertDontSeeHtml('href="'.route('assets.hardware.show', $hardware).'"')
        ->call('openTargetDetail', $target->id)
        ->assertSet('showTargetDetailModal', true)
        ->assertSet('selectedTargetId', $target->id)
        ->assertSee('FullyEncrypted')
        ->assertSee('hasRecoveryKey')
        ->assertSee(__('Result'))
        ->call('closeTargetDetailModal')
        ->assertSet('showTargetDetailModal', false)
        ->assertSet('selectedTargetId', null);
});
