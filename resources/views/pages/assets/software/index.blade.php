<?php

use App\Actions\Assets\CreateSoftware;
use App\Actions\Assets\DeleteSoftware;
use App\Enums\SoftwareBillingInterval;
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

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public string $name = '';

    public string $vendor = '';

    public string $license_type = 'seat';

    public string $total_seats = '10';

    public string $createStatus = 'active';

    public string $expires_at = '';

    public bool $is_recurring = false;

    public string $billing_interval = 'monthly';

    public string $billing_amount = '';

    public string $currency = 'GBP';

    public string $next_billing_at = '';

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

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
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
            'is_recurring' => $this->is_recurring,
            'billing_interval' => $this->is_recurring ? $this->billing_interval : null,
            'billing_amount' => $this->is_recurring && $this->billing_amount !== '' ? $this->billing_amount : null,
            'currency' => $this->currency !== '' ? $this->currency : 'GBP',
            'next_billing_at' => $this->is_recurring && $this->next_billing_at !== '' ? $this->next_billing_at : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ]);

        $this->reset(['name', 'vendor', 'expires_at', 'billing_amount', 'next_billing_at', 'notes', 'is_recurring']);
        $this->license_type = SoftwareLicenseType::Seat->value;
        $this->total_seats = '10';
        $this->createStatus = SoftwareStatus::Active->value;
        $this->billing_interval = SoftwareBillingInterval::Monthly->value;
        $this->currency = 'GBP';

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
        $sortable = ['name', 'vendor', 'license_type', 'status', 'next_billing_at'];
        $sortBy = in_array($this->sortBy, $sortable, true) ? $this->sortBy : 'name';

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
            ->orderBy($sortBy, $this->sortDirection === 'desc' ? 'desc' : 'asc')
            ->paginate(10);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Software') }}</flux:heading>
            <flux:text>{{ __('Licenses, seats, and recurring subscriptions.') }}</flux:text>
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

    <flux:table :paginate="$this->softwares">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'vendor'" :direction="$sortDirection" wire:click="sort('vendor')">{{ __('Vendor') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'license_type'" :direction="$sortDirection" wire:click="sort('license_type')">{{ __('License') }}</flux:table.column>
            <flux:table.column>{{ __('Seats / Billing') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->softwares as $software)
                <flux:table.row :key="$software->id">
                    <flux:table.cell>
                        <a href="{{ route('assets.software.show', $software) }}" class="font-medium text-accent" wire:navigate>{{ $software->name }}</a>
                        @if ($software->is_recurring && $software->next_billing_at)
                            <div class="text-xs text-zinc-500">{{ __('Next billing') }} {{ $software->next_billing_at->format('M j, Y') }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $software->vendor ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $software->license_type->label() }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($software->license_type === App\Enums\SoftwareLicenseType::Seat)
                            {{ $software->assignments_count }} / {{ $software->total_seats }}
                        @elseif ($software->is_recurring)
                            {{ $software->formattedBillingAmount() ?? '—' }}
                            @if ($software->billing_interval)
                                <span class="text-zinc-500">/ {{ strtolower($software->billing_interval->label()) }}</span>
                            @endif
                        @else
                            —
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="py-0">
                        <flux:badge size="sm" :color="$software->status->color()">{{ $software->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end">
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item :href="route('assets.software.show', $software)" wire:navigate icon="eye">{{ __('View') }}</flux:menu.item>
                                    @can('delete', $software)
                                        <flux:menu.separator />
                                        <flux:menu.item variant="danger" icon="trash" wire:click="delete({{ $software->id }})" wire:confirm="{{ __('Delete this software?') }}">
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
                    <flux:table.cell colspan="6">
                        <div class="py-10 text-center">
                            <flux:heading size="sm">{{ __('No software found') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Add a license or subscription to track software.') }}</flux:text>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

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
            <flux:checkbox wire:model.live="is_recurring" :label="__('Recurring subscription')" />
            @if ($is_recurring)
                <flux:select wire:model="billing_interval" :label="__('Billing interval')">
                    @foreach (App\Enums\SoftwareBillingInterval::cases() as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </flux:select>
                <div class="grid grid-cols-2 gap-3">
                    <flux:input wire:model="billing_amount" type="number" step="0.01" min="0" :label="__('Amount')" />
                    <flux:input wire:model="currency" maxlength="3" :label="__('Currency')" />
                </div>
                <flux:input wire:model="next_billing_at" type="date" :label="__('Next billing date')" />
            @endif
            <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
