<?php

namespace Database\Seeders;

use App\Enums\HardwareCategory;
use App\Enums\HardwareStatus;
use App\Enums\OrganizationRole;
use App\Enums\SoftwareLicenseType;
use App\Enums\SoftwareStatus;
use App\Enums\UserwareStatus;
use App\Enums\VirtualwareCategory;
use App\Enums\VirtualwareProvider;
use App\Enums\VirtualwareStatus;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\Software;
use App\Models\User;
use App\Models\Userware;
use App\Models\Virtualware;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $organization = Organization::factory()->create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
        ]);

        $organization->googleDomains()->create(['domain' => 'acme.com']);
        $organization->googleDomains()->create(['domain' => 'acme.co.uk']);

        $organization->users()->attach($user->id, [
            'role' => OrganizationRole::Owner->value,
        ]);

        $ada = Userware::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@acme.com',
            'department' => 'Engineering',
            'status' => UserwareStatus::Active,
        ]);

        $grace = Userware::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Grace Hopper',
            'email' => 'grace@acme.com',
            'department' => 'IT',
            'status' => UserwareStatus::Active,
        ]);

        $laptop = Hardware::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'MacBook Pro 16',
            'asset_tag' => 'HW-1001',
            'category' => HardwareCategory::Laptop,
            'status' => HardwareStatus::Assigned,
            'assigned_userware_id' => $ada->id,
            'manufacturer' => 'Apple',
        ]);

        Hardware::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Dell Monitor 27',
            'asset_tag' => 'HW-1002',
            'category' => HardwareCategory::Monitor,
            'status' => HardwareStatus::Available,
        ]);

        Virtualware::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'prod-api-01',
            'provider' => VirtualwareProvider::Aws,
            'category' => VirtualwareCategory::Vm,
            'status' => VirtualwareStatus::Running,
            'host_hardware_id' => $laptop->id,
            'assigned_userware_id' => $grace->id,
        ]);

        Software::factory()->seatBased(25)->create([
            'organization_id' => $organization->id,
            'name' => 'JetBrains All Products',
            'vendor' => 'JetBrains',
            'license_type' => SoftwareLicenseType::Seat,
            'status' => SoftwareStatus::Active,
        ]);
    }
}
