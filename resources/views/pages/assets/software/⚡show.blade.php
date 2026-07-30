<?php

use App\Actions\Assets\AssignSoftwareSeat;
use App\Actions\Assets\DeleteSoftware;
use App\Actions\Assets\UnassignSoftwareSeat;
use App\Actions\Assets\UpdateSoftware;
use App\Enums\SoftwareLicenseType;
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

    public string $status = '';

    public string $expires_at = '';

    public string $notes = '';

    public string $assign_userware_id = '';

    public function mount(Software $software): void
    {
        $this->authorize('view', $software);
        abort_unless($software->organization_id === CurrentOrganization::require()->id, 404);

        $this->software = $software->load(['assignments.userware']);
        $this->fillForm();
    }

    public function fillForm(): void
    {
        $this->name = $this->software->name;
        $this->vendor = (string) ($this->software->vendor ?? '');
        $this->license_type = $this->software->license_type->value;
        $this->total_seats = (string) ($this->software->total_seats ?? '');
        $this->status = $this->software->status->value;
        $this->expires_at = $this->software->expires_at?->format('Y-m-d') ?? '';
        $this->notes = (string) ($this->software->notes ?? '');
    }

    public function save(UpdateSoftware $updateSoftware): void
    {
        $this->authorize('update', $this->software);

        $this->software = $updateSoftware->handle($this->software, [
            'name' => $this->name,
            'vendor' => $this->vendor !== '' ? $this->vendor : null,
            'license_type' => $this->license_type,
            'total_seats' => $this->total_seats !== '' ? (int) $this->total_seats : null,
            'status' => $this->status,
            'expires_at' => $this->expires_at !== '' ? $this->expires_at : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ])->load(['assignments.userware']);

        Flux::toast(variant: 'success', text: __('Software updated.'));
    }

    public function assignSeat(AssignSoftwareSeat $assignSoftwareSeat): void
    {
        $this->authorize('assign', $this->software);

        $userware = Userware::query()
            ->where('organization_id', CurrentOrganization::require()->id)
            ->findOrFail($this->assign_userware_id);

        $assignSoftwareSeat->handle($this->software, $userware);
        $this->software->load(['assignments.userware']);
        $this->assign_userware_id = '';

        Flux::toast(variant: 'success', text: __('Seat assigned.'));
    }

    public function unassignSeat(SoftwareAssignment $assignment, UnassignSoftwareSeat $unassignSoftwareSeat): void
    {
        $this->authorize('delete', $assignment);
        abort_unless($assignment->software_id === $this->software->id, 404);

        $unassignSoftwareSeat->handle($assignment);
        $this->software->load(['assignments.userware']);

        Flux::toast(variant: 'success', text: __('Seat unassigned.'));
    }

    public function delete(DeleteSoftware $deleteSoftware): void
    {
        $this->authorize('delete', $this->software);
        $deleteSoftware->handle($this->software);
        $this->redirect(route('assets.software.index', absolute: false), navigate: true);
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
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="flex items-center gap-3">
            <flux:button size="sm" :href="route('assets.software.index')" wire:navigate icon="arrow-left">{{ __('Back') }}</flux:button>
            <div>
                <flux:heading size="xl">{{ $software->name }}</flux:heading>
                <flux:text>
                    {{ $software->license_type->label() }}
                    @if ($software->license_type === SoftwareLicenseType::Seat)
                        · {{ $software->assignments->count() }} / {{ $software->total_seats }} {{ __('seats') }}
                    @endif
                </flux:text>
            </div>
        </div>

        <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:input wire:model="name" :label="__('Name')" required @disabled(! auth()->user()->can('update', $software)) />
            <flux:input wire:model="vendor" :label="__('Vendor')" @disabled(! auth()->user()->can('update', $software)) />
            <flux:select wire:model="license_type" :label="__('License type')" @disabled(! auth()->user()->can('update', $software))>
                @foreach (SoftwareLicenseType::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="total_seats" type="number" min="1" :label="__('Total seats')" @disabled(! auth()->user()->can('update', $software)) />
            <flux:select wire:model="status" :label="__('Status')" @disabled(! auth()->user()->can('update', $software))>
                @foreach (SoftwareStatus::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="expires_at" type="date" :label="__('Expires at')" @disabled(! auth()->user()->can('update', $software)) />
            <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" @disabled(! auth()->user()->can('update', $software)) />
            @can('update', $software)
                <div class="flex justify-between">
                    <flux:button variant="danger" type="button" wire:click="delete" wire:confirm="{{ __('Delete this software?') }}">{{ __('Delete') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </div>
            @endcan
        </form>

        @can('assign', $software)
            <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Seat assignments') }}</flux:heading>

                <form wire:submit="assignSeat" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <flux:select wire:model="assign_userware_id" :label="__('Identity')" class="flex-1" required>
                        <option value="">{{ __('Select identity') }}</option>
                        @foreach ($this->identities as $identity)
                            <option value="{{ $identity->id }}">{{ $identity->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:button variant="primary" type="submit">{{ __('Assign seat') }}</flux:button>
                </form>

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
