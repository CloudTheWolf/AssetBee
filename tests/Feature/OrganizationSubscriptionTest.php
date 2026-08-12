<?php

use App\Actions\Organizations\EnsureUserOrganization;
use App\Actions\Organizations\InviteOrganizationMember;
use App\Enums\OrganizationRole;
use App\Models\AssetDocument;
use App\Models\CloudTenant;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\OrganizationApiKey;
use App\Models\OrganizationSubscription;
use App\Models\Software;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Models\Userware;
use App\Models\UserwareAccount;
use App\Models\Virtualware;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

test('legacy organization subscriptions remain available as a compatibility fallback', function () {
    $organization = Organization::factory()->create();
    $legacyPlan = OrganizationSubscription::factory()->create([
        'organization_id' => $organization->id,
        'plan_name' => 'Legacy Custom',
    ]);

    expect($organization->plan()->first()->is($legacyPlan))->toBeTrue();
});

test('catalogue package limits take precedence over legacy allocated limits', function () {
    $organization = Organization::factory()->create();
    OrganizationSubscription::factory()->create([
        'organization_id' => $organization->id,
        'hardware_limit' => 0,
    ]);
    $package = SubscriptionPackage::factory()->create(['hardware_limit' => 1]);
    $organization->forceFill(['subscription_package_id' => $package->id])->save();

    Hardware::factory()->create(['organization_id' => $organization->id]);

    expect(fn () => Hardware::factory()->create(['organization_id' => $organization->id]))
        ->toThrow(ValidationException::class);
});

test('organizations without a subscription remain unlimited', function () {
    $organization = Organization::factory()->create();

    Hardware::factory()->count(3)->create(['organization_id' => $organization->id]);

    expect($organization->hardwares()->count())->toBe(3);
});

test('resource creation is blocked when each configured subscription limit is reached', function (string $limit, Closure $createResource) {
    $organization = Organization::factory()->create();
    OrganizationSubscription::factory()->create([
        'organization_id' => $organization->id,
        $limit => 0,
    ]);

    $createResource($organization);
})->with([
    'userware' => ['userware_limit', fn (Organization $organization) => Userware::factory()->create(['organization_id' => $organization->id])],
    'hardware' => ['hardware_limit', fn (Organization $organization) => Hardware::factory()->create(['organization_id' => $organization->id])],
    'virtualware' => ['virtualware_limit', fn (Organization $organization) => Virtualware::factory()->create(['organization_id' => $organization->id])],
    'software' => ['software_limit', fn (Organization $organization) => Software::factory()->create(['organization_id' => $organization->id])],
    'cloud tenant' => ['cloud_tenant_limit', fn (Organization $organization) => CloudTenant::factory()->create(['organization_id' => $organization->id])],
    'asset document' => ['asset_document_limit', function (Organization $organization) {
        $hardware = Hardware::factory()->create(['organization_id' => $organization->id]);

        return AssetDocument::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => Hardware::class,
            'documentable_id' => $hardware->id,
        ]);
    }],
    'userware account' => ['userware_account_limit', function (Organization $organization) {
        $userware = Userware::factory()->create(['organization_id' => $organization->id]);

        return UserwareAccount::factory()->create([
            'organization_id' => $organization->id,
            'userware_id' => $userware->id,
        ]);
    }],
])->throws(ValidationException::class);

test('active api key limits are enforced and revoked keys release capacity', function () {
    $organization = Organization::factory()->create();
    OrganizationSubscription::factory()->create([
        'organization_id' => $organization->id,
        'api_key_limit' => 1,
    ]);

    [$firstKey] = OrganizationApiKey::issue($organization, 'First key');

    expect(fn () => OrganizationApiKey::issue($organization, 'Second key'))
        ->toThrow(ValidationException::class);

    $firstKey->update(['revoked_at' => now()]);

    expect(fn () => OrganizationApiKey::issue($organization, 'Replacement key'))
        ->not->toThrow(ValidationException::class);
});

test('member limits include pending invitations', function () {
    Notification::fake();

    [$owner, $organization] = createOrganizationMember();
    OrganizationSubscription::factory()->create([
        'organization_id' => $organization->id,
        'member_limit' => 2,
    ]);

    app(InviteOrganizationMember::class)->handle($organization, $owner, [
        'email' => 'first@example.com',
        'role' => OrganizationRole::Member->value,
    ]);

    expect(fn () => app(InviteOrganizationMember::class)->handle($organization, $owner, [
        'email' => 'second@example.com',
        'role' => OrganizationRole::Member->value,
    ]))->toThrow(ValidationException::class);

    Notification::assertSentOnDemandTimes(OrganizationInvitationNotification::class, 1);
});

test('member limits prevent automatic google domain joins', function () {
    [$owner, $organization] = createOrganizationMember();
    $organization->googleDomains()->create([
        'domain' => 'acme.test',
        'verified_at' => now(),
    ]);
    OrganizationSubscription::factory()->create([
        'organization_id' => $organization->id,
        'member_limit' => 1,
    ]);
    $joiningUser = User::factory()->create(['email' => 'new@acme.test']);

    expect(fn () => app(EnsureUserOrganization::class)->handle($joiningUser, 'acme.test'))
        ->toThrow(ValidationException::class);

    expect($organization->users()->where('users.id', $joiningUser->id)->exists())->toBeFalse()
        ->and($organization->users()->where('users.id', $owner->id)->exists())->toBeTrue();
});
