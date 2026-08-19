<?php

use App\Actions\Assets\BulkAssignSoftwareSeats;
use App\Actions\Assets\DeleteSoftware;
use App\Actions\Assets\UnassignSoftwareSeat;
use App\Actions\Assets\UpdateSoftware;
use App\Enums\SoftwareBillingInterval;
use App\Enums\SoftwareLicenseType;
use App\Enums\SoftwareSeatManagerType;
use App\Enums\SoftwareStatus;
use App\Models\Software;
use App\Models\SoftwareAssignment;
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

    public function mount(Software $software): void
    {
        $this->authorize('view', $software);
        abort_unless($software->organization_id === CurrentOrganization::require()->id, 404);

        $this->software = $software->load(['assignments.userware', 'seatManagerUserware']);
        $this->fillForm();
    }

    public function fillForm(): void
    {
        $this->name = $this->software->name;
        $this->vendor = (string) ($this->software->vendor ?? '');
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
        ])->load(['assignments.userware', 'seatManagerUserware']);

        $this->fillForm();

        Flux::toast(variant: 'success', text: __('Software updated.'));
    }

    public function assignSeats(BulkAssignSoftwareSeats $bulkAssignSoftwareSeats): void
    {
        $this->authorize('assign', $this->software);

        $result = $bulkAssignSoftwareSeats->handle($this->software, $this->selectedUserwareIds);

        $this->software->load(['assignments.userware']);
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

        $this->software->load(['assignments.userware']);
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
        $this->software->load(['assignments.userware']);
        unset($this->identities);

        Flux::toast(variant: 'success', text: __('Seat unassigned.'));
    }

    public function delete(DeleteSoftware $deleteSoftware): void
    {
        $this->authorize('delete', $this->software);
        $deleteSoftware->handle($this->software);
        $this->redirect(route('assets.software.index', absolute: false));
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
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="flex items-center gap-3">
        <flux:button size="sm" :href="route('assets.software.index')" icon="arrow-left">{{ __('Back') }}</flux:button>
        <div>
            <flux:heading size="xl">{{ $software->name }}</flux:heading>
            <flux:text>
                {{ $software->license_type->label() }}
                @if ($software->license_type === SoftwareLicenseType::Seat)
                    · {{ $software->assignments->count() }} / {{ $software->total_seats }} {{ __('seats') }}
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

    <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:input wire:model="name" :label="__('Name')" required :disabled="! auth()->user()->can('update', $software)" />
        <flux:input wire:model="vendor" :label="__('Vendor')" :disabled="! auth()->user()->can('update', $software)" />
        <flux:select wire:model="license_type" :label="__('License type')" :disabled="! auth()->user()->can('update', $software)">
            @foreach (SoftwareLicenseType::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model="total_seats" type="number" min="1" :label="__('Total seats')" :disabled="! auth()->user()->can('update', $software)" />

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

    <livewire:asset-documents :documentable="$software" :key="'software-docs-'.$software->id" />

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
</div>
