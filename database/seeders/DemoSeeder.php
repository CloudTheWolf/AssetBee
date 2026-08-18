<?php

namespace Database\Seeders;

use App\Enums\BitLockerStatus;
use App\Enums\CloudTenantProvider;
use App\Enums\CloudTenantStatus;
use App\Enums\HardwareCategory;
use App\Enums\HardwareOperatingSystem;
use App\Enums\HardwareStatus;
use App\Enums\OrganizationRole;
use App\Enums\SoftwareBillingInterval;
use App\Enums\SoftwareLicenseType;
use App\Enums\SoftwareSeatManagerType;
use App\Enums\SoftwareStatus;
use App\Enums\UserwareStatus;
use App\Enums\VirtualwareCategory;
use App\Enums\VirtualwareProvider;
use App\Enums\VirtualwareStatus;
use App\Models\CloudTenant;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\Software;
use App\Models\SoftwareAssignment;
use App\Models\User;
use App\Models\Userware;
use App\Models\UserwareAccount;
use App\Models\Virtualware;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a complete demonstration organization.
     */
    public function run(): void
    {
        $demoUser = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => Hash::make('assetbeedemo'),
        ]);

        $organization = Organization::factory()->create([
            'name' => 'Example Company',
            'slug' => 'example-company',
        ]);

        $organization->users()->attach($demoUser, [
            'role' => OrganizationRole::Owner->value,
        ]);

        $alex = $this->createUserware($organization, [
            'name' => 'Alex Morgan',
            'email' => 'alex.morgan@example.com',
            'employee_id' => 'EMP-1001',
            'department' => 'Engineering',
            'status' => UserwareStatus::Active,
        ]);

        $priya = $this->createUserware($organization, [
            'name' => 'Priya Shah',
            'email' => 'priya.shah@example.com',
            'employee_id' => 'EMP-1002',
            'department' => 'Finance',
            'status' => UserwareStatus::Active,
        ]);

        $jordan = $this->createUserware($organization, [
            'name' => 'Jordan Lee',
            'email' => 'jordan.lee@example.com',
            'employee_id' => 'EMP-1003',
            'department' => 'Operations',
            'status' => UserwareStatus::Inactive,
        ]);

        $this->createHardware($organization, [
            'name' => 'Alex - ThinkPad X1 Carbon',
            'asset_tag' => 'HW-1001',
            'serial_number' => 'PF-DEMO-1001',
            'manufacturer' => 'Lenovo',
            'model' => 'ThinkPad X1 Carbon Gen 12',
            'operating_system' => HardwareOperatingSystem::Windows11,
            'cpu' => 'Intel Core Ultra 7 155U',
            'ram_gb' => 32,
            'storage_gb' => 1024,
            'bitlocker_status' => BitLockerStatus::Enabled,
            'category' => HardwareCategory::Laptop,
            'status' => HardwareStatus::Assigned,
            'assigned_userware_id' => $alex->id,
        ]);

        $this->createHardware($organization, [
            'name' => 'Priya - MacBook Air',
            'asset_tag' => 'HW-1002',
            'serial_number' => 'FV-DEMO-1002',
            'manufacturer' => 'Apple',
            'model' => 'MacBook Air 15-inch M3',
            'operating_system' => HardwareOperatingSystem::Macos,
            'cpu' => 'Apple M3',
            'ram_gb' => 16,
            'storage_gb' => 512,
            'bitlocker_status' => BitLockerStatus::NotApplicable,
            'category' => HardwareCategory::Laptop,
            'status' => HardwareStatus::Assigned,
            'assigned_userware_id' => $priya->id,
        ]);

        $this->createHardware($organization, [
            'name' => 'Meeting Room Display',
            'asset_tag' => 'HW-1003',
            'serial_number' => 'DELL-DEMO-1003',
            'manufacturer' => 'Dell',
            'model' => 'UltraSharp U2723QE',
            'category' => HardwareCategory::Monitor,
            'status' => HardwareStatus::Available,
        ]);

        $virtualizationHost = $this->createHardware($organization, [
            'name' => 'London Virtualization Host',
            'asset_tag' => 'HW-1004',
            'serial_number' => 'HPE-DEMO-1004',
            'manufacturer' => 'HPE',
            'model' => 'ProLiant DL380 Gen11',
            'operating_system' => HardwareOperatingSystem::Linux,
            'cpu' => '2 x Intel Xeon Gold 5418Y',
            'ram_gb' => 256,
            'storage_gb' => 4096,
            'bitlocker_status' => BitLockerStatus::NotApplicable,
            'is_vm_host' => true,
            'category' => HardwareCategory::Server,
            'status' => HardwareStatus::Available,
        ]);

        $this->createHardware($organization, [
            'name' => 'Office Core Switch',
            'asset_tag' => 'HW-1005',
            'serial_number' => 'CISCO-DEMO-1005',
            'manufacturer' => 'Cisco',
            'model' => 'Catalyst 9300',
            'category' => HardwareCategory::Network,
            'status' => HardwareStatus::Maintenance,
        ]);

        $awsTenant = CloudTenant::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Example Company AWS',
            'provider' => CloudTenantProvider::Aws,
            'external_id' => '123456789012',
            'domain' => null,
            'status' => CloudTenantStatus::Active,
            'notes' => 'Demo cloud account without stored credentials.',
        ]);

        Virtualware::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'dev-app-01',
            'provider' => VirtualwareProvider::Vmware,
            'external_id' => 'vm-demo-1001',
            'category' => VirtualwareCategory::Vm,
            'status' => VirtualwareStatus::Running,
            'host_hardware_id' => $virtualizationHost->id,
            'assigned_userware_id' => $alex->id,
            'private_ip' => '10.20.0.11',
            'disks' => [['device_name' => 'sda', 'size_gb' => 120, 'encrypted' => true]],
        ]);

        Virtualware::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'finance-reports-01',
            'provider' => VirtualwareProvider::Aws,
            'external_id' => 'i-0demo1002',
            'category' => VirtualwareCategory::Vm,
            'status' => VirtualwareStatus::Running,
            'cloud_tenant_id' => $awsTenant->id,
            'assigned_userware_id' => $priya->id,
            'region' => 'eu-west-2',
            'instance_type' => 't3.medium',
            'private_ip' => '10.30.1.20',
            'termination_protection' => true,
        ]);

        Virtualware::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'legacy-import-worker',
            'provider' => VirtualwareProvider::Other,
            'external_id' => 'container-demo-1003',
            'category' => VirtualwareCategory::Container,
            'status' => VirtualwareStatus::Stopped,
        ]);

        $microsoft365 = $this->createSoftware($organization, [
            'name' => 'Microsoft 365 Business Premium',
            'vendor' => 'Microsoft',
            'license_type' => SoftwareLicenseType::Seat,
            'total_seats' => 25,
            'seat_manager_type' => SoftwareSeatManagerType::Userware,
            'seat_manager_userware_id' => $priya->id,
            'billing_interval' => SoftwareBillingInterval::Monthly,
            'billing_amount' => 462.50,
        ]);

        $adobe = $this->createSoftware($organization, [
            'name' => 'Adobe Creative Cloud',
            'vendor' => 'Adobe',
            'license_type' => SoftwareLicenseType::Seat,
            'total_seats' => 8,
            'seat_manager_type' => SoftwareSeatManagerType::Department,
            'seat_manager_department' => 'Operations',
            'billing_interval' => SoftwareBillingInterval::Yearly,
            'billing_amount' => 4799.00,
        ]);

        $jira = $this->createSoftware($organization, [
            'name' => 'Jira Software',
            'vendor' => 'Atlassian',
            'license_type' => SoftwareLicenseType::Site,
            'total_seats' => null,
            'billing_interval' => SoftwareBillingInterval::Monthly,
            'billing_amount' => 180.00,
        ]);

        foreach ([$alex, $priya, $jordan] as $userware) {
            $this->assignSoftware($microsoft365, $userware);
        }

        $this->assignSoftware($adobe, $alex);
        $this->assignSoftware($jira, $alex);

        $this->createSoftwareAccount($organization, $alex, $microsoft365);
        $this->createSoftwareAccount($organization, $priya, $microsoft365);
        $this->createSoftwareAccount($organization, $alex, $jira);

        UserwareAccount::factory()->create([
            'organization_id' => $organization->id,
            'userware_id' => $jordan->id,
            'software_id' => null,
            'site_name' => 'GitHub',
            'site_url' => 'https://github.com',
            'username' => 'jordan-example',
            'notes' => 'External account retained for offboarding review.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUserware(Organization $organization, array $attributes): Userware
    {
        return Userware::factory()->create([
            'organization_id' => $organization->id,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createHardware(Organization $organization, array $attributes): Hardware
    {
        return Hardware::factory()->create([
            'organization_id' => $organization->id,
            'purchased_at' => now()->subYear()->toDateString(),
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSoftware(Organization $organization, array $attributes): Software
    {
        return Software::factory()->create([
            'organization_id' => $organization->id,
            'status' => SoftwareStatus::Active,
            'expires_at' => now()->addYear()->toDateString(),
            'is_recurring' => true,
            'currency' => 'GBP',
            'next_billing_at' => now()->addMonth()->toDateString(),
            ...$attributes,
        ]);
    }

    private function assignSoftware(Software $software, Userware $userware): void
    {
        SoftwareAssignment::factory()->create([
            'software_id' => $software->id,
            'userware_id' => $userware->id,
            'assigned_at' => now()->subMonth(),
            'notes' => 'Demo seat assignment.',
        ]);
    }

    private function createSoftwareAccount(
        Organization $organization,
        Userware $userware,
        Software $software,
    ): void {
        UserwareAccount::factory()->create([
            'organization_id' => $organization->id,
            'userware_id' => $userware->id,
            'software_id' => $software->id,
            'site_name' => null,
            'site_url' => null,
            'username' => $userware->email,
            'notes' => 'Demo software account.',
        ]);
    }
}
