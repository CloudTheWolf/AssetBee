<?php

use App\Actions\Organizations\EnsureUserOrganization;
use App\Actions\Organizations\SyncOrganizationGoogleDomains;
use App\Actions\Organizations\VerifyOrganizationGoogleDomain;
use App\Contracts\DomainDnsLookup;
use App\Models\Organization;
use App\Models\OrganizationGoogleDomain;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->dns = Mockery::mock(DomainDnsLookup::class);
    $this->app->instance(DomainDnsLookup::class, $this->dns);
});

test('new google domains start unverified with a txt verification token', function () {
    $organization = Organization::factory()->create();

    app(SyncOrganizationGoogleDomains::class)->handle($organization, ['mysite.com']);

    $domain = $organization->googleDomains()->where('domain', 'mysite.com')->first();

    expect($domain)->not->toBeNull()
        ->and($domain->isVerified())->toBeFalse()
        ->and($domain->verification_token)->not->toBeEmpty()
        ->and($domain->txtRecordValue())->toStartWith(OrganizationGoogleDomain::TXT_RECORD_PREFIX);
});

test('users are not auto-assigned when a claimed google domain is unverified', function () {
    $organization = Organization::factory()
        ->withUnverifiedGoogleDomains(['mysite.com'])
        ->create();

    $user = User::factory()->create(['email' => 'ada@mysite.com']);

    $joined = app(EnsureUserOrganization::class)->handle($user);

    expect($joined)->toBeNull()
        ->and($user->fresh()->organizations)->toHaveCount(0)
        ->and(Organization::findByGoogleDomain('mysite.com'))->toBeNull()
        ->and(Organization::findByGoogleDomain('mysite.com', verifiedOnly: false)?->is($organization))->toBeTrue();
});

test('users are auto-assigned after a google domain passes txt verification', function () {
    $organization = Organization::factory()
        ->withUnverifiedGoogleDomains(['mysite.com'])
        ->create();

    $domain = $organization->googleDomains()->firstOrFail();

    $this->dns->shouldReceive('txtRecords')
        ->once()
        ->with('mysite.com')
        ->andReturn([$domain->txtRecordValue()]);

    app(VerifyOrganizationGoogleDomain::class)->handle($domain);

    expect($domain->fresh()->isVerified())->toBeTrue();

    $user = User::factory()->create(['email' => 'ada@mysite.com']);
    $joined = app(EnsureUserOrganization::class)->handle($user);

    expect($joined?->is($organization))->toBeTrue()
        ->and($user->fresh()->organizations)->toHaveCount(1)
        ->and(CurrentOrganization::id())->toBe($organization->id);
});

test('verification fails when the expected txt record is missing', function () {
    $organization = Organization::factory()
        ->withUnverifiedGoogleDomains(['mysite.com'])
        ->create();

    $domain = $organization->googleDomains()->firstOrFail();

    $this->dns->shouldReceive('txtRecords')
        ->once()
        ->with('mysite.com')
        ->andReturn(['unrelated-txt=value']);

    expect(fn () => app(VerifyOrganizationGoogleDomain::class)->handle($domain))
        ->toThrow(ValidationException::class);

    expect($domain->fresh()->isVerified())->toBeFalse()
        ->and($domain->fresh()->verification_last_checked_at)->not->toBeNull();
});

test('organization owners can verify a domain from the manage page', function () {
    [$owner, $organization] = createOrganizationMember();
    CurrentOrganization::set($organization, $owner);

    app(SyncOrganizationGoogleDomains::class)->handle($organization, ['mysite.com']);
    $domain = $organization->googleDomains()->firstOrFail();

    $this->dns->shouldReceive('txtRecords')
        ->once()
        ->with('mysite.com')
        ->andReturn(['prefix '.$domain->txtRecordValue().' suffix']);

    $this->actingAs($owner);

    Livewire::test('pages::organizations.manage')
        ->call('verifyGoogleDomain', $domain->id)
        ->assertHasNoErrors();

    expect($domain->fresh()->isVerified())->toBeTrue();
});

test('syncing domains keeps verification status for unchanged domains', function () {
    $organization = Organization::factory()
        ->withGoogleDomains(['keep.com', 'drop.com'])
        ->create();

    $keep = $organization->googleDomains()->where('domain', 'keep.com')->firstOrFail();
    expect($keep->isVerified())->toBeTrue();

    app(SyncOrganizationGoogleDomains::class)->handle($organization, ['keep.com', 'new.com']);

    expect($keep->fresh()->isVerified())->toBeTrue()
        ->and($organization->googleDomains()->where('domain', 'new.com')->first()?->isVerified())->toBeFalse()
        ->and($organization->googleDomains()->where('domain', 'drop.com')->exists())->toBeFalse();
});
