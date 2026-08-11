<?php

use App\Actions\Organizations\InviteOrganizationMember;
use App\Enums\OrganizationRole;
use App\Enums\UserAccountType;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\Support\Registration;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('owners can invite members to their organization', function () {
    Notification::fake();

    [$owner, $organization] = actingAsOrganizationMember(OrganizationRole::Owner);

    Livewire::test('pages::organizations.manage')
        ->set('invite_email', 'new.member@example.com')
        ->set('invite_role', OrganizationRole::Member->value)
        ->call('invite')
        ->assertHasNoErrors();

    $invitation = OrganizationInvitation::query()->where('email', 'new.member@example.com')->first();

    expect($invitation)->not->toBeNull()
        ->and($invitation->organization_id)->toBe($organization->id)
        ->and($invitation->invited_by)->toBe($owner->id);

    Notification::assertSentOnDemand(OrganizationInvitationNotification::class);
});

test('admins cannot invite members', function () {
    actingAsOrganizationMember(OrganizationRole::Admin);

    Livewire::test('pages::organizations.manage')
        ->call('invite')
        ->assertForbidden();
});

test('invited users can register and join when self hosted', function () {
    config(['app.cloud_hosted' => false]);
    User::factory()->create();

    [$owner, $organization] = createOrganizationMember(OrganizationRole::Owner);

    $invitation = app(InviteOrganizationMember::class)->handle($organization, $owner, [
        'email' => 'invitee@example.com',
        'role' => OrganizationRole::Member->value,
    ]);

    Registration::rememberInvitation($invitation);

    $this->post(route('register.store'), [
        'name' => 'Invitee',
        'email' => 'invitee@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'invitee@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->account_type)->toBe(UserAccountType::Customer)
        ->and($organization->users()->where('users.id', $user->id)->exists())->toBeTrue()
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('invitation page stores pending invitation for registration', function () {
    [$owner, $organization] = createOrganizationMember();

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'invited_by' => $owner->id,
        'email' => 'guest@example.com',
    ]);

    $this->get(route('invitations.show', $invitation->token))
        ->assertOk()
        ->assertSee($organization->name);

    expect(Registration::pendingInvitation()?->is($invitation))->toBeTrue();
});
