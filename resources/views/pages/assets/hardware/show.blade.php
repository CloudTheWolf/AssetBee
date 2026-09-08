<?php

use App\Actions\Assets\AssignHardware;
use App\Actions\Assets\ClearHardwareProxmoxCredentials;
use App\Actions\Assets\DeleteHardware;
use App\Actions\Assets\DiscoverProxmoxGuests;
use App\Actions\Assets\ImportProxmoxGuests;
use App\Actions\Assets\UpdateHardware;
use App\Actions\Assets\UpdateHardwareProxmoxCredentials;
use App\Enums\HardwareCategory;
use App\Enums\HardwareOperatingSystem;
use App\Enums\HardwareStatus;
use App\Enums\VirtualwareProvider;
use App\Livewire\Concerns\DisplaysCollectedInventory;
use App\Models\Hardware;
use App\Models\Userware;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Hardware')] class extends Component {
    use AuthorizesRequests, DisplaysCollectedInventory;

    public Hardware $hardware;

    public string $name = '';

    public string $asset_tag = '';

    public string $serial_number = '';

    public string $manufacturer = '';

    public string $model = '';

    public string $category = '';

    public string $status = '';

    public string $operating_system = '';

    public string $cpu = '';

    public string $ram_gb = '';

    public string $storage_gb = '';

    public string $bitlocker_status = '';

    public string $bitlocker_recovery_key = '';

    public bool $is_vm_host = false;

    public string $purchased_at = '';

    public string $notes = '';

    public string $assigned_userware_id = '';

    public string $sbomSearch = '';

    public string $proxmox_api_url = '';

    public string $proxmox_token_id = '';

    public string $proxmox_token_secret = '';

    public bool $proxmox_verify_tls = true;

    public string $proxmox_node = '';

    /** @var list<string> */
    public array $selectedExternalIds = [];

    public ?string $discoveryError = null;

    public function mount(Hardware $hardware): void
    {
        $this->authorize('view', $hardware);
        abort_unless($hardware->organization_id === CurrentOrganization::require()->id, 404);

        $this->hardware = $hardware->load(['assignedUserware', 'virtualwares']);
        $this->fillForm();
        $this->fillProxmoxCredentialForm();
    }

    public function updatedCategory(): void
    {
        if (! $this->selectedCategory()?->canBeVmHost()) {
            $this->is_vm_host = false;
        }
    }

    public function updatedOperatingSystem(): void
    {
        if (! $this->selectedOperatingSystem()?->isWindows()) {
            $this->bitlocker_status = '';
            $this->bitlocker_recovery_key = '';
        }
    }

    public function fillForm(): void
    {
        $this->name = $this->hardware->name;
        $this->asset_tag = (string) ($this->hardware->asset_tag ?? '');
        $this->serial_number = (string) ($this->hardware->serial_number ?? '');
        $this->manufacturer = (string) ($this->hardware->manufacturer ?? '');
        $this->model = (string) ($this->hardware->model ?? '');
        $this->category = $this->hardware->category->value;
        $this->status = $this->hardware->status->value;
        $this->operating_system = $this->hardware->operating_system?->value ?? '';
        $this->cpu = (string) ($this->hardware->cpu ?? '');
        $this->ram_gb = $this->hardware->ram_gb !== null ? (string) $this->hardware->ram_gb : '';
        $this->storage_gb = $this->hardware->storage_gb !== null ? (string) $this->hardware->storage_gb : '';
        $this->bitlocker_status = $this->hardware->bitlocker_status?->value ?? '';
        $this->bitlocker_recovery_key = (string) ($this->hardware->bitlocker_recovery_key ?? '');
        $this->is_vm_host = (bool) $this->hardware->is_vm_host;
        $this->purchased_at = $this->hardware->purchased_at?->format('Y-m-d') ?? '';
        $this->notes = (string) ($this->hardware->notes ?? '');
        $this->assigned_userware_id = (string) ($this->hardware->assigned_userware_id ?? '');
    }

    public function fillProxmoxCredentialForm(): void
    {
        $defaults = $this->hardware->proxmoxCredentialFormDefaults();

        $this->proxmox_api_url = $defaults['api_url'];
        $this->proxmox_token_id = $defaults['token_id'];
        $this->proxmox_token_secret = $defaults['token_secret'];
        $this->proxmox_verify_tls = $defaults['verify_tls'];
        $this->proxmox_node = $defaults['node'];
    }

    public function save(UpdateHardware $updateHardware): void
    {
        $this->authorize('update', $this->hardware);

        $this->hardware = $updateHardware->handle($this->hardware, [
            'name' => $this->name,
            'asset_tag' => $this->asset_tag !== '' ? $this->asset_tag : null,
            'serial_number' => $this->serial_number !== '' ? $this->serial_number : null,
            'manufacturer' => $this->manufacturer !== '' ? $this->manufacturer : null,
            'model' => $this->model !== '' ? $this->model : null,
            'category' => $this->category,
            'status' => $this->status,
            'operating_system' => $this->operating_system !== '' ? $this->operating_system : null,
            'cpu' => $this->cpu !== '' ? $this->cpu : null,
            'ram_gb' => $this->ram_gb !== '' ? (int) $this->ram_gb : null,
            'storage_gb' => $this->storage_gb !== '' ? (int) $this->storage_gb : null,
            'bitlocker_status' => $this->bitlocker_status !== '' ? $this->bitlocker_status : null,
            'bitlocker_recovery_key' => $this->bitlocker_recovery_key !== '' ? $this->bitlocker_recovery_key : null,
            'is_vm_host' => $this->is_vm_host,
            'purchased_at' => $this->purchased_at !== '' ? $this->purchased_at : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ])->load(['assignedUserware', 'virtualwares']);

        $this->fillForm();
        $this->fillProxmoxCredentialForm();

        if (! $this->hardware->is_vm_host) {
            $this->resetDiscovery();
        }

        Flux::toast(variant: 'success', text: __('Hardware updated.'));
    }

    public function saveProxmoxCredentials(UpdateHardwareProxmoxCredentials $updateHardwareProxmoxCredentials): void
    {
        $this->authorize('update', $this->hardware);

        $this->hardware = $updateHardwareProxmoxCredentials->handle($this->hardware, [
            'api_url' => $this->proxmox_api_url,
            'token_id' => $this->proxmox_token_id,
            'token_secret' => $this->proxmox_token_secret !== '' ? $this->proxmox_token_secret : null,
            'verify_tls' => $this->proxmox_verify_tls,
            'node' => $this->proxmox_node !== '' ? $this->proxmox_node : null,
        ])->load(['assignedUserware', 'virtualwares']);

        $this->fillProxmoxCredentialForm();
        $this->resetDiscovery();

        Flux::toast(variant: 'success', text: __('Proxmox credentials saved.'));
    }

    public function clearProxmoxCredentials(ClearHardwareProxmoxCredentials $clearHardwareProxmoxCredentials): void
    {
        $this->authorize('update', $this->hardware);

        $this->hardware = $clearHardwareProxmoxCredentials->handle($this->hardware)->load(['assignedUserware', 'virtualwares']);
        $this->fillProxmoxCredentialForm();
        $this->resetDiscovery();

        Flux::toast(variant: 'success', text: __('Proxmox credentials removed.'));
    }

    public function discoverProxmoxGuests(DiscoverProxmoxGuests $discoverProxmoxGuests): void
    {
        $this->authorize('update', $this->hardware);
        $this->discoveryError = null;

        try {
            $discovered = $discoverProxmoxGuests->handle($this->hardware);
        } catch (\Throwable $exception) {
            $this->resetDiscovery();
            $this->discoveryError = $exception->getMessage();

            return;
        }

        $importedIds = $this->hardware->organization->virtualwares()
            ->where('provider', VirtualwareProvider::Proxmox)
            ->whereNotNull('external_id')
            ->pluck('external_id')
            ->all();

        $this->hardware->refresh();
        $guests = collect($discovered)
            ->map(fn ($guest): array => [
                ...Arr::except($guest->toArray(), 'notes'),
                'already_imported' => in_array($guest->externalId, $importedIds, true),
            ])
            ->values()
            ->all();

        Cache::put($this->discoveryCacheKey(), $guests, now()->addMinutes(30));
        unset($this->discoveredGuests);

        $this->selectedExternalIds = collect($guests)
            ->pluck('external_id')
            ->values()
            ->all();

        if ($guests === []) {
            Flux::toast(text: __('No guests were found on this Proxmox node.'));
        }
    }

    public function importProxmoxGuests(ImportProxmoxGuests $importProxmoxGuests): void
    {
        $this->authorize('update', $this->hardware);

        $result = $importProxmoxGuests->handle($this->hardware, $this->selectedExternalIds);

        $this->hardware = $this->hardware->fresh()->load(['assignedUserware', 'virtualwares']);
        $this->resetDiscovery();

        Flux::toast(
            variant: 'success',
            text: __('Imported :created new and updated :updated virtual machines.', [
                'created' => $result['created'],
                'updated' => $result['updated'],
            ]),
        );
    }

    public function assign(AssignHardware $assignHardware): void
    {
        $this->authorize('assign', $this->hardware);

        $userware = $this->assigned_userware_id !== ''
            ? Userware::query()
                ->where('organization_id', CurrentOrganization::require()->id)
                ->findOrFail($this->assigned_userware_id)
            : null;

        $this->hardware = $assignHardware->handle($this->hardware, $userware)->load(['assignedUserware', 'virtualwares']);
        $this->status = $this->hardware->status->value;

        Flux::toast(variant: 'success', text: __('Assignment updated.'));
    }

    public function delete(DeleteHardware $deleteHardware): void
    {
        $this->authorize('delete', $this->hardware);
        $deleteHardware->handle($this->hardware);
        $this->redirect(route('assets.hardware.index', absolute: false), navigate: true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function discoveredGuests(): array
    {
        return Cache::get($this->discoveryCacheKey(), []);
    }

    /** @return list<string> */
    public function discoveredExternalIds(): array
    {
        return collect($this->discoveredGuests)->pluck('external_id')->values()->all();
    }

    protected function discoveryCacheKey(): string
    {
        return sprintf('hardware:%s:proxmox-discovery:%s', $this->hardware->id, auth()->id());
    }

    protected function resetDiscovery(): void
    {
        Cache::forget($this->discoveryCacheKey());
        unset($this->discoveredGuests);

        $this->selectedExternalIds = [];
        $this->discoveryError = null;
    }

    protected function selectedCategory(): ?HardwareCategory
    {
        return HardwareCategory::tryFrom($this->category);
    }

    protected function selectedOperatingSystem(): ?HardwareOperatingSystem
    {
        return HardwareOperatingSystem::tryFrom($this->operating_system);
    }

    #[Computed]
    public function identities()
    {
        return Userware::query()
            ->where('organization_id', CurrentOrganization::require()->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function inventory(): ?array
    {
        $payload = $this->hardware->inventory_payload;

        return is_array($payload) ? $payload : null;
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="flex items-center gap-3">
        <flux:button size="sm" :href="route('assets.hardware.index')" wire:navigate icon="arrow-left">{{ __('Back') }}</flux:button>
        <div>
            <flux:heading size="xl">{{ $hardware->name }}</flux:heading>
            <flux:text>
                {{ $hardware->category->label() }}
                @if ($hardware->asset_tag)
                    · {{ $hardware->asset_tag }}
                @endif
                @if ($hardware->is_vm_host)
                    · {{ __('VM host') }}
                @endif
                @if ($hardware->inventory_collected_at)
                    · {{ __('Inventory :when', ['when' => $hardware->inventory_collected_at->diffForHumans()]) }}
                @endif
            </flux:text>
        </div>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading size="lg">{{ __('Basics') }}</flux:heading>
        <flux:input wire:model="name" :label="__('Name')" required :disabled="! auth()->user()->can('update', $hardware)" />
        <flux:select wire:model.live="category" :label="__('Type')" :disabled="! auth()->user()->can('update', $hardware)">
            @foreach (HardwareCategory::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model="asset_tag" :label="__('Asset tag')" :disabled="! auth()->user()->can('update', $hardware)" />
        <flux:select wire:model="status" :label="__('Status')" :disabled="! auth()->user()->can('update', $hardware)">
            @foreach (HardwareStatus::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model="purchased_at" type="date" :label="__('Purchased at')" :disabled="! auth()->user()->can('update', $hardware)" />
        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" :disabled="! auth()->user()->can('update', $hardware)" />

        @if ($this->selectedCategory()?->hasComputeSpecs())
            <div class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Specs') }}</flux:heading>
                <flux:input wire:model="manufacturer" :label="__('Manufacturer')" :disabled="! auth()->user()->can('update', $hardware)" />
                <flux:input wire:model="model" :label="__('Model')" :disabled="! auth()->user()->can('update', $hardware)" />
                <flux:input wire:model="serial_number" :label="__('Serial number')" :disabled="! auth()->user()->can('update', $hardware)" />
                <flux:select wire:model.live="operating_system" :label="__('Operating system')" :disabled="! auth()->user()->can('update', $hardware)">
                    <option value="">{{ __('Select OS') }}</option>
                    @foreach (HardwareOperatingSystem::cases() as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </flux:select>
                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:input wire:model="cpu" :label="__('CPU')" :disabled="! auth()->user()->can('update', $hardware)" />
                    <flux:input wire:model="ram_gb" type="number" min="1" :label="__('RAM (GB)')" :disabled="! auth()->user()->can('update', $hardware)" />
                    <flux:input wire:model="storage_gb" type="number" min="1" :label="__('Storage (GB)')" :disabled="! auth()->user()->can('update', $hardware)" />
                </div>
            </div>

            @if ($this->selectedCategory()?->canBeVmHost())
                <div class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('Virtualization') }}</flux:heading>
                    <flux:checkbox wire:model="is_vm_host" :label="__('VM host')" :description="__('Virtualware can be assigned to this server.')" :disabled="! auth()->user()->can('update', $hardware)" />
                </div>
            @endif
        @endif

        @can('update', $hardware)
            <div class="flex justify-between border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <flux:button variant="danger" type="button" wire:click="delete" wire:confirm="{{ __('Delete this hardware?') }}">{{ __('Delete') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        @endcan
    </form>

    @include('pages.assets.partials.collected-inventory', [
        'collectedAt' => $hardware->inventory_collected_at,
    ])

    @can('assign', $hardware)
        <form wire:submit="assign" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Assignment') }}</flux:heading>
            <flux:select wire:model="assigned_userware_id" :label="__('Assigned identity')">
                <option value="">{{ __('Unassigned') }}</option>
                @foreach ($this->identities as $identity)
                    <option value="{{ $identity->id }}">{{ $identity->name }} ({{ $identity->email }})</option>
                @endforeach
            </flux:select>
            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Update assignment') }}</flux:button>
            </div>
        </form>
    @endcan

    @if ($hardware->is_vm_host)
        <form wire:submit="saveProxmoxCredentials" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div>
                <flux:heading size="lg">{{ __('Proxmox connection') }}</flux:heading>
                <flux:text>
                    {{ __('Store encrypted API credentials used to discover and import guests as virtualware.') }}
                    @if ($hardware->hasProxmoxCredentials())
                        · {{ __('Credentials are saved.') }}
                        @if ($hardware->proxmox_credentials_verified_at)
                            {{ __('Last verified :time.', ['time' => $hardware->proxmox_credentials_verified_at->diffForHumans()]) }}
                        @endif
                    @endif
                </flux:text>
            </div>

            <flux:input wire:model="proxmox_api_url" :label="__('API URL')" :description="__('Example: https://pve.example:8006')" required :disabled="! auth()->user()->can('update', $hardware)" />
            <flux:input wire:model="proxmox_token_id" :label="__('API token ID')" :description="__('Format: user@realm!tokenid')" required :disabled="! auth()->user()->can('update', $hardware)" />
            <flux:input
                wire:model="proxmox_token_secret"
                type="password"
                :label="__('API token secret')"
                :description="$hardware->hasProxmoxCredentials() ? __('Leave blank to keep the existing secret.') : null"
                :required="! $hardware->hasProxmoxCredentials()"
                :disabled="! auth()->user()->can('update', $hardware)"
            />
            <flux:input wire:model="proxmox_node" :label="__('Node name')" :description="__('Short Proxmox node name from the UI (for example pve1), not an FQDN. Required when the API exposes more than one node.')" :disabled="! auth()->user()->can('update', $hardware)" />
            <flux:checkbox wire:model="proxmox_verify_tls" :label="__('Verify TLS certificate')" :description="__('Turn off for the default Proxmox self-signed certificate.')" :disabled="! auth()->user()->can('update', $hardware)" />

            @can('update', $hardware)
                <div class="flex justify-between gap-3">
                    @if ($hardware->hasProxmoxCredentials())
                        <flux:button
                            variant="danger"
                            type="button"
                            wire:click="clearProxmoxCredentials"
                            wire:confirm="{{ __('Remove stored Proxmox credentials for this host?') }}"
                        >
                            {{ __('Remove credentials') }}
                        </flux:button>
                    @else
                        <div></div>
                    @endif
                    <flux:button variant="primary" type="submit">{{ __('Save credentials') }}</flux:button>
                </div>
            @endcan
        </form>

        <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div>
                <flux:heading size="lg">{{ __('Sync from Proxmox') }}</flux:heading>
                <flux:text>{{ __('Discover QEMU VMs and LXC containers, then import them as virtualware on the matching host.') }}</flux:text>
            </div>

            @unless ($hardware->hasProxmoxCredentials())
                <flux:text>{{ __('Add Proxmox credentials above before discovering guests.') }}</flux:text>
            @endunless

            @can('update', $hardware)
                <div>
                    <flux:button
                        type="button"
                        wire:click="discoverProxmoxGuests"
                        wire:loading.attr="disabled"
                        :disabled="! $hardware->hasProxmoxCredentials()"
                    >
                        {{ __('Discover guests') }}
                    </flux:button>
                </div>
            @endcan

            @if ($discoveryError)
                <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:border-red-700 dark:bg-red-950/30 dark:text-red-200">
                    {{ $discoveryError }}
                </div>
            @endif

            @if ($this->discoveredGuests !== [])
                <form
                    wire:submit="importProxmoxGuests"
                    class="flex flex-col gap-4"
                    x-data="{
                        allIds: @js($this->discoveredExternalIds()),
                        selectAll: @js($selectedExternalIds === $this->discoveredExternalIds()),
                    }"
                    x-init="
                        $watch('selectAll', (value) => $wire.selectedExternalIds = value ? allIds.slice() : []);
                        $watch('$wire.selectedExternalIds', (ids) => selectAll = allIds.length !== 0 && ids.length === allIds.length);
                    "
                >
                    <div class="flex items-center justify-between gap-3">
                        <flux:heading size="sm">{{ __('Select guests to import') }}</flux:heading>
                        <flux:switch x-model="selectAll" :label="__('Select all')" />
                    </div>

                    <flux:checkbox.group wire:model="selectedExternalIds">
                        @foreach ($this->discoveredGuests as $guest)
                            <flux:field variant="inline" wire:key="discovered-{{ $guest['external_id'] }}">
                                <flux:checkbox value="{{ $guest['external_id'] }}" />
                                <div>
                                    <flux:label>{{ $guest['name'] }}</flux:label>
                                    <flux:description>
                                        {{ $guest['external_id'] }}
                                        · {{ $guest['node'] }}
                                        · {{ $guest['category'] }}
                                        @if ($guest['instance_type'])
                                            · {{ $guest['instance_type'] }}
                                        @endif
                                        · {{ $guest['status'] }}
                                        @if ($guest['private_ip'])
                                            · {{ $guest['private_ip'] }}
                                        @endif
                                        @if (! empty($guest['disks']))
                                            · {{ trans_choice(':count disk|:count disks', count($guest['disks']), ['count' => count($guest['disks'])]) }}
                                        @endif
                                        @if ($guest['already_imported'])
                                            · {{ __('Already imported') }}
                                        @endif
                                    </flux:description>
                                </div>
                            </flux:field>
                        @endforeach
                    </flux:checkbox.group>

                    <div class="flex items-center justify-between gap-3">
                        <flux:text x-text="`${$wire.selectedExternalIds.length} {{ __('selected') }}`">{{ __(':count selected', ['count' => count($selectedExternalIds)]) }}</flux:text>
                        <flux:button variant="primary" type="submit" x-bind:disabled="$wire.selectedExternalIds.length === 0">
                            {{ __('Import selected') }}
                        </flux:button>
                    </div>
                </form>
            @endif
        </div>

        <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Hosted virtualware') }}</flux:heading>
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($hardware->virtualwares as $virtualware)
                    <li class="flex items-center justify-between py-3">
                        <div>
                            <a href="{{ route('assets.virtualware.show', $virtualware) }}" class="font-medium text-accent" wire:navigate>{{ $virtualware->name }}</a>
                            <flux:text>
                                {{ $virtualware->status->label() }}
                                @if ($virtualware->external_id)
                                    · {{ $virtualware->external_id }}
                                @endif
                            </flux:text>
                        </div>
                        <flux:button size="sm" :href="route('assets.virtualware.show', $virtualware)" wire:navigate>{{ __('View') }}</flux:button>
                    </li>
                @empty
                    <li class="py-3"><flux:text>{{ __('No virtualware hosted on this server.') }}</flux:text></li>
                @endforelse
            </ul>
        </div>
    @endif

    <livewire:asset-documents :documentable="$hardware" :key="'hardware-docs-'.$hardware->id" />
</div>
