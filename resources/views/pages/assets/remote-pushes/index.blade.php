<?php

use App\Actions\RemoteAdmin\DeleteRemotePush;
use App\Enums\RemotePushStatus;
use App\Models\RemotePush;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Remote pushes')] class extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', RemotePush::class);
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function delete(RemotePush $remotePush, DeleteRemotePush $deleteRemotePush): void
    {
        $this->authorize('delete', $remotePush);
        abort_unless($remotePush->organization_id === CurrentOrganization::require()->id, 404);

        $deleteRemotePush->handle($remotePush);
        Flux::toast(variant: 'success', text: __('Remote push deleted.'));
    }

    #[Computed]
    public function pushes()
    {
        return RemotePush::query()
            ->with(['packages', 'package', 'creator'])
            ->withCount([
                'targets',
                'targets as incomplete_targets_count' => fn ($query) => $query->whereIn(
                    'status',
                    array_map(fn ($status) => $status->value, App\Enums\RemotePushTargetStatus::incompleteCases()),
                ),
            ])
            ->where('organization_id', CurrentOrganization::require()->id)
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->latest()
            ->paginate(25);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Remote pushes') }}</flux:heading>
            <flux:text>{{ __('Probe and install commands sent to Drone agents.') }}</flux:text>
        </div>
        @can('create', App\Models\RemotePush::class)
            <flux:button variant="primary" icon="plus" :href="route('assets.remote-pushes.create')" wire:navigate>
                {{ __('New push') }}
            </flux:button>
        @endcan
    </div>

    <flux:select wire:model.live="status" class="max-w-xs">
        <option value="">{{ __('All statuses') }}</option>
        @foreach (App\Enums\RemotePushStatus::cases() as $option)
            <option value="{{ $option->value }}">{{ $option->label() }}</option>
        @endforeach
    </flux:select>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('Packages') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Targets') }}</flux:table.column>
            <flux:table.column>{{ __('Incomplete') }}</flux:table.column>
            <flux:table.column>{{ __('Created') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->pushes as $push)
                <flux:table.row wire:key="push-{{ $push->id }}">
                    <flux:table.cell class="font-medium">{{ $push->type->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $push->packageSummary() !== '' ? $push->packageSummary() : '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$push->status->color()">{{ $push->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $push->targets_count }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($push->incomplete_targets_count > 0)
                            <span class="font-medium text-amber-600 dark:text-amber-400">{{ $push->incomplete_targets_count }}</span>
                        @else
                            0
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $push->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            <flux:button size="sm" :href="route('assets.remote-pushes.show', $push)" wire:navigate>{{ __('Open') }}</flux:button>
                            @can('delete', $push)
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    wire:click="delete({{ $push->id }})"
                                    wire:confirm="{{ __('Permanently delete this push and all target history?') }}"
                                >
                                    {{ __('Delete') }}
                                </flux:button>
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">{{ __('No remote pushes yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->pushes->links() }}
</div>
