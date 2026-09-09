<?php

use App\Actions\Assets\AddSoftwareKeys;
use App\Actions\Assets\AssignSoftwareKey;
use App\Actions\Assets\BulkAssignSoftwareSeats;
use App\Actions\Assets\ClearSoftwareCostSyncSettings;
use App\Actions\Assets\DeleteSoftware;
use App\Actions\Assets\DeleteSoftwareKey;
use App\Actions\Assets\SyncAssetCosts;
use App\Actions\Assets\UnassignSoftwareSeat;
use App\Actions\Assets\UpdateSoftware;
use App\Actions\Assets\UpdateSoftwareCostSyncSettings;
use App\Enums\AtlassianCostProduct;
use App\Enums\SoftwareBillingInterval;
use App\Enums\SoftwareCostSyncProvider;
use App\Enums\SoftwareLicenseType;
use App\Enums\SoftwareSeatManagerType;
use App\Enums\SoftwareStatus;
use App\Models\Software;
use App\Models\SoftwareAssignment;
use App\Models\SoftwareKey;
use App\Models\Userware;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Software')] class extends Component {
    use AuthorizesRequests;

    public Software $software;

    public string $name = '';

    public string $vendor = '';

    public string $parent_software_id = '';

    public string $license_type = '';

    public string $total_seats = '';

    public string $seat_manager_type = '';

    public string $seat_manager_userware_id = '';

    public string $seat_manager_department = '';

    public string $status = '';

    public string $expires_at = '';

    public bool $is_recurring = false;

    public string $billing_interval = 'monthly';

    public string $billing_amount = '';

    public string $currency = 'GBP';

    public string $next_billing_at = '';

    public string $notes = '';

    /** @var list<int|string> */
    public array $selectedUserwareIds = [];

    public string $newKeys = '';

    public string $newKeyLabel = '';

    public string $assignKeyId = '';

    public string $assignUserwareId = '';

    public bool $showLicenseKeyModal = false;

    public string $revealedLicenseKey = '';

    public string $revealedLicenseKeyLabel = '';

    public string $cost_sync_provider = 'none';

    public string $cost_organization_id = '';

    public string $cost_api_token = '';

    /** @var list<array{slug: string, label: string, price_per_seat: string, keys: string, name_contains: string, custom: bool}> */
    public array $cost_atlassian_products = [];

    public string $cost_customer_id = '';

    public string $cost_service_account_email = '';

    public string $cost_service_account_json = '';

    public string $cost_admin_email = '';

    public string $cost_team_id = '';

    public string $cost_api_key = '';

    public string $cost_bearer_token = '';

    public string $cost_username = '';

    public string $cost_password = '';

    public string $cost_header_name = '';

    public string $cost_header_value = '';

    public string $cost_request_method = 'GET';

    public string $cost_request_url = '';

    public string $cost_request_auth = 'none';

    public string $cost_request_body = '';

    public string $cost_response_amount_path = 'amount';

    public string $cost_response_currency_path = 'currency';

    public string $cost_response_seats_path = '';

    public string $cost_amount_period = 'month';

    /** @var list<array{name: string, value: string}> */
    public array $cost_request_headers = [];

    public ?string $costSyncError = null;

    public function mount(Software $software): void
    {
        $this->authorize('view', $software);
        abort_unless($software->organization_id === CurrentOrganization::require()->id, 404);

        $this->software = $software->load([
            'assignments.userware',
            'assignments.key',
            'keys.assignment.userware',
            'seatManagerUserware',
            'costSnapshots',
            'parentSoftware',
            'childSoftwares',
        ]);
        $this->fillForm();
        $this->fillCostSyncForm();
    }

    public function fillForm(): void
    {
        $this->name = $this->software->name;
        $this->vendor = (string) ($this->software->vendor ?? '');
        $this->parent_software_id = (string) ($this->software->parent_software_id ?? '');
        $this->license_type = $this->software->license_type->value;
        $this->total_seats = (string) ($this->software->total_seats ?? '');
        $this->seat_manager_type = $this->software->seat_manager_type?->value ?? '';
        $this->seat_manager_userware_id = (string) ($this->software->seat_manager_userware_id ?? '');
        $this->seat_manager_department = (string) ($this->software->seat_manager_department ?? '');
        $this->status = $this->software->status->value;
        $this->expires_at = $this->software->expires_at?->format('Y-m-d') ?? '';
        $this->is_recurring = (bool) $this->software->is_recurring;
        $this->billing_interval = $this->software->billing_interval?->value ?? SoftwareBillingInterval::Monthly->value;
        $this->billing_amount = $this->software->billing_amount !== null ? (string) $this->software->billing_amount : '';
        $this->currency = $this->software->currency ?: 'GBP';
        $this->next_billing_at = $this->software->next_billing_at?->format('Y-m-d') ?? '';
        $this->notes = (string) ($this->software->notes ?? '');
    }

    public function fillCostSyncForm(): void
    {
        $this->cost_sync_provider = $this->software->cost_sync_provider?->value ?? 'none';
        $defaults = $this->software->costSyncCredentialFormDefaults();
        $this->cost_organization_id = (string) ($defaults['organization_id'] ?? '');
        $this->cost_api_token = '';
        $this->cost_atlassian_products = is_array($defaults['products'] ?? null)
            ? array_values($defaults['products'])
            : AtlassianCostProduct::formDefaults(null);
        $this->cost_customer_id = (string) ($defaults['customer_id'] ?? '');
        $this->cost_service_account_email = (string) ($defaults['service_account_email'] ?? '');
        $this->cost_service_account_json = '';
        $this->cost_admin_email = (string) ($defaults['admin_email'] ?? '');
        $this->cost_team_id = (string) ($defaults['team_id'] ?? '');
        $this->cost_api_key = '';
        $this->cost_bearer_token = '';
        $this->cost_username = (string) ($defaults['username'] ?? '');
        $this->cost_password = '';
        $this->cost_header_name = (string) ($defaults['header_name'] ?? '');
        $this->cost_header_value = '';

        $request = $this->software->costSyncRequestFormDefaults();
        $this->cost_request_method = (string) $request['method'];
        $this->cost_request_url = (string) $request['url'];
        $this->cost_request_auth = (string) $request['auth'];
        $this->cost_request_body = (string) $request['body'];
        $this->cost_response_amount_path = (string) $request['response_amount_path'];
        $this->cost_response_currency_path = (string) $request['response_currency_path'];
        $this->cost_response_seats_path = (string) $request['response_seats_path'];
        $this->cost_amount_period = (string) $request['amount_period'];
        $this->cost_request_headers = collect($request['headers'] ?? [])
            ->map(fn ($header): array => [
                'name' => (string) ($header['name'] ?? ''),
                'value' => (string) ($header['value'] ?? ''),
            ])
            ->values()
            ->all();
        $this->costSyncError = $this->software->cost_sync_error;
    }

    public function updatedCostSyncProvider(): void
    {
        if ($this->cost_sync_provider === SoftwareCostSyncProvider::Atlassian->value && $this->cost_atlassian_products === []) {
            $this->cost_atlassian_products = AtlassianCostProduct::formDefaults(null);
        }

        if ($this->cost_request_headers === []) {
            $this->cost_request_headers = [['name' => '', 'value' => '']];
        }
    }

    public function addAtlassianAddon(): void
    {
        $this->cost_atlassian_products[] = AtlassianCostProduct::blankAddonFormRow();
    }

    public function removeAtlassianAddon(int $index): void
    {
        if (! isset($this->cost_atlassian_products[$index]) || ! ($this->cost_atlassian_products[$index]['custom'] ?? false)) {
            return;
        }

        unset($this->cost_atlassian_products[$index]);
        $this->cost_atlassian_products = array_values($this->cost_atlassian_products);
    }

    public function addCostRequestHeader(): void
    {
        $this->cost_request_headers[] = ['name' => '', 'value' => ''];
    }

    public function removeCostRequestHeader(int $index): void
    {
        unset($this->cost_request_headers[$index]);
        $this->cost_request_headers = array_values($this->cost_request_headers);
    }

    public function updatedSeatManagerType(): void
    {
        if ($this->seat_manager_type !== SoftwareSeatManagerType::Userware->value) {
            $this->seat_manager_userware_id = '';
        }

        if ($this->seat_manager_type !== SoftwareSeatManagerType::Department->value) {
            $this->seat_manager_department = '';
        }
    }

    public function save(UpdateSoftware $updateSoftware): void
    {
        $this->authorize('update', $this->software);

        $this->software = $updateSoftware->handle($this->software, [
            'name' => $this->name,
            'vendor' => $this->vendor !== '' ? $this->vendor : null,
            'parent_software_id' => $this->parent_software_id !== '' ? (int) $this->parent_software_id : null,
            'license_type' => $this->license_type,
            'total_seats' => $this->total_seats !== '' ? (int) $this->total_seats : null,
            'seat_manager_type' => $this->seat_manager_type !== '' ? $this->seat_manager_type : null,
            'seat_manager_userware_id' => $this->seat_manager_userware_id !== '' ? (int) $this->seat_manager_userware_id : null,
            'seat_manager_department' => $this->seat_manager_department !== '' ? $this->seat_manager_department : null,
            'status' => $this->status,
            'expires_at' => $this->expires_at !== '' ? $this->expires_at : null,
            'is_recurring' => $this->is_recurring,
            'billing_interval' => $this->is_recurring ? $this->billing_interval : null,
            'billing_amount' => $this->is_recurring && $this->billing_amount !== '' ? $this->billing_amount : null,
            'currency' => $this->currency !== '' ? $this->currency : 'GBP',
            'next_billing_at' => $this->is_recurring && $this->next_billing_at !== '' ? $this->next_billing_at : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ])->load([
            'assignments.userware',
            'assignments.key',
            'keys.assignment.userware',
            'seatManagerUserware',
            'parentSoftware',
            'childSoftwares',
        ]);

        $this->fillForm();

        Flux::toast(variant: 'success', text: __('Software updated.'));
    }

    public function addKeys(AddSoftwareKeys $addSoftwareKeys): void
    {
        $this->authorize('update', $this->software);

        $created = $addSoftwareKeys->handle(
            $this->software,
            $this->newKeys,
            $this->newKeyLabel !== '' ? $this->newKeyLabel : null,
        );

        $this->newKeys = '';
        $this->newKeyLabel = '';
        $this->reloadKeyRelations();

        Flux::toast(
            variant: 'success',
            text: __('Added :count license keys.', ['count' => $created->count()]),
        );
    }

    public function deleteKey(SoftwareKey $key, DeleteSoftwareKey $deleteSoftwareKey): void
    {
        $this->authorize('update', $this->software);
        abort_unless($key->software_id === $this->software->id, 404);

        $deleteSoftwareKey->handle($key);
        $this->reloadKeyRelations();

        Flux::toast(variant: 'success', text: __('License key deleted.'));
    }

    public function revealKey(SoftwareKey $key): void
    {
        $this->authorize('view', $this->software);
        abort_unless($key->software_id === $this->software->id, 404);

        $this->revealedLicenseKey = $key->value;
        $this->revealedLicenseKeyLabel = $key->label ?: $key->maskedValue();
        $this->showLicenseKeyModal = true;
    }

    public function closeLicenseKeyModal(): void
    {
        $this->showLicenseKeyModal = false;
        $this->revealedLicenseKey = '';
        $this->revealedLicenseKeyLabel = '';
    }

    public function assignKey(AssignSoftwareKey $assignSoftwareKey): void
    {
        $this->authorize('assign', $this->software);

        $key = SoftwareKey::query()->findOrFail((int) $this->assignKeyId);
        $userware = Userware::query()->findOrFail((int) $this->assignUserwareId);

        $assignSoftwareKey->handle($this->software, $key, $userware);

        $this->assignKeyId = '';
        $this->assignUserwareId = '';
        $this->reloadKeyRelations();
        unset($this->identities, $this->unassignedKeys);

        Flux::toast(variant: 'success', text: __('License key assigned.'));
    }

    public function assignSeats(BulkAssignSoftwareSeats $bulkAssignSoftwareSeats): void
    {
        $this->authorize('assign', $this->software);

        $result = $bulkAssignSoftwareSeats->handle($this->software, $this->selectedUserwareIds);

        $this->software->load(['assignments.userware', 'assignments.key']);
        $this->selectedUserwareIds = [];
        unset($this->identities);

        Flux::toast(
            variant: 'success',
            text: __('Assigned :count seats.', ['count' => $result['assigned']]),
        );
    }

    public function assignAllSeats(BulkAssignSoftwareSeats $bulkAssignSoftwareSeats): void
    {
        $this->authorize('assign', $this->software);

        $result = $bulkAssignSoftwareSeats->handle(
            $this->software,
            $this->identities->pluck('id')->all(),
        );

        $this->software->load(['assignments.userware', 'assignments.key']);
        $this->selectedUserwareIds = [];
        unset($this->identities);

        Flux::toast(
            variant: 'success',
            text: __('Assigned :count seats.', ['count' => $result['assigned']]),
        );
    }

    public function unassignSeat(SoftwareAssignment $assignment, UnassignSoftwareSeat $unassignSoftwareSeat): void
    {
        $this->authorize('delete', $assignment);
        abort_unless($assignment->software_id === $this->software->id, 404);

        $unassignSoftwareSeat->handle($assignment);
        $this->reloadKeyRelations();
        unset($this->identities, $this->unassignedKeys);

        Flux::toast(variant: 'success', text: __('Assignment removed.'));
    }

    public function delete(DeleteSoftware $deleteSoftware): void
    {
        $this->authorize('delete', $this->software);
        $deleteSoftware->handle($this->software);
        $this->redirect(route('assets.software.index', absolute: false), navigate: true);
    }

    public function saveCostSync(UpdateSoftwareCostSyncSettings $updateSoftwareCostSyncSettings): void
    {
        $this->authorize('update', $this->software);

        $this->software = $updateSoftwareCostSyncSettings->handle($this->software, [
            'cost_sync_provider' => $this->cost_sync_provider,
            'organization_id' => $this->cost_organization_id !== '' ? $this->cost_organization_id : null,
            'api_token' => $this->cost_api_token !== '' ? $this->cost_api_token : null,
            'products' => $this->cost_atlassian_products,
            'customer_id' => $this->cost_customer_id !== '' ? $this->cost_customer_id : null,
            'service_account_email' => $this->cost_service_account_email !== '' ? $this->cost_service_account_email : null,
            'service_account_json' => $this->cost_service_account_json !== '' ? $this->cost_service_account_json : null,
            'admin_email' => $this->cost_admin_email !== '' ? $this->cost_admin_email : null,
            'team_id' => $this->cost_team_id !== '' ? $this->cost_team_id : null,
            'api_key' => $this->cost_api_key !== '' ? $this->cost_api_key : null,
            'bearer_token' => $this->cost_bearer_token !== '' ? $this->cost_bearer_token : null,
            'username' => $this->cost_username !== '' ? $this->cost_username : null,
            'password' => $this->cost_password !== '' ? $this->cost_password : null,
            'header_name' => $this->cost_header_name !== '' ? $this->cost_header_name : null,
            'header_value' => $this->cost_header_value !== '' ? $this->cost_header_value : null,
            'cost_sync_request' => [
                'method' => $this->cost_request_method,
                'url' => $this->cost_request_url,
                'headers' => $this->cost_request_headers,
                'auth' => $this->cost_request_auth,
                'body' => $this->cost_request_body,
                'response_amount_path' => $this->cost_response_amount_path,
                'response_currency_path' => $this->cost_response_currency_path,
                'response_seats_path' => $this->cost_response_seats_path,
                'amount_period' => $this->cost_amount_period,
            ],
        ])->load([
            'assignments.userware',
            'assignments.key',
            'keys.assignment.userware',
            'seatManagerUserware',
            'costSnapshots',
            'parentSoftware',
            'childSoftwares',
        ]);

        $this->fillForm();
        $this->fillCostSyncForm();

        Flux::toast(variant: 'success', text: __('Cost sync settings saved.'));
    }

    public function clearCostSync(ClearSoftwareCostSyncSettings $clearSoftwareCostSyncSettings): void
    {
        $this->authorize('update', $this->software);

        $this->software = $clearSoftwareCostSyncSettings->handle($this->software)
            ->load(['assignments.userware', 'assignments.key', 'keys.assignment.userware', 'seatManagerUserware', 'costSnapshots']);
        $this->fillCostSyncForm();

        Flux::toast(variant: 'success', text: __('Cost sync settings cleared.'));
    }

    public function syncCosts(SyncAssetCosts $syncAssetCosts): void
    {
        $this->authorize('update', $this->software);
        $this->costSyncError = null;

        try {
            $result = $syncAssetCosts->handle($this->software);
        } catch (\Throwable $exception) {
            $this->software->refresh();
            $this->costSyncError = $exception->getMessage();
            $this->fillForm();
            $this->fillCostSyncForm();

            Flux::toast(variant: 'danger', text: __('Cost sync failed.'));

            return;
        }

        $this->software = $this->software->fresh()->load([
            'assignments.userware',
            'assignments.key',
            'keys.assignment.userware',
            'seatManagerUserware',
            'costSnapshots',
            'parentSoftware',
            'childSoftwares',
        ]);
        $this->fillForm();
        $this->fillCostSyncForm();

        Flux::toast(
            variant: 'success',
            text: __('Synced :count cost periods.', ['count' => $result['snapshots']]),
        );
    }

    #[Computed]
    public function parentSoftwareOptions()
    {
        if ($this->software->childSoftwares->isNotEmpty()) {
            return collect();
        }

        return Software::query()
            ->where('organization_id', CurrentOrganization::require()->id)
            ->whereNull('parent_software_id')
            ->whereKeyNot($this->software->id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function identities()
    {
        $assignedIds = $this->software->assignments->pluck('userware_id');

        return Userware::query()
            ->where('organization_id', CurrentOrganization::require()->id)
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function unassignedKeys()
    {
        return $this->software->keys
            ->filter(fn (SoftwareKey $key): bool => ! $key->isAssigned())
            ->values();
    }

    #[Computed]
    public function managerIdentities()
    {
        return Userware::query()
            ->where('organization_id', CurrentOrganization::require()->id)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function departments()
    {
        return Userware::query()
            ->where('organization_id', CurrentOrganization::require()->id)
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');
    }

    private function reloadKeyRelations(): void
    {
        $this->software->load(['assignments.userware', 'assignments.key', 'keys.assignment.userware']);
    }
}; ?>

<div>
<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="flex items-center gap-3">
        <flux:button size="sm" :href="route('assets.software.index')" wire:navigate icon="arrow-left">{{ __('Back') }}</flux:button>
        <div>
            <flux:heading size="xl">{{ $software->name }}</flux:heading>
            <flux:text>
                {{ $software->license_type->label() }}
                @if ($software->parentSoftware)
                    · {{ __('Part of') }}
                    <a href="{{ route('assets.software.show', $software->parentSoftware) }}" class="text-accent" wire:navigate>{{ $software->parentSoftware->name }}</a>
                @endif
                @if ($software->license_type === SoftwareLicenseType::Seat)
                    · {{ $software->assignments->count() }} / {{ $software->total_seats }} {{ __('seats') }}
                @endif
                @if ($software->license_type === SoftwareLicenseType::Key)
                    · {{ $software->keysUsed() }} / {{ $software->keys->count() }} {{ __('keys') }}
                @endif
                @if ($software->seatManagerLabel())
                    · {{ __('Seat manager') }}: {{ $software->seatManagerLabel() }}
                @endif
                @if ($software->is_recurring)
                    · {{ __('Recurring') }}
                @endif
            </flux:text>
        </div>
    </div>

    @if ($software->childSoftwares->isNotEmpty())
        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Sub-software') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Product breakdown under this suite. Costs come from seats × price per seat on sync.') }}</flux:text>
            <ul class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($software->childSoftwares as $child)
                    <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <a href="{{ route('assets.software.show', $child) }}" class="font-medium text-accent" wire:navigate>{{ $child->name }}</a>
                            @if ($child->vendor)
                                <span class="text-sm text-zinc-500"> · {{ $child->vendor }}</span>
                            @endif
                        </div>
                        <div class="text-sm text-zinc-600 dark:text-zinc-300">
                            @if ($child->total_seats !== null)
                                {{ $child->total_seats }} {{ __('seats') }}
                            @endif
                            @if ($child->formattedBillingAmount())
                                <span class="text-zinc-400">·</span>
                                {{ $child->formattedBillingAmount() }}
                                @if ($child->billing_interval)
                                    / {{ strtolower($child->billing_interval->label()) }}
                                @endif
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:input wire:model="name" :label="__('Name')" required :disabled="! auth()->user()->can('update', $software)" />
        <flux:input wire:model="vendor" :label="__('Vendor')" :disabled="! auth()->user()->can('update', $software)" />
        @if ($software->childSoftwares->isEmpty())
            <flux:select wire:model="parent_software_id" :label="__('Parent software')" :description="__('Optional. Nest this product under a suite such as Atlassian.')" :disabled="! auth()->user()->can('update', $software)">
                <option value="">{{ __('None') }}</option>
                @foreach ($this->parentSoftwareOptions as $parentOption)
                    <option value="{{ $parentOption->id }}">{{ $parentOption->name }}</option>
                @endforeach
            </flux:select>
        @endif
        <flux:select wire:model.live="license_type" :label="__('License type')" :disabled="! auth()->user()->can('update', $software)">
            @foreach (SoftwareLicenseType::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>
        @if ($license_type === SoftwareLicenseType::Seat->value)
            <flux:input wire:model="total_seats" type="number" min="1" :label="__('Total seats')" :disabled="! auth()->user()->can('update', $software)" />
        @endif

        <div class="grid gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:select wire:model.live="seat_manager_type" :label="__('Seat manager')" :disabled="! auth()->user()->can('update', $software)">
                <option value="">{{ __('None') }}</option>
                @foreach (SoftwareSeatManagerType::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </flux:select>

            @if ($seat_manager_type === SoftwareSeatManagerType::Userware->value)
                <flux:select wire:model="seat_manager_userware_id" :label="__('User')" :disabled="! auth()->user()->can('update', $software)" required>
                    <option value="">{{ __('Select user') }}</option>
                    @foreach ($this->managerIdentities as $identity)
                        <option value="{{ $identity->id }}">{{ $identity->name }} ({{ $identity->email }})</option>
                    @endforeach
                </flux:select>
            @endif

            @if ($seat_manager_type === SoftwareSeatManagerType::Department->value)
                <flux:select wire:model="seat_manager_department" :label="__('Department')" :disabled="! auth()->user()->can('update', $software)" required>
                    <option value="">{{ __('Select department') }}</option>
                    @foreach ($this->departments as $department)
                        <option value="{{ $department }}">{{ $department }}</option>
                    @endforeach
                    @if ($seat_manager_department !== '' && ! $this->departments->contains($seat_manager_department))
                        <option value="{{ $seat_manager_department }}">{{ $seat_manager_department }}</option>
                    @endif
                </flux:select>
            @endif
        </div>

        <flux:select wire:model="status" :label="__('Status')" :disabled="! auth()->user()->can('update', $software)">
            @foreach (SoftwareStatus::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model="expires_at" type="date" :label="__('Expires at')" :disabled="! auth()->user()->can('update', $software)" />

        <flux:checkbox wire:model.live="is_recurring" :label="__('Recurring subscription')" :disabled="! auth()->user()->can('update', $software)" />
        @if ($is_recurring)
            <div class="grid gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 sm:grid-cols-2">
                <flux:select wire:model="billing_interval" :label="__('Billing interval')" :disabled="! auth()->user()->can('update', $software)">
                    @foreach (SoftwareBillingInterval::cases() as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="next_billing_at" type="date" :label="__('Next billing date')" :disabled="! auth()->user()->can('update', $software)" />
                <flux:input wire:model="billing_amount" type="number" step="0.01" min="0" :label="__('Amount')" :disabled="! auth()->user()->can('update', $software)" />
                <flux:input wire:model="currency" maxlength="3" :label="__('Currency')" :disabled="! auth()->user()->can('update', $software)" />
            </div>
        @endif

        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" :disabled="! auth()->user()->can('update', $software)" />
        @can('update', $software)
            <div class="flex justify-between">
                <flux:button variant="danger" type="button" wire:click="delete" wire:confirm="{{ __('Delete this software?') }}">{{ __('Delete') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        @endcan
    </form>

    <form wire:submit="saveCostSync" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <div>
            <flux:heading size="lg">{{ __('Cost sync') }}</flux:heading>
            <flux:text>
                {{ __('Pull licence and seat costs from a provider. Successful syncs update billing and keep monthly history.') }}
                @if ($software->cost_synced_at)
                    · {{ __('Last synced :time.', ['time' => $software->cost_synced_at->diffForHumans()]) }}
                @endif
            </flux:text>
        </div>

        <flux:select wire:model.live="cost_sync_provider" :label="__('Provider')" :disabled="! auth()->user()->can('update', $software)">
            @foreach (SoftwareCostSyncProvider::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>

        @if ($cost_sync_provider === SoftwareCostSyncProvider::Atlassian->value)
            <flux:text>
                {{ __('Use an organization API key (Admin → Settings → API keys). Sync counts users with product access, multiplies by your price per seat, and updates sub-software for each product.') }}
            </flux:text>
            <flux:input wire:model="cost_organization_id" :label="__('Atlassian organization ID')" :disabled="! auth()->user()->can('update', $software)" />
            <flux:input wire:model="cost_api_token" type="password" :label="__('Organization API key')" :description="$software->hasCostSyncCredentials() ? __('Leave blank to keep the existing key.') : null" :disabled="! auth()->user()->can('update', $software)" />
            <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div>
                    <flux:heading size="sm">{{ __('Atlassian products') }}</flux:heading>
                    <flux:text>{{ __('Set price per seat in :currency. Product keys map to Admin API product_access values.', ['currency' => $currency]) }}</flux:text>
                </div>
                @foreach ($cost_atlassian_products as $index => $product)
                    @continue(! empty($product['custom']))
                    <div class="grid gap-3 sm:grid-cols-2" wire:key="atlassian-product-{{ $product['slug'] ?: $index }}">
                        <flux:input
                            wire:model="cost_atlassian_products.{{ $index }}.price_per_seat"
                            type="number"
                            step="0.01"
                            min="0"
                            :label="$product['label']"
                            :disabled="! auth()->user()->can('update', $software)"
                        />
                        <flux:input
                            wire:model="cost_atlassian_products.{{ $index }}.keys"
                            :label="__('Product keys')"
                            :description="__('Comma-separated Admin API keys')"
                            :disabled="! auth()->user()->can('update', $software)"
                        />
                        <input type="hidden" wire:model="cost_atlassian_products.{{ $index }}.slug" />
                        <input type="hidden" wire:model="cost_atlassian_products.{{ $index }}.label" />
                        <input type="hidden" wire:model="cost_atlassian_products.{{ $index }}.custom" />
                        <input type="hidden" wire:model="cost_atlassian_products.{{ $index }}.name_contains" />
                    </div>
                @endforeach
            </div>

            <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="sm">{{ __('Marketplace add-ons') }}</flux:heading>
                        <flux:text>{{ __('Third-party apps such as Git Integration for Jira. Match by product key and/or name from Admin API product_access.') }}</flux:text>
                    </div>
                    @can('update', $software)
                        <flux:button type="button" size="sm" wire:click="addAtlassianAddon">{{ __('Add add-on') }}</flux:button>
                    @endcan
                </div>

                @foreach ($cost_atlassian_products as $index => $product)
                    @continue(empty($product['custom']))
                    <div class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" wire:key="atlassian-addon-{{ $product['slug'] ?: $index }}">
                        <div class="flex items-start justify-between gap-3">
                            <flux:input
                                wire:model="cost_atlassian_products.{{ $index }}.label"
                                :label="__('Add-on name')"
                                :disabled="! auth()->user()->can('update', $software)"
                                class="flex-1"
                            />
                            @can('update', $software)
                                <flux:button type="button" size="sm" variant="ghost" wire:click="removeAtlassianAddon({{ $index }})" class="mt-7">{{ __('Remove') }}</flux:button>
                            @endcan
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <flux:input
                                wire:model="cost_atlassian_products.{{ $index }}.price_per_seat"
                                type="number"
                                step="0.01"
                                min="0"
                                :label="__('Price per seat')"
                                :disabled="! auth()->user()->can('update', $software)"
                            />
                            <flux:input
                                wire:model="cost_atlassian_products.{{ $index }}.keys"
                                :label="__('Product keys')"
                                :description="__('Comma-separated')"
                                :disabled="! auth()->user()->can('update', $software)"
                            />
                            <flux:input
                                wire:model="cost_atlassian_products.{{ $index }}.name_contains"
                                :label="__('Name contains')"
                                :description="__('Fallback match on product name')"
                                :disabled="! auth()->user()->can('update', $software)"
                            />
                        </div>
                        <input type="hidden" wire:model="cost_atlassian_products.{{ $index }}.slug" />
                        <input type="hidden" wire:model="cost_atlassian_products.{{ $index }}.custom" />
                    </div>
                @endforeach

                @if (collect($cost_atlassian_products)->contains(fn (array $product): bool => ! empty($product['custom'])))
                @else
                    <flux:text>{{ __('No Marketplace add-ons configured yet.') }}</flux:text>
                @endif
            </div>
        @elseif ($cost_sync_provider === SoftwareCostSyncProvider::GoogleWorkspace->value)
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:text>{{ __('Connect with a service account that has domain-wide delegation.') }}</flux:text>
                <flux:modal.trigger name="google-workspace-setup">
                    <flux:button type="button" size="sm" variant="ghost">{{ __('Setup guide') }}</flux:button>
                </flux:modal.trigger>
            </div>
            <flux:input wire:model="cost_customer_id" :label="__('Customer ID')" :disabled="! auth()->user()->can('update', $software)" />
            <flux:input wire:model="cost_service_account_email" :label="__('Service account email')" :disabled="! auth()->user()->can('update', $software)" />
            <flux:input wire:model="cost_admin_email" type="email" :label="__('Admin email')" :disabled="! auth()->user()->can('update', $software)" />
            <flux:textarea wire:model="cost_service_account_json" rows="5" :label="__('Service account JSON')" :description="$software->hasCostSyncCredentials() ? __('Leave blank to keep the existing key.') : null" :disabled="! auth()->user()->can('update', $software)" />
        @elseif ($cost_sync_provider === SoftwareCostSyncProvider::Cursor->value)
            <flux:input wire:model="cost_team_id" :label="__('Team ID')" :description="__('Optional. Admin API keys are already scoped to a team.')" :disabled="! auth()->user()->can('update', $software)" />
            <flux:input wire:model="cost_api_key" type="password" :label="__('Admin API key')" :description="$software->hasCostSyncCredentials() ? __('Leave blank to keep the existing key.') : __('Create an admin-scoped key from Cursor Dashboard → API Keys.')" :disabled="! auth()->user()->can('update', $software)" />
        @elseif ($cost_sync_provider === SoftwareCostSyncProvider::CustomHttp->value)
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="cost_request_method" :label="__('Method')" :disabled="! auth()->user()->can('update', $software)">
                    @foreach (['GET', 'POST', 'PUT', 'PATCH'] as $method)
                        <option value="{{ $method }}">{{ $method }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="cost_request_url" :label="__('URL')" :disabled="! auth()->user()->can('update', $software)" />
            </div>
            <flux:select wire:model="cost_request_auth" :label="__('Authentication')" :disabled="! auth()->user()->can('update', $software)">
                <option value="none">{{ __('None') }}</option>
                <option value="bearer">{{ __('Bearer token') }}</option>
                <option value="basic">{{ __('Basic auth') }}</option>
                <option value="header">{{ __('Custom header') }}</option>
            </flux:select>
            @if ($cost_request_auth === 'bearer')
                <flux:input wire:model="cost_bearer_token" type="password" :label="__('Bearer token')" :disabled="! auth()->user()->can('update', $software)" />
            @elseif ($cost_request_auth === 'basic')
                <flux:input wire:model="cost_username" :label="__('Username')" :disabled="! auth()->user()->can('update', $software)" />
                <flux:input wire:model="cost_password" type="password" :label="__('Password')" :disabled="! auth()->user()->can('update', $software)" />
            @elseif ($cost_request_auth === 'header')
                <flux:input wire:model="cost_header_name" :label="__('Header name')" :disabled="! auth()->user()->can('update', $software)" />
                <flux:input wire:model="cost_header_value" type="password" :label="__('Header value')" :disabled="! auth()->user()->can('update', $software)" />
            @endif
            <div class="flex flex-col gap-3">
                <flux:heading size="sm">{{ __('Headers') }}</flux:heading>
                @foreach ($cost_request_headers as $index => $header)
                    <div class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]" wire:key="cost-header-{{ $index }}">
                        <flux:input wire:model="cost_request_headers.{{ $index }}.name" :label="__('Name')" :disabled="! auth()->user()->can('update', $software)" />
                        <flux:input wire:model="cost_request_headers.{{ $index }}.value" :label="__('Value')" :disabled="! auth()->user()->can('update', $software)" />
                        @can('update', $software)
                            <flux:button type="button" size="sm" wire:click="removeCostRequestHeader({{ $index }})">{{ __('Remove') }}</flux:button>
                        @endcan
                    </div>
                @endforeach
                @can('update', $software)
                    <flux:button type="button" size="sm" wire:click="addCostRequestHeader">{{ __('Add header') }}</flux:button>
                @endcan
            </div>
            <flux:textarea wire:model="cost_request_body" rows="4" :label="__('Body')" :disabled="! auth()->user()->can('update', $software)" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="cost_response_amount_path" :label="__('Amount JSON path')" :disabled="! auth()->user()->can('update', $software)" />
                <flux:input wire:model="cost_response_currency_path" :label="__('Currency JSON path')" :disabled="! auth()->user()->can('update', $software)" />
                <flux:input wire:model="cost_response_seats_path" :label="__('Seats JSON path')" :disabled="! auth()->user()->can('update', $software)" />
                <flux:select wire:model="cost_amount_period" :label="__('Amount period')" :disabled="! auth()->user()->can('update', $software)">
                    <option value="month">{{ __('Current month') }}</option>
                    <option value="as_reported">{{ __('As reported') }}</option>
                </flux:select>
            </div>
        @endif

        @if ($costSyncError || $software->cost_sync_error)
            <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:border-red-700 dark:bg-red-950/30 dark:text-red-200">
                {{ $costSyncError ?: $software->cost_sync_error }}
            </div>
        @endif

        @can('update', $software)
            <div class="flex flex-wrap justify-between gap-3">
                <div class="flex gap-2">
                    @if ($software->hasCostSyncConfigured())
                        <flux:button type="button" variant="danger" wire:click="clearCostSync" wire:confirm="{{ __('Clear cost sync settings?') }}">{{ __('Clear') }}</flux:button>
                        <flux:button type="button" wire:click="syncCosts" wire:loading.attr="disabled">{{ __('Sync now') }}</flux:button>
                    @endif
                </div>
                <flux:button variant="primary" type="submit">{{ __('Save cost sync') }}</flux:button>
            </div>
        @endcan

        @if ($software->costSnapshots->isNotEmpty())
            <div class="flex flex-col gap-2">
                <flux:heading size="sm">{{ __('Recent cost history') }}</flux:heading>
                <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @foreach ($software->costSnapshots->take(12) as $snapshot)
                        <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                            <span>{{ $snapshot->period_start->format('Y-m') }} · {{ $snapshot->provider->label() }}</span>
                            <span>
                                {{ $snapshot->formattedAmount() }}
                                @if ($snapshot->seat_count !== null)
                                    · {{ $snapshot->seat_count }} {{ __('seats') }}
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </form>

    <livewire:asset-documents :documentable="$software" :key="'software-docs-'.$software->id" />

    @if ($software->license_type === SoftwareLicenseType::Key)
        <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('License keys') }}</flux:heading>
            @can('update', $software)
                <form wire:submit="addKeys" class="flex flex-col gap-3">
                    <flux:input wire:model="newKeyLabel" :label="__('Label (optional)')" :placeholder="__('Applies to all keys added below')" />
                    <flux:textarea wire:model="newKeys" rows="4" :label="__('Keys')" :placeholder="__('One key per line')" required />
                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit" data-test="add-software-keys">{{ __('Add keys') }}</flux:button>
                    </div>
                </form>
            @endcan

            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($software->keys as $key)
                    <li class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between" wire:key="software-key-{{ $key->id }}">
                        <div class="min-w-0">
                            <div class="font-medium font-mono">{{ $key->maskedValue() }}</div>
                            <flux:text>
                                @if ($key->label)
                                    {{ $key->label }} ·
                                @endif
                                @if ($key->assignment)
                                    {{ __('Assigned to') }} {{ $key->assignment->userware->name }}
                                @else
                                    {{ __('Unassigned') }}
                                @endif
                            </flux:text>
                        </div>
                        <div class="flex gap-2">
                            <flux:button size="sm" variant="ghost" wire:click="revealKey({{ $key->id }})">{{ __('Reveal') }}</flux:button>
                            @can('update', $software)
                                @unless ($key->isAssigned())
                                    <flux:button size="sm" variant="danger" wire:click="deleteKey({{ $key->id }})" wire:confirm="{{ __('Delete this license key?') }}">
                                        {{ __('Delete') }}
                                    </flux:button>
                                @endunless
                            @endcan
                        </div>
                    </li>
                @empty
                    <li class="py-3"><flux:text>{{ __('No license keys yet.') }}</flux:text></li>
                @endforelse
            </ul>
        </div>

        @can('assign', $software)
            <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Key assignments') }}</flux:heading>

                @if ($this->unassignedKeys->isNotEmpty() && $this->identities->isNotEmpty())
                    <form wire:submit="assignKey" class="flex flex-col gap-3">
                        <flux:select wire:model="assignKeyId" :label="__('License key')" required>
                            <option value="">{{ __('Select key') }}</option>
                            @foreach ($this->unassignedKeys as $key)
                                <option value="{{ $key->id }}">
                                    {{ $key->label ? $key->label.' — ' : '' }}{{ $key->maskedValue() }}
                                </option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="assignUserwareId" :label="__('User')" required>
                            <option value="">{{ __('Select user') }}</option>
                            @foreach ($this->identities as $identity)
                                <option value="{{ $identity->id }}">{{ $identity->name }} ({{ $identity->email }})</option>
                            @endforeach
                        </flux:select>
                        <div class="flex justify-end">
                            <flux:button variant="primary" type="submit" data-test="assign-software-key">{{ __('Assign key') }}</flux:button>
                        </div>
                    </form>
                @else
                    <flux:text>
                        @if ($software->keys->isEmpty())
                            {{ __('Add license keys before assigning them.') }}
                        @elseif ($this->unassignedKeys->isEmpty())
                            {{ __('All keys are already assigned.') }}
                        @else
                            {{ __('All identities already have a key, or none exist yet.') }}
                        @endif
                    </flux:text>
                @endif

                <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($software->assignments as $assignment)
                        <li class="flex items-center justify-between py-3">
                            <div>
                                <div class="font-medium">{{ $assignment->userware->name }}</div>
                                <flux:text>
                                    {{ $assignment->userware->email }}
                                    @if ($assignment->key)
                                        · {{ $assignment->key->label ?: $assignment->key->maskedValue() }}
                                    @endif
                                </flux:text>
                            </div>
                            <flux:button size="sm" variant="danger" wire:click="unassignSeat({{ $assignment->id }})" wire:confirm="{{ __('Remove this key assignment?') }}">
                                {{ __('Unassign') }}
                            </flux:button>
                        </li>
                    @empty
                        <li class="py-3"><flux:text>{{ __('No keys assigned.') }}</flux:text></li>
                    @endforelse
                </ul>
            </div>
        @endcan
    @else
        @can('assign', $software)
            <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Seat assignments') }}</flux:heading>

                @if ($this->identities->isNotEmpty())
                    <form wire:submit="assignSeats" class="flex flex-col gap-3">
                        <flux:select
                            wire:model="selectedUserwareIds"
                            multiple
                            :size="8"
                            :label="__('Identities')"
                            class="!h-auto min-h-40 py-1"
                        >
                            @foreach ($this->identities as $identity)
                                <option value="{{ $identity->id }}">{{ $identity->name }} ({{ $identity->email }})</option>
                            @endforeach
                        </flux:select>

                        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                            <flux:button
                                type="button"
                                variant="ghost"
                                data-test="assign-all-seats"
                                wire:click="assignAllSeats"
                                wire:confirm="{{ __('Assign this software to all available identities?') }}"
                            >
                                {{ __('Assign to all userware') }}
                            </flux:button>
                            <flux:button variant="primary" type="submit" data-test="bulk-assign-seats">
                                {{ __('Assign selected') }}
                            </flux:button>
                        </div>
                    </form>
                @else
                    <flux:text>{{ __('All identities already have a seat, or none exist yet.') }}</flux:text>
                @endif

                <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($software->assignments as $assignment)
                        <li class="flex items-center justify-between py-3">
                            <div>
                                <div class="font-medium">{{ $assignment->userware->name }}</div>
                                <flux:text>{{ $assignment->userware->email }}</flux:text>
                            </div>
                            <flux:button size="sm" variant="danger" wire:click="unassignSeat({{ $assignment->id }})" wire:confirm="{{ __('Remove this seat?') }}">
                                {{ __('Unassign') }}
                            </flux:button>
                        </li>
                    @empty
                        <li class="py-3"><flux:text>{{ __('No seats assigned.') }}</flux:text></li>
                    @endforelse
                </ul>
            </div>
        @endcan
    @endif
</div>

<flux:modal wire:model="showLicenseKeyModal" class="max-w-lg" @close="closeLicenseKeyModal">
    <div class="space-y-6">
        <div class="space-y-2">
            <flux:heading size="lg">{{ __('License key') }}</flux:heading>
            @if (filled($revealedLicenseKeyLabel))
                <flux:text>{{ $revealedLicenseKeyLabel }}</flux:text>
            @endif
        </div>

        <div class="break-all rounded-lg border border-zinc-200 p-4 font-mono text-sm dark:border-zinc-700">
            {{ $revealedLicenseKey }}
        </div>

        <div class="flex justify-end">
            <flux:button variant="primary" wire:click="closeLicenseKeyModal">{{ __('Close') }}</flux:button>
        </div>
    </div>
</flux:modal>

@include('pages.assets.partials.google-workspace-setup-modal')

