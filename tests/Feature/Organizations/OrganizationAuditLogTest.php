<?php

use App\Enums\OrganizationRole;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\OrganizationApiKey;
use App\Models\SystemAudit;
use App\Models\User;
use App\Support\CurrentOrganization;
use Livewire\Livewire;

test('organization owners and admins can view the audit log', function (OrganizationRole $role) {
    actingAsOrganizationMember($role);

    $this->get(route('organizations.audit-log'))
        ->assertOk()
        ->assertSee(__('Audit log'));
})->with([
    OrganizationRole::Owner,
    OrganizationRole::Admin,
]);

test('organization members cannot view the audit log', function () {
    actingAsOrganizationMember(OrganizationRole::Member);

    $this->get(route('organizations.audit-log'))->assertForbidden();
});

test('system users can view a customer audit log while in customer context', function () {
    $system = User::factory()->system()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($system);
    CurrentOrganization::set($organization, $system);

    $this->get(route('organizations.audit-log'))
        ->assertOk()
        ->assertSee(__('Audit log'));
});

test('customer asset changes are audited without storing secrets', function () {
    [$owner, $organization] = actingAsOrganizationMember();

    $hardware = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'MacBook Pro',
        'bitlocker_recovery_key' => 'secret-recovery-key',
        'notes' => 'sensitive document-like text',
    ]);

    $audit = SystemAudit::query()
        ->where('target_type', Hardware::class)
        ->where('target_id', $hardware->id)
        ->firstOrFail();

    expect($audit->action)->toBe('hardware.created')
        ->and($audit->actor_id)->toBe($owner->id)
        ->and($audit->actor_name)->toBe($owner->name)
        ->and($audit->organization_id)->toBe($organization->id)
        ->and($audit->summary)->toBe('MacBook Pro')
        ->and($audit->getAttributes())->not->toContain('secret-recovery-key')
        ->and($audit->getAttributes())->not->toContain('sensitive document-like text');
});

test('inventory api changes are audited as the api key', function () {
    $organization = Organization::factory()->create();
    [, $plainTextKey] = OrganizationApiKey::issue($organization, 'Office collector');

    $this->withToken($plainTextKey)
        ->postJson('/api/v1/inventory', inventoryPayload())
        ->assertCreated();

    $hardware = Hardware::query()->sole();
    $audit = SystemAudit::query()
        ->where('target_type', Hardware::class)
        ->where('target_id', $hardware->id)
        ->firstOrFail();

    expect($audit->action)->toBe('hardware.created')
        ->and($audit->actor_id)->toBeNull()
        ->and($audit->actor_name)->toBe('Office collector')
        ->and($audit->organization_id)->toBe($organization->id)
        ->and($audit->summary)->toBe('UKMICHAELH25')
        ->and($audit->getAttributes())->not->toContain('016148-202037');
});

test('audit entries record the client ip forwarded by traefik', function () {
    $user = User::factory()->create();

    $this->withServerVariables([
        'REMOTE_ADDR' => '172.18.0.2',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ])->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('system_audits', [
        'action' => 'auth.login',
        'actor_id' => $user->id,
        'ip_address' => '203.0.113.10',
    ]);
});

test('unauthenticated model changes are not audited', function () {
    Hardware::factory()->create(['name' => 'Orphan Device']);

    expect(SystemAudit::query()->count())->toBe(0);
});

test('the audit log lists this organization only and includes member sign ins', function () {
    [$admin, $organization] = createOrganizationMember(OrganizationRole::Admin);
    $other = Organization::factory()->create();

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertSessionHasNoErrors();

    CurrentOrganization::set($organization, $admin);

    Hardware::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Visible Laptop',
    ]);
    Hardware::factory()->create([
        'organization_id' => $other->id,
        'name' => 'Secret Laptop',
    ]);

    $this->get(route('organizations.audit-log'))
        ->assertOk()
        ->assertSee('Visible Laptop')
        ->assertDontSee('Secret Laptop')
        ->assertSee(__('Signed in'));

    Livewire::test('pages::organizations.audit-log')
        ->set('search', 'Visible Laptop')
        ->assertSee('Visible Laptop')
        ->assertDontSee(__('Signed in'))
        ->set('search', '')
        ->set('action', 'auth')
        ->assertSee(__('Signed in'))
        ->assertDontSee('Visible Laptop');
});
