<?php

use App\Actions\Organizations\UpdateOrganization;
use App\Actions\Organizations\UpdateOrganizationMemberRole;
use App\Enums\OrganizationRole;
use App\Models\AssetDocument;
use App\Models\CloudTenant;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\Software;
use App\Models\SoftwareAssignment;
use App\Models\SystemAudit;
use App\Models\User;
use App\Models\Userware;
use App\Models\UserwareAccount;
use App\Models\Virtualware;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

test('cloud system users can access the customer console and customers cannot', function () {
    $organization = Organization::factory()->withGoogleDomains('acme.test')->create(['name' => 'Acme Corp']);
    $system = User::factory()->system()->create();

    $this->actingAs($system)
        ->get(route('system.customers'))
        ->assertOk()
        ->assertSee('Acme Corp')
        ->assertSee('acme.test');

    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('system.customers'))
        ->assertForbidden();
});

test('system routes and permissions are disabled in self hosted mode', function () {
    config(['app.self_hosted' => true]);

    $system = User::factory()->system()->create();
    $organization = Organization::factory()->create();
    $hardware = Hardware::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($system);
    Session::put(CurrentOrganization::SESSION_KEY, $organization->id);

    $this->get(route('system.customers'))->assertForbidden();

    expect($system->hasSystemAccess())->toBeFalse()
        ->and(Gate::forUser($system)->allows('view', $hardware))->toBeFalse()
        ->and(CurrentOrganization::get())->toBeNull();
});

test('system users without a customer context are sent to the customer console', function () {
    $system = User::factory()->system()->create();

    $this->actingAs($system)
        ->get(route('dashboard'))
        ->assertRedirect(route('system.customers'));
});

test('system users explicitly enter and exit customer context with audit entries', function () {
    $system = User::factory()->system()->create();
    $organization = Organization::factory()->create(['name' => 'Acme Corp']);

    $this->actingAs($system)
        ->post(route('organizations.switch', $organization))
        ->assertRedirect(route('dashboard'));

    expect(CurrentOrganization::id())->toBe($organization->id);
    $this->assertDatabaseHas('system_audits', [
        'actor_id' => $system->id,
        'organization_id' => $organization->id,
        'action' => 'customer_context.entered',
        'target_type' => Organization::class,
        'target_id' => $organization->id,
    ]);

    $this->delete(route('system.customers.exit'))
        ->assertRedirect(route('system.customers'));

    expect(CurrentOrganization::id())->toBeNull();
    $this->assertDatabaseHas('system_audits', [
        'actor_id' => $system->id,
        'organization_id' => $organization->id,
        'action' => 'customer_context.exited',
    ]);
});

test('customers cannot select unrelated organizations', function () {
    [$customer] = createOrganizationMember();
    $unrelated = Organization::factory()->create();

    $this->actingAs($customer)
        ->post(route('organizations.switch', $unrelated))
        ->assertForbidden();
});

test('system context authorizes selected customer records and rejects cross customer records', function () {
    $system = User::factory()->system()->create();
    $selected = Organization::factory()->create();
    $other = Organization::factory()->create();

    $selectedModels = [
        Userware::factory()->create(['organization_id' => $selected->id]),
        Hardware::factory()->create(['organization_id' => $selected->id]),
        Virtualware::factory()->create(['organization_id' => $selected->id]),
        Software::factory()->create(['organization_id' => $selected->id]),
        CloudTenant::factory()->create(['organization_id' => $selected->id]),
    ];
    $otherModels = [
        Userware::factory()->create(['organization_id' => $other->id]),
        Hardware::factory()->create(['organization_id' => $other->id]),
        Virtualware::factory()->create(['organization_id' => $other->id]),
        Software::factory()->create(['organization_id' => $other->id]),
        CloudTenant::factory()->create(['organization_id' => $other->id]),
    ];
    $selectedHardware = $selectedModels[1];
    $otherHardware = $otherModels[1];
    $selectedDocument = AssetDocument::factory()->create([
        'organization_id' => $selected->id,
        'documentable_type' => Hardware::class,
        'documentable_id' => $selectedHardware->id,
    ]);
    $otherDocument = AssetDocument::factory()->create([
        'organization_id' => $other->id,
        'documentable_type' => Hardware::class,
        'documentable_id' => $otherHardware->id,
    ]);
    $selectedAccount = UserwareAccount::factory()->create([
        'organization_id' => $selected->id,
        'userware_id' => $selectedModels[0]->id,
    ]);
    $otherAccount = UserwareAccount::factory()->create([
        'organization_id' => $other->id,
        'userware_id' => $otherModels[0]->id,
    ]);
    $selectedAssignment = SoftwareAssignment::factory()->create([
        'software_id' => $selectedModels[3]->id,
        'userware_id' => $selectedModels[0]->id,
    ]);
    $otherAssignment = SoftwareAssignment::factory()->create([
        'software_id' => $otherModels[3]->id,
        'userware_id' => $otherModels[0]->id,
    ]);

    $this->actingAs($system);
    CurrentOrganization::set($selected, $system);

    foreach ($selectedModels as $model) {
        expect(Gate::forUser($system)->allows('view', $model))->toBeTrue()
            ->and(Gate::forUser($system)->allows('update', $model))->toBeTrue();
    }

    foreach ($otherModels as $model) {
        expect(Gate::forUser($system)->allows('view', $model))->toBeFalse()
            ->and(Gate::forUser($system)->allows('update', $model))->toBeFalse();
    }

    expect(Gate::forUser($system)->allows('view', $selectedDocument))->toBeTrue()
        ->and(Gate::forUser($system)->allows('delete', $selectedDocument))->toBeTrue()
        ->and(Gate::forUser($system)->allows('view', $otherDocument))->toBeFalse()
        ->and(Gate::forUser($system)->allows('delete', $otherDocument))->toBeFalse()
        ->and(Gate::forUser($system)->allows('update', $selectedAccount))->toBeTrue()
        ->and(Gate::forUser($system)->allows('update', $otherAccount))->toBeFalse()
        ->and(Gate::forUser($system)->allows('delete', $selectedAssignment))->toBeTrue()
        ->and(Gate::forUser($system)->allows('delete', $otherAssignment))->toBeFalse();

    expect(Gate::forUser($system)->allows('update', $selected))->toBeTrue()
        ->and(Gate::forUser($system)->allows('invite', $selected))->toBeTrue()
        ->and(Gate::forUser($system)->allows('manageApiKeys', $selected))->toBeFalse()
        ->and(Gate::forUser($system)->allows('delete', $selected))->toBeFalse();
});

test('system users never appear in customer member lists', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $system = User::factory()->system()->create();
    $organization->users()->attach($owner->id, ['role' => OrganizationRole::Owner->value]);
    $organization->users()->attach($system->id, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($system);
    CurrentOrganization::set($organization, $system);

    $members = Livewire::test('pages::organizations.manage')->get('members');

    expect($members->contains($owner))->toBeTrue()
        ->and($members->contains($system))->toBeFalse();
});

test('system asset changes create metadata only audit entries', function () {
    $system = User::factory()->system()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($system);
    CurrentOrganization::set($organization, $system);

    $hardware = Hardware::factory()->create([
        'organization_id' => $organization->id,
        'bitlocker_recovery_key' => 'secret-recovery-key',
        'notes' => 'sensitive document-like text',
    ]);

    $audit = SystemAudit::query()->where('target_type', Hardware::class)->where('target_id', $hardware->id)->firstOrFail();

    expect($audit->action)->toBe('hardware.created')
        ->and($audit->actor_id)->toBe($system->id)
        ->and($audit->organization_id)->toBe($organization->id)
        ->and($audit->getAttributes())->not->toContain('secret-recovery-key')
        ->and($audit->getAttributes())->not->toContain('sensitive document-like text');
});

test('system settings and membership changes are audited', function () {
    $system = User::factory()->system()->create();
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member->id, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($system);
    CurrentOrganization::set($organization, $system);

    app(UpdateOrganization::class)->handle($organization, [
        'name' => 'Renamed Customer',
        'google_hosted_domains' => [],
    ]);
    app(UpdateOrganizationMemberRole::class)->handle($organization, $member, OrganizationRole::Admin);

    $this->assertDatabaseHas('system_audits', [
        'actor_id' => $system->id,
        'organization_id' => $organization->id,
        'action' => 'organization.updated',
        'target_id' => $organization->id,
    ]);
    $this->assertDatabaseHas('system_audits', [
        'actor_id' => $system->id,
        'organization_id' => $organization->id,
        'action' => 'organization_member.role_updated',
        'target_type' => User::class,
        'target_id' => $member->id,
    ]);
});
