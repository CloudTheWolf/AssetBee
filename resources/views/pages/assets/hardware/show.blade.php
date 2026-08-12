<?php

use App\Actions\Assets\AssignHardware;
use App\Actions\Assets\DeleteHardware;
use App\Actions\Assets\UpdateHardware;
use App\Enums\BitLockerStatus;
use App\Enums\HardwareCategory;
use App\Enums\HardwareOperatingSystem;
use App\Enums\HardwareStatus;
use App\Models\Hardware;
use App\Models\Userware;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Hardware')] class extends Component {
    use AuthorizesRequests;

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

    public function mount(Hardware $hardware): void
    {
        $this->authorize('view', $hardware);
        abort_unless($hardware->organization_id === CurrentOrganization::require()->id, 404);

        $this->hardware = $hardware->load(['assignedUserware', 'virtualwares']);
        $this->fillForm();
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

        Flux::toast(variant: 'success', text: __('Hardware updated.'));
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

    /**
     * @return array<string, mixed>|null
     */
    protected function inventoryProbe(string $key): ?array
    {
        $probe = data_get($this->inventory, $key);

        return is_array($probe) ? $probe : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function inventoryList(string $key): array
    {
        $probe = $this->inventoryProbe($key);

        if (($probe['status'] ?? null) !== 'available' || ! is_array($probe['value'] ?? null)) {
            return [];
        }

        return array_values(array_filter(
            $probe['value'],
            fn (mixed $item): bool => is_array($item),
        ));
    }

    protected function formatBytes(mixed $bytes): string
    {
        if (! is_int($bytes) || $bytes < 0) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return round($value, $unit === 0 ? 0 : 1).' '.$units[$unit];
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

            @if ($this->selectedOperatingSystem()?->isWindows())
                <div class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('BitLocker') }}</flux:heading>
                    <flux:select wire:model="bitlocker_status" :label="__('BitLocker status')" :disabled="! auth()->user()->can('update', $hardware)">
                        <option value="">{{ __('Select status') }}</option>
                        @foreach (BitLockerStatus::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:textarea wire:model="bitlocker_recovery_key" :label="__('Recovery key')" rows="3" :disabled="! auth()->user()->can('update', $hardware)" />
                </div>
            @endif

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

    @if ($this->inventory)
        @php
            $operatingSystem = $this->inventoryProbe('operatingSystem');
            $cpu = $this->inventoryProbe('cpu');
            $memory = $this->inventoryProbe('memory');
            $domainWorkspace = $this->inventoryProbe('domainWorkspace');
            $updates = $this->inventoryProbe('updates');
            $disks = $this->inventoryList('disks');
            $encryption = $this->inventoryList('diskEncryption');
            $loginProviders = $this->inventoryList('loginProviders');
            $antivirus = $this->inventoryList('antivirus');
            $installedUpdates = is_array(data_get($updates, 'value.installed')) ? data_get($updates, 'value.installed') : [];
            $availableUpdates = is_array(data_get($updates, 'value.available')) ? data_get($updates, 'value.available') : [];
            $sbom = $this->inventoryProbe('sbom');
            $sbomComponentCount = 0;
            foreach (data_get($sbom, 'value.targets', []) as $target) {
                if (is_array($target) && is_array($target['components'] ?? null)) {
                    $sbomComponentCount += count($target['components']);
                }
            }
        @endphp

        <div class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div>
                <flux:heading size="lg">{{ __('Collected inventory') }}</flux:heading>
                <flux:text>
                    {{ __('Last collected :when', ['when' => $hardware->inventory_collected_at?->timezone('UTC')->toDayDateTimeString().' UTC']) }}
                    · {{ strtoupper((string) data_get($this->inventory, 'platform')) }}
                    · {{ __('Schema :version', ['version' => data_get($this->inventory, 'schemaVersion')]) }}
                </flux:text>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:text class="font-medium">{{ __('Operating system') }}</flux:text>
                    @if (($operatingSystem['status'] ?? null) === 'available')
                        <div>{{ data_get($operatingSystem, 'value.name') }}</div>
                        <flux:text>
                            {{ collect([
                                data_get($operatingSystem, 'value.version'),
                                data_get($operatingSystem, 'value.displayVersion'),
                                data_get($operatingSystem, 'value.build') ? __('Build :build', ['build' => data_get($operatingSystem, 'value.build')]) : null,
                            ])->filter()->implode(' · ') }}
                        </flux:text>
                    @else
                        <flux:text>{{ data_get($operatingSystem, 'status', '—') }}</flux:text>
                    @endif
                </div>
                <div>
                    <flux:text class="font-medium">{{ __('CPU') }}</flux:text>
                    @if (($cpu['status'] ?? null) === 'available')
                        <div>{{ data_get($cpu, 'value.model') }}</div>
                        <flux:text>
                            {{ __(':logical logical / :physical physical', [
                                'logical' => data_get($cpu, 'value.logicalProcessors', '—'),
                                'physical' => data_get($cpu, 'value.physicalCores') ?? '—',
                            ]) }}
                        </flux:text>
                    @else
                        <flux:text>{{ data_get($cpu, 'status', '—') }}</flux:text>
                    @endif
                </div>
                <div>
                    <flux:text class="font-medium">{{ __('Memory') }}</flux:text>
                    @if (($memory['status'] ?? null) === 'available')
                        <div>{{ $this->formatBytes(data_get($memory, 'value.totalBytes')) }}</div>
                        @if (data_get($memory, 'value.availableBytes') !== null)
                            <flux:text>{{ __(':available available', ['available' => $this->formatBytes(data_get($memory, 'value.availableBytes'))]) }}</flux:text>
                        @endif
                    @else
                        <flux:text>{{ data_get($memory, 'status', '—') }}</flux:text>
                    @endif
                </div>
                <div>
                    <flux:text class="font-medium">{{ __('Domain / workspace') }}</flux:text>
                    @if (($domainWorkspace['status'] ?? null) === 'available')
                        <div>{{ data_get($domainWorkspace, 'value.domain') ?: '—' }}</div>
                        <flux:text>
                            {{ collect([
                                data_get($domainWorkspace, 'value.domainJoined') ? __('Domain joined') : null,
                                data_get($domainWorkspace, 'value.workspace'),
                                data_get($domainWorkspace, 'value.workspaceJoined') ? __('Workspace joined') : null,
                            ])->filter()->implode(' · ') ?: '—' }}
                        </flux:text>
                    @else
                        <flux:text>{{ data_get($domainWorkspace, 'status', '—') }}</flux:text>
                    @endif
                </div>
            </div>

            <div class="space-y-3 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <div class="font-medium">{{ __('Disks') }}</div>
                @forelse ($disks as $disk)
                    <div class="flex items-start justify-between gap-4 py-2" wire:key="disk-{{ $loop->index }}">
                        <div>
                            <div class="font-medium">{{ $disk['name'] ?? '—' }}</div>
                            <flux:text>
                                {{ collect([
                                    $disk['mountPoint'] ?? null,
                                    $disk['fileSystem'] ?? null,
                                ])->filter()->implode(' · ') }}
                            </flux:text>
                        </div>
                        <flux:text class="whitespace-nowrap text-right">
                            {{ $this->formatBytes($disk['totalBytes'] ?? null) }}
                            @if (($disk['availableBytes'] ?? null) !== null)
                                <br>{{ __(':available free', ['available' => $this->formatBytes($disk['availableBytes'])]) }}
                            @endif
                        </flux:text>
                    </div>
                @empty
                    <flux:text>{{ __('No disk details collected.') }}</flux:text>
                @endforelse
            </div>

            <div class="space-y-3 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <div class="font-medium">{{ __('Disk encryption') }}</div>
                @forelse ($encryption as $volume)
                    <div class="flex items-start justify-between gap-4 py-2" wire:key="encryption-{{ $loop->index }}">
                        <div>
                            <div class="font-medium">{{ $volume['volume'] ?? '—' }}</div>
                            <flux:text>{{ ($volume['technology'] ?? '—').' · '.($volume['state'] ?? '—') }}</flux:text>
                        </div>
                        @php
                            $hasRecoveryKey = collect($volume['recoveryKeys'] ?? [])->filter()->isNotEmpty()
                                || collect($volume['keyProtectors'] ?? [])->contains(fn ($protector) => filled(data_get($protector, 'recoveryKey')));
                        @endphp
                        @if ($hasRecoveryKey)
                            <flux:badge size="sm" color="green">{{ __('Recovery key stored') }}</flux:badge>
                        @endif
                    </div>
                @empty
                    <flux:text>{{ __('No encryption details collected.') }}</flux:text>
                @endforelse
            </div>

            <div class="space-y-3 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <div class="font-medium">{{ __('Login providers') }}</div>
                @forelse ($loginProviders as $provider)
                    <div class="py-2" wire:key="login-{{ $loop->index }}">
                        <div class="font-medium">{{ $provider['name'] ?? '—' }}</div>
                        <flux:text>{{ collect([$provider['state'] ?? null, $provider['detail'] ?? null])->filter()->implode(' · ') }}</flux:text>
                    </div>
                @empty
                    <flux:text>{{ __('No login providers collected.') }}</flux:text>
                @endforelse
            </div>

            <div class="space-y-3 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <div class="font-medium">{{ __('Antivirus') }}</div>
                @forelse ($antivirus as $product)
                    <div class="flex items-start justify-between gap-4 py-2" wire:key="av-{{ $loop->index }}">
                        <div>
                            <div class="font-medium">{{ $product['name'] ?? '—' }}</div>
                            <flux:text>{{ collect([$product['state'] ?? null, $product['detail'] ?? null])->filter()->implode(' · ') }}</flux:text>
                        </div>
                        <div class="flex flex-wrap justify-end gap-2">
                            @if (($product['enabled'] ?? null) === true)
                                <flux:badge size="sm" color="green">{{ __('Enabled') }}</flux:badge>
                            @elseif (($product['enabled'] ?? null) === false)
                                <flux:badge size="sm" color="zinc">{{ __('Disabled') }}</flux:badge>
                            @endif
                            @if (($product['upToDate'] ?? null) === true)
                                <flux:badge size="sm" color="green">{{ __('Up to date') }}</flux:badge>
                            @elseif (($product['upToDate'] ?? null) === false)
                                <flux:badge size="sm" color="amber">{{ __('Out of date') }}</flux:badge>
                            @endif
                        </div>
                    </div>
                @empty
                    <flux:text>{{ __('No antivirus details collected.') }}</flux:text>
                @endforelse
            </div>

            <div class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <div>
                    <div class="font-medium">{{ __('Updates') }}</div>
                    @if (($updates['status'] ?? null) === 'available')
                        <flux:text>
                            {{ __(':installed installed · :available available', [
                                'installed' => count($installedUpdates),
                                'available' => count($availableUpdates),
                            ]) }}
                        </flux:text>
                    @else
                        <flux:text>{{ data_get($updates, 'status', '—') }}</flux:text>
                    @endif
                </div>

                @if ($availableUpdates !== [])
                    <div class="space-y-2">
                        <flux:text class="font-medium">{{ __('Available') }}</flux:text>
                        @foreach ($availableUpdates as $update)
                            <div class="py-1" wire:key="available-update-{{ $loop->index }}">
                                <div>{{ $update['title'] ?? $update['id'] ?? '—' }}</div>
                                <flux:text>{{ collect([$update['id'] ?? null, $update['category'] ?? null, $update['kbArticle'] ?? null])->filter()->implode(' · ') }}</flux:text>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($installedUpdates !== [])
                    <div class="space-y-2">
                        <flux:text class="font-medium">{{ __('Installed') }}</flux:text>
                        @foreach (array_slice($installedUpdates, 0, 10) as $update)
                            <div class="py-1" wire:key="installed-update-{{ $loop->index }}">
                                <div>{{ $update['title'] ?? $update['id'] ?? '—' }}</div>
                                <flux:text>
                                    {{ collect([
                                        $update['id'] ?? null,
                                        $update['kbArticle'] ?? null,
                                        filled($update['installedAtUtc'] ?? null)
                                            ? \Illuminate\Support\Carbon::parse($update['installedAtUtc'])->timezone('UTC')->toFormattedDateString()
                                            : null,
                                    ])->filter()->implode(' · ') }}
                                </flux:text>
                            </div>
                        @endforeach
                        @if (count($installedUpdates) > 10)
                            <flux:text>{{ __('And :count more…', ['count' => count($installedUpdates) - 10]) }}</flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <div>
                    <div class="font-medium">{{ __('Software bill of materials') }}</div>
                    @if (($sbom['status'] ?? null) === 'available')
                        <flux:text>
                            {{ collect([
                                trim((string) data_get($sbom, 'value.format').' '.(string) data_get($sbom, 'value.specVersion')),
                                __(':count components across :targets targets', [
                                    'count' => $sbomComponentCount,
                                    'targets' => count(data_get($sbom, 'value.targets', [])),
                                ]),
                            ])->filter()->implode(' · ') }}
                        </flux:text>
                        <flux:text>
                            {{ filled(data_get($sbom, 'value.generatedAtUtc'))
                                ? __('Generated :when', ['when' => \Illuminate\Support\Carbon::parse(data_get($sbom, 'value.generatedAtUtc'))->timezone('UTC')->toDayDateTimeString().' UTC'])
                                : '—' }}
                        </flux:text>
                    @else
                        <flux:text>{{ data_get($sbom, 'status', '—') }}</flux:text>
                    @endif
                </div>

                @if (($sbom['status'] ?? null) === 'available')
                    @foreach (data_get($sbom, 'value.targets', []) as $target)
                        @php
                            $sbomComponents = isset($target['components']) && is_array($target['components'])
                                ? $target['components']
                                : [];
                        @endphp
                        <div class="space-y-2" wire:key="sbom-target-{{ $loop->index }}">
                            <div>
                                <flux:text class="font-medium">{{ $target['name'] ?? $target['bomRef'] ?? __('Target') }}</flux:text>
                                <flux:text>
                                    {{ collect([
                                        $target['kind'] ?? null,
                                        __(':count components', ['count' => count($sbomComponents)]),
                                    ])->filter()->implode(' · ') }}
                                </flux:text>
                            </div>

                            @forelse (array_slice($sbomComponents, 0, 50) as $component)
                                <div class="py-1" wire:key="sbom-component-{{ $loop->parent->index }}-{{ $loop->index }}">
                                    <div>
                                        {{ $component['name'] ?? '—' }}
                                        @if (filled($component['version'] ?? null))
                                            <span class="text-zinc-500 dark:text-zinc-400">{{ $component['version'] }}</span>
                                        @endif
                                    </div>
                                    <flux:text>
                                        {{ collect([
                                            $component['type'] ?? null,
                                            $component['publisher'] ?? null,
                                            $component['purl'] ?? null,
                                        ])->filter()->implode(' · ') }}
                                    </flux:text>
                                </div>
                            @empty
                                <flux:text>{{ __('No components collected.') }}</flux:text>
                            @endforelse

                            @if (count($sbomComponents) > 50)
                                <flux:text>{{ __('And :count more…', ['count' => count($sbomComponents) - 50]) }}</flux:text>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    @endif

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
        <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Hosted virtualware') }}</flux:heading>
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($hardware->virtualwares as $virtualware)
                    <li class="flex items-center justify-between py-3">
                        <div>
                            <a href="{{ route('assets.virtualware.show', $virtualware) }}" class="font-medium text-accent" wire:navigate>{{ $virtualware->name }}</a>
                            <flux:text>{{ $virtualware->status->label() }}</flux:text>
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
