<?php

use App\Actions\Assets\BulkAssignHardware;
use App\Actions\Assets\BulkDeleteHardware;
use App\Actions\Assets\BulkUpdateHardwareStatus;
use App\Actions\Assets\CreateHardware;
use App\Actions\Assets\DeleteHardware;
use App\Actions\Assets\ImportHardwareFromCsv;
use App\Enums\BitLockerStatus;
use App\Enums\HardwareCategory;
use App\Enums\HardwareOperatingSystem;
use App\Enums\HardwareStatus;
use App\Livewire\Concerns\ControlsAssetTables;
use App\Models\Hardware;
use App\Models\Userware;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Hardware')] class extends Component {
    use AuthorizesRequests;
    use ControlsAssetTables;
    use WithFileUploads;
    use WithPagination;

    #[Session]
    public string $search = '';

    #[Session]
    public string $type = '';

    #[Session]
    public string $status = '';

    /** @var list<int|string> */
    public array $selected = [];

    public bool $selectPage = false;

    public string $name = '';

    public string $asset_tag = '';

    public string $category = 'laptop';

    public string $createStatus = 'available';

    public string $serial_number = '';

    public string $manufacturer = '';

    public string $model = '';

    public string $operating_system = '';

    public string $cpu = '';

    public string $ram_gb = '';

    public string $storage_gb = '';

    public string $bitlocker_status = '';

    public string $bitlocker_recovery_key = '';

    public bool $is_vm_host = false;

    public ?TemporaryUploadedFile $importFile = null;

    public string $bulkStatus = 'available';

    public string $bulkAssignedUserwareId = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Hardware::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSelectPage(bool $value): void
    {
        $pageIds = $this->hardwares
            ->getCollection()
            ->pluck('id')
            ->map(fn (int $id): string => (string) $id)
            ->all();

        if ($value) {
            $this->selected = array_values(array_unique([
                ...array_map(fn (mixed $id): string => (string) $id, $this->selected),
                ...$pageIds,
            ]));

            return;
        }

        $this->selected = array_values(array_diff(
            array_map(fn (mixed $id): string => (string) $id, $this->selected),
            $pageIds,
        ));
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectPage = false;
    }

    public function updatedCategory(): void
    {
        if (! $this->selectedCategory()?->canBeVmHost()) {
            $this->is_vm_host = false;
        }

        if (! $this->selectedCategory()?->hasComputeSpecs()) {
            $this->resetComputeFields();
        }
    }

    public function updatedOperatingSystem(): void
    {
        if (! $this->selectedOperatingSystem()?->isWindows()) {
            $this->bitlocker_status = '';
            $this->bitlocker_recovery_key = '';
        }
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'asset_tag', 'serial_number', 'category', 'status', 'assigned_to'];
    }

    public function create(CreateHardware $createHardware): void
    {
        $this->authorize('create', Hardware::class);

        $createHardware->handle(CurrentOrganization::require(), $this->createPayload());

        $this->reset([
            'name',
            'asset_tag',
            'serial_number',
            'manufacturer',
            'model',
            'operating_system',
            'cpu',
            'ram_gb',
            'storage_gb',
            'bitlocker_status',
            'bitlocker_recovery_key',
            'is_vm_host',
        ]);
        $this->category = HardwareCategory::Laptop->value;
        $this->createStatus = HardwareStatus::Available->value;

        Flux::modal('create-hardware')->close();
        Flux::toast(variant: 'success', text: __('Hardware created.'));
    }

    public function import(ImportHardwareFromCsv $importHardwareFromCsv): void
    {
        $this->authorize('create', Hardware::class);

        $this->validate([
            'importFile' => ['required', 'file', 'extensions:csv,txt', 'max:10240'],
        ]);

        $result = $importHardwareFromCsv->handle(CurrentOrganization::require(), $this->importFile);

        $this->reset('importFile');

        Flux::modal('import-hardware')->close();
        Flux::toast(
            variant: 'success',
            text: __('Imported :created devices (:skipped skipped).', [
                'created' => $result['created'],
                'skipped' => $result['skipped'],
            ]),
        );
    }

    public function bulkUpdateStatus(BulkUpdateHardwareStatus $bulkUpdateHardwareStatus): void
    {
        $hardwares = $this->authorizeSelectedHardwares('update');

        $result = $bulkUpdateHardwareStatus->handle($hardwares, $this->bulkStatus);

        $this->clearSelection();
        $this->bulkStatus = HardwareStatus::Available->value;

        Flux::modal('bulk-change-status')->close();
        Flux::toast(
            variant: 'success',
            text: __('Updated status for :count devices.', ['count' => $result['updated']]),
        );
    }

    public function bulkAssign(BulkAssignHardware $bulkAssignHardware): void
    {
        $hardwares = $this->authorizeSelectedHardwares('assign');

        $userware = $this->bulkAssignedUserwareId !== ''
            ? Userware::query()
                ->where('organization_id', CurrentOrganization::require()->id)
                ->findOrFail($this->bulkAssignedUserwareId)
            : null;

        $result = $bulkAssignHardware->handle($hardwares, $userware);

        $this->clearSelection();
        $this->bulkAssignedUserwareId = '';

        Flux::modal('bulk-assign')->close();
        Flux::toast(
            variant: 'success',
            text: __('Assigned :assigned devices (:skipped skipped).', [
                'assigned' => $result['assigned'],
                'skipped' => $result['skipped'],
            ]),
        );
    }

    public function bulkDelete(BulkDeleteHardware $bulkDeleteHardware): void
    {
        $hardwares = $this->authorizeSelectedHardwares('delete');

        $result = $bulkDeleteHardware->handle($hardwares);

        $this->clearSelection();

        Flux::toast(
            variant: 'success',
            text: __('Deleted :count devices.', ['count' => $result['deleted']]),
        );
    }

    public function delete(Hardware $hardware, DeleteHardware $deleteHardware): void
    {
        $this->authorize('delete', $hardware);
        $deleteHardware->handle($hardware);
        Flux::toast(variant: 'success', text: __('Hardware deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function createPayload(): array
    {
        return [
            'name' => $this->name,
            'asset_tag' => $this->asset_tag !== '' ? $this->asset_tag : null,
            'serial_number' => $this->serial_number !== '' ? $this->serial_number : null,
            'manufacturer' => $this->manufacturer !== '' ? $this->manufacturer : null,
            'model' => $this->model !== '' ? $this->model : null,
            'category' => $this->category,
            'status' => $this->createStatus,
            'operating_system' => $this->operating_system !== '' ? $this->operating_system : null,
            'cpu' => $this->cpu !== '' ? $this->cpu : null,
            'ram_gb' => $this->ram_gb !== '' ? (int) $this->ram_gb : null,
            'storage_gb' => $this->storage_gb !== '' ? (int) $this->storage_gb : null,
            'bitlocker_status' => $this->bitlocker_status !== '' ? $this->bitlocker_status : null,
            'bitlocker_recovery_key' => $this->bitlocker_recovery_key !== '' ? $this->bitlocker_recovery_key : null,
            'is_vm_host' => $this->is_vm_host,
        ];
    }

    protected function resetComputeFields(): void
    {
        $this->reset([
            'serial_number',
            'manufacturer',
            'model',
            'operating_system',
            'cpu',
            'ram_gb',
            'storage_gb',
            'bitlocker_status',
            'bitlocker_recovery_key',
            'is_vm_host',
        ]);
    }

    protected function selectedCategory(): ?HardwareCategory
    {
        return HardwareCategory::tryFrom($this->category);
    }

    protected function selectedOperatingSystem(): ?HardwareOperatingSystem
    {
        return HardwareOperatingSystem::tryFrom($this->operating_system);
    }

    /**
     * @return Collection<int, Hardware>
     *
     * @throws ValidationException
     */
    protected function authorizeSelectedHardwares(string $ability): Collection
    {
        $ids = collect($this->selected)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'selected' => __('Select at least one device.'),
            ]);
        }

        $hardwares = CurrentOrganization::require()
            ->hardwares()
            ->whereIn('id', $ids->all())
            ->get();

        if ($hardwares->isEmpty()) {
            throw ValidationException::withMessages([
                'selected' => __('Select at least one device.'),
            ]);
        }

        foreach ($hardwares as $hardware) {
            $this->authorize($ability, $hardware);
        }

        return $hardwares;
    }

    #[Computed]
    public function identities()
    {
        return Userware::query()
            ->where('organization_id', CurrentOrganization::require()->id)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function hardwares()
    {
        $sortBy = $this->currentSortColumn();
        $direction = $this->currentSortDirection();

        $hardwares = Hardware::query()
            ->with('assignedUserware')
            ->where('organization_id', CurrentOrganization::require()->id)
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('asset_tag', 'like', '%'.$this->search.'%')
                        ->orWhere('serial_number', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->type !== '', fn ($query) => $query->where('category', $this->type))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status));

        if ($sortBy === 'assigned_to') {
            $this->orderByAssignedUserware($hardwares, $direction);
        } else {
            $hardwares->orderBy($sortBy, $direction)->orderBy('id');
        }

        return $hardwares->paginate($this->rowsPerPage());
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Hardware') }}</flux:heading>
            <flux:text>{{ __('Physical devices across your organization.') }}</flux:text>
        </div>
        @can('create', App\Models\Hardware::class)
            <div class="flex flex-wrap gap-2">
                <flux:modal.trigger name="import-hardware">
                    <flux:button variant="ghost" icon="arrow-up-tray" data-test="import-hardware">{{ __('Import CSV') }}</flux:button>
                </flux:modal.trigger>
                <flux:modal.trigger name="create-hardware">
                    <flux:button variant="primary" icon="plus">{{ __('Add hardware') }}</flux:button>
                </flux:modal.trigger>
            </div>
        @endcan
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search name, asset tag, serial...')" class="flex-1" />
        <flux:select wire:model.live="type" class="sm:w-48">
            <option value="">{{ __('All types') }}</option>
            @foreach (App\Enums\HardwareCategory::cases() as $typeOption)
                <option value="{{ $typeOption->value }}">{{ $typeOption->label() }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="status" class="sm:w-48">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (App\Enums\HardwareStatus::cases() as $statusOption)
                <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
            @endforeach
        </flux:select>
        <x-asset-table-per-page />
    </div>

    @if (count($selected) > 0)
        <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700 dark:bg-zinc-900" data-test="hardware-bulk-actions">
            <flux:text>{{ __(':count selected', ['count' => count($selected)]) }}</flux:text>
            <div class="flex flex-wrap gap-2">
                <flux:modal.trigger name="bulk-change-status">
                    <flux:button variant="ghost" size="sm" icon="arrow-path" data-test="bulk-change-status">{{ __('Change status') }}</flux:button>
                </flux:modal.trigger>
                <flux:modal.trigger name="bulk-assign">
                    <flux:button variant="ghost" size="sm" icon="user-plus" data-test="bulk-assign">{{ __('Assign') }}</flux:button>
                </flux:modal.trigger>
                <flux:button
                    variant="danger"
                    size="sm"
                    icon="trash"
                    wire:click="bulkDelete"
                    wire:confirm="{{ __('Delete the selected hardware?') }}"
                    data-test="bulk-delete"
                >
                    {{ __('Delete') }}
                </flux:button>
                <flux:button variant="ghost" size="sm" wire:click="clearSelection">{{ __('Clear') }}</flux:button>
            </div>
        </div>
    @endif

    <flux:table :paginate="$this->hardwares">
        <flux:table.columns>
            @can('create', App\Models\Hardware::class)
                <flux:table.column class="w-10">
                    <flux:checkbox wire:model.live="selectPage" />
                </flux:table.column>
            @endcan
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'asset_tag'" :direction="$sortDirection" wire:click="sort('asset_tag')">{{ __('Asset tag') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'serial_number'" :direction="$sortDirection" wire:click="sort('serial_number')">{{ __('Serial') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'category'" :direction="$sortDirection" wire:click="sort('category')">{{ __('Category') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">{{ __('Status') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'assigned_to'" :direction="$sortDirection" wire:click="sort('assigned_to')">{{ __('Assigned to') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->hardwares as $hardware)
                <flux:table.row :key="$hardware->id">
                    @can('update', $hardware)
                        <flux:table.cell>
                            <flux:checkbox wire:model.live="selected" value="{{ $hardware->id }}" />
                        </flux:table.cell>
                    @endcan
                    <flux:table.cell>
                        <a href="{{ route('assets.hardware.show', $hardware) }}" class="font-medium text-accent" wire:navigate>{{ $hardware->name }}</a>
                        <div class="text-xs text-zinc-500">
                            {{ collect([
                                $hardware->manufacturer,
                                $hardware->model,
                                $hardware->operating_system?->label(),
                                $hardware->is_vm_host ? __('VM host') : null,
                                $hardware->inventory_collected_at
                                    ? __('Inventory :when', ['when' => $hardware->inventory_collected_at->diffForHumans()])
                                    : null,
                            ])->filter()->implode(' · ') }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $hardware->asset_tag ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $hardware->serial_number ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $hardware->category->label() }}</flux:table.cell>
                    <flux:table.cell class="py-0">
                        <flux:badge size="sm" :color="$hardware->status->color()">{{ $hardware->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $hardware->assignedUserware?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end">
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item :href="route('assets.hardware.show', $hardware)" wire:navigate icon="eye">{{ __('View') }}</flux:menu.item>
                                    @can('delete', $hardware)
                                        <flux:menu.separator />
                                        <flux:menu.item variant="danger" icon="trash" wire:click="delete({{ $hardware->id }})" wire:confirm="{{ __('Delete this hardware?') }}">
                                            {{ __('Delete') }}
                                        </flux:menu.item>
                                    @endcan
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="{{ auth()->user()->can('create', App\Models\Hardware::class) ? 8 : 7 }}">
                        <div class="py-10 text-center">
                            <flux:heading size="sm">{{ __('No hardware found') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Add a device to start tracking physical assets.') }}</flux:text>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @can('create', App\Models\Hardware::class)
        <flux:modal name="bulk-change-status" class="max-w-lg">
            <form wire:submit="bulkUpdateStatus" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Change status') }}</flux:heading>
                    <flux:text>{{ __('Set a new status for the selected devices.') }}</flux:text>
                </div>

                <flux:select wire:model="bulkStatus" :label="__('Status')" required>
                    @foreach (App\Enums\HardwareStatus::cases() as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit" data-test="confirm-bulk-change-status">{{ __('Update status') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="bulk-assign" class="max-w-lg">
            <form wire:submit="bulkAssign" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Assign hardware') }}</flux:heading>
                    <flux:text>{{ __('Assign the selected devices to an identity, or leave unassigned.') }}</flux:text>
                </div>

                <flux:select wire:model="bulkAssignedUserwareId" :label="__('Assigned identity')">
                    <option value="">{{ __('Unassigned') }}</option>
                    @foreach ($this->identities as $identity)
                        <option value="{{ $identity->id }}">{{ $identity->name }} ({{ $identity->email }})</option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit" data-test="confirm-bulk-assign">{{ __('Assign') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="import-hardware" class="max-w-lg">
            <form wire:submit="import" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Import hardware') }}</flux:heading>
                    <flux:text>{{ __('Upload a CSV with Device Name, Name, Email, OS, and Serial Number columns. Existing serial numbers are skipped, and rows without a serial number or email are skipped.') }}</flux:text>
                </div>

                <flux:input type="file" wire:model="importFile" accept=".csv,text/csv" :label="__('CSV file')" required />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit" data-test="confirm-import-hardware">{{ __('Import') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan

    <flux:modal name="create-hardware" class="max-w-lg">
        <form wire:submit="create" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add hardware') }}</flux:heading>
                <flux:text>{{ __('Start with the basics. Specs appear based on the type.') }}</flux:text>
            </div>

            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:select wire:model.live="category" :label="__('Type')">
                @foreach (App\Enums\HardwareCategory::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="asset_tag" :label="__('Asset tag')" />
            <flux:select wire:model="createStatus" :label="__('Status')">
                @foreach (App\Enums\HardwareStatus::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </flux:select>

            @if ($this->selectedCategory()?->hasComputeSpecs())
                <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Device details') }}</flux:heading>
                    <flux:input wire:model="manufacturer" :label="__('Manufacturer')" />
                    <flux:input wire:model="model" :label="__('Model')" />
                    <flux:input wire:model="serial_number" :label="__('Serial number')" />
                    <flux:select wire:model.live="operating_system" :label="__('Operating system')">
                        <option value="">{{ __('Select OS') }}</option>
                        @foreach (App\Enums\HardwareOperatingSystem::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </flux:select>
                    <div class="grid grid-cols-3 gap-3">
                        <flux:input wire:model="cpu" :label="__('CPU')" class="col-span-3 sm:col-span-1" />
                        <flux:input wire:model="ram_gb" type="number" min="1" :label="__('RAM (GB)')" />
                        <flux:input wire:model="storage_gb" type="number" min="1" :label="__('Storage (GB)')" />
                    </div>

                    @if ($this->selectedOperatingSystem()?->isWindows())
                        <div class="space-y-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <flux:heading size="sm">{{ __('BitLocker') }}</flux:heading>
                            <flux:select wire:model="bitlocker_status" :label="__('BitLocker status')">
                                <option value="">{{ __('Select status') }}</option>
                                @foreach (App\Enums\BitLockerStatus::cases() as $option)
                                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                @endforeach
                            </flux:select>
                            <flux:textarea wire:model="bitlocker_recovery_key" :label="__('Recovery key')" rows="2" />
                        </div>
                    @endif

                    @if ($this->selectedCategory()?->canBeVmHost())
                        <flux:checkbox wire:model="is_vm_host" :label="__('VM host')" :description="__('Allow virtualware to run on this server.')" />
                    @endif
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
