<?php

use App\Actions\Assets\CreateSoftware;
use App\Actions\Assets\DeleteSoftware;
use App\Enums\SoftwareLicenseType;
use App\Enums\SoftwareStatus;
use App\Models\Software;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Software')] class extends Component {
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $name = '';

    public string $vendor = '';

    public string $license_type = 'seat';

    public string $total_seats = '10';

    public string $createStatus = 'active';

    public string $expires_at = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Software::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function create(CreateSoftware $createSoftware): void
    {
        $this->authorize('create', Software::class);

        $createSoftware->handle(CurrentOrganization::require(), [
            'name' => $this->name,
            'vendor' => $this->vendor !== '' ? $this->vendor : null,
            'license_type' => $this->license_type,
            'total_seats' => $this->total_seats !== '' ? (int) $this->total_seats : null,
            'status' => $this->createStatus,
            'expires_at' => $this->expires_at !== '' ? $this->expires_at : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ]);

        $this->reset(['name', 'vendor', 'expires_at', 'notes']);
        $this->license_type = SoftwareLicenseType::Seat->value;
        $this->total_seats = '10';
        $this->createStatus = SoftwareStatus::Active->value;

        Flux::modal('create-software')->close();
        Flux::toast(variant: 'success', text: __('Software created.'));
    }

    public function delete(Software $software, DeleteSoftware $deleteSoftware): void
    {
        $this->authorize('delete', $software);
        $deleteSoftware->handle($software);
        Flux::toast(variant: 'success', text: __('Software deleted.'));
    }

    #[Computed]
    public function softwares()
    {
        return Software::query()
            ->withCount('assignments')
            ->where('organization_id', CurrentOrganization::require()->id)
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('vendor', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->orderBy('name')
            ->paginate(10);
    }
}; ?>

<div class="flex flex-col gap-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Software') }}</flux:heading>
                <flux:text>{{ __('Licenses and seats.') }}</flux:text>
            </div>
            @can('create', App\Models\Software::class)
                <flux:modal.trigger name="create-software">
                    <flux:button variant="primary" icon="plus">{{ __('Add software') }}</flux:button>
                </flux:modal.trigger>
            @endcan
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search name or vendor...')" class="flex-1" />
            <flux:select wire:model.live="status" class="sm:w-48">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (App\Enums\SoftwareStatus::cases() as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </flux:select>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Vendor') }}</flux:table.column>
                    <flux:table.column>{{ __('License') }}</flux:table.column>
                    <flux:table.column>{{ __('Seats') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->softwares as $software)
                        <flux:table.row :key="$software->id">
                            <flux:table.cell>
                                <a href="{{ route('assets.software.show', $software) }}" class="font-medium text-accent" wire:navigate>{{ $software->name }}</a>
                            </flux:table.cell>
                            <flux:table.cell>{{ $software->vendor ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $software->license_type->label() }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($software->license_type === App\Enums\SoftwareLicenseType::Seat)
                                    {{ $software->assignments_count }} / {{ $software->total_seats }}
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $software->status->label() }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" :href="route('assets.software.show', $software)" wire:navigate>{{ __('View') }}</flux:button>
                                    @can('delete', $software)
                                        <flux:button size="sm" variant="danger" wire:click="delete({{ $software->id }})" wire:confirm="{{ __('Delete this software?') }}">{{ __('Delete') }}</flux:button>
                                    @endcan
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6"><flux:text class="py-6 text-center">{{ __('No software found.') }}</flux:text></flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        {{ $this->softwares->links() }}

        <flux:modal name="create-software" class="max-w-lg">
            <form wire:submit="create" class="space-y-6">
                <flux:heading size="lg">{{ __('Add software') }}</flux:heading>
                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:input wire:model="vendor" :label="__('Vendor')" />
                <flux:select wire:model="license_type" :label="__('License type')">
                    @foreach (App\Enums\SoftwareLicenseType::cases() as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="total_seats" type="number" min="1" :label="__('Total seats')" />
                <flux:select wire:model="createStatus" :label="__('Status')">
                    @foreach (App\Enums\SoftwareStatus::cases() as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="expires_at" type="date" :label="__('Expires at')" />
                <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
                </div>
            </form>
        </flux:modal>
</div>
