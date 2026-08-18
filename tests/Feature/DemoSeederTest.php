<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\SoftwareAssignment;
use App\Models\User;
use App\Models\UserwareAccount;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('database seeder creates the complete demo organization', function () {
    $this->seed(DatabaseSeeder::class);

    $demoUser = User::query()
        ->where('email', 'demo@example.com')
        ->firstOrFail();
    $organization = Organization::query()
        ->where('slug', 'example-company')
        ->firstOrFail();

    expect($demoUser->name)->toBe('Demo User')
        ->and(Hash::check('assetbeedemo', $demoUser->password))->toBeTrue()
        ->and($organization->name)->toBe('Example Company')
        ->and($organization->users()
            ->whereKey($demoUser->id)
            ->wherePivot('role', OrganizationRole::Owner->value)
            ->exists())->toBeTrue()
        ->and($organization->userwares()->count())->toBe(3)
        ->and($organization->hardwares()->count())->toBe(5)
        ->and($organization->virtualwares()->count())->toBe(3)
        ->and($organization->softwares()->count())->toBe(3)
        ->and($organization->cloudTenants()->count())->toBe(1)
        ->and(SoftwareAssignment::query()->count())->toBe(5)
        ->and(UserwareAccount::query()
            ->where('organization_id', $organization->id)
            ->count())->toBe(4);
});
