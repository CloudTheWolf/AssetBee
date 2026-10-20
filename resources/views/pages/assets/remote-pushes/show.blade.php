<?php

use App\Actions\RemoteAdmin\CancelRemotePush;
use App\Enums\RemotePushTargetStatus;
use App\Models\RemotePush;
use App\Models\RemotePushTarget;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Remote push')] class extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public RemotePush $remotePush;

    public bool $incompleteOnly = true;

    public bool $showTargetDetailModal = false;

    public ?int $selectedTargetId = null;

    public function mount(RemotePush $remotePush): void
    {
        $this->authorize('view', $remotePush);
        abort_unless($remotePush->organization_id === CurrentOrganization::require()->id, 404);
        $this->remotePush = $remotePush->load(['package', 'creator']);
    }

    public function cancel(CancelRemotePush $cancelRemotePush): void
    {
        $this->authorize('update', $this->remotePush);
        $cancelRemotePush->handle($this->remotePush);
        $this->remotePush->refresh();
        Flux::toast(variant: 'success', text: __('Push cancelled.'));
    }

    public function openTargetDetail(int $targetId): void
    {
        $this->authorize('view', $this->remotePush);

        $exists = $this->remotePush->targets()->whereKey($targetId)->exists();
        abort_unless($exists, 404);

        $this->selectedTargetId = $targetId;
        $this->showTargetDetailModal = true;
    }

    public function closeTargetDetailModal(): void
    {
        $this->showTargetDetailModal = false;
        $this->selectedTargetId = null;
    }

    #[Computed]
    public function targets()
    {
        return $this->remotePush->targets()
            ->with('hardware')
            ->when(
                $this->incompleteOnly,
                fn ($query) => $query->whereIn(
                    'status',
                    array_map(fn ($status) => $status->value, RemotePushTargetStatus::incompleteCases()),
                ),
            )
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function selectedTarget(): ?RemotePushTarget
    {
        if ($this->selectedTargetId === null) {
            return null;
        }

        return $this->remotePush->targets()
            ->with('hardware')
            ->whereKey($this->selectedTargetId)
            ->first();
    }

    #[Computed]
    public function incompleteCount(): int
    {
        return $this->remotePush->targets()
            ->whereIn(
                'status',
                array_map(fn ($status) => $status->value, RemotePushTargetStatus::incompleteCases()),
            )
            ->count();
    }
}; ?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ $remotePush->type->label() }}</flux:heading>
            <flux:text>
                {{ $remotePush->package?->name ? __('Package: :name', ['name' => $remotePush->package->name]) : __('Probe command') }}
                ·
                <flux:badge :color="$remotePush->status->color()">{{ $remotePush->status->label() }}</flux:badge>
            </flux:text>
            @if ($remotePush->notes)
                <flux:text class="mt-2">{{ $remotePush->notes }}</flux:text>
            @endif
        </div>
        <div class="flex gap-2">
            <flux:button size="sm" :href="route('assets.remote-pushes.index')" wire:navigate icon="arrow-left">{{ __('Back') }}</flux:button>
            @if ($remotePush->status === App\Enums\RemotePushStatus::Open)
                <flux:button size="sm" variant="danger" wire:click="cancel" wire:confirm="{{ __('Cancel this push for remaining devices?') }}">
                    {{ __('Cancel push') }}
                </flux:button>
            @endif
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="lg">{{ __('Targets') }}</flux:heading>
        <flux:checkbox wire:model.live="incompleteOnly" :label="__('Show incomplete only (:count)', ['count' => $this->incompleteCount])" />
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Device') }}</flux:table.column>
            <flux:table.column>{{ __('Serial') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Scheduled') }}</flux:table.column>
            <flux:table.column>{{ __('Deadline') }}</flux:table.column>
            <flux:table.column>{{ __('Detail') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->targets as $target)
                <flux:table.row wire:key="target-{{ $target->id }}">
                    <flux:table.cell>
                        <button
                            type="button"
                            class="font-medium text-accent hover:underline"
                            wire:click="openTargetDetail({{ $target->id }})"
                        >
                            {{ $target->hardware->name }}
                        </button>
                    </flux:table.cell>
                    <flux:table.cell>{{ $target->hardware->serial_number ?: '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$target->status->color()">{{ $target->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $target->scheduled_for?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $target->postpone_deadline_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($target->error)
                            <span class="text-red-600 dark:text-red-400">{{ $target->error }}</span>
                        @elseif ($target->result_json)
                            <code class="text-xs">{{ \Illuminate\Support\Str::limit(json_encode($target->result_json), 80) }}</code>
                        @else
                            —
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        {{ $incompleteOnly ? __('All targets have finished.') : __('No targets.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showTargetDetailModal" class="max-w-2xl" @close="closeTargetDetailModal">
        @if ($this->selectedTarget)
            @php($target = $this->selectedTarget)
            <div class="space-y-6">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ $target->hardware->name }}</flux:heading>
                    <flux:text>
                        {{ $target->hardware->serial_number ?: __('No serial number') }}
                        ·
                        <flux:badge :color="$target->status->color()">{{ $target->status->label() }}</flux:badge>
                    </flux:text>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <flux:text class="font-medium">{{ __('Scheduled') }}</flux:text>
                        <div>{{ $target->scheduled_for?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}</div>
                    </div>
                    <div>
                        <flux:text class="font-medium">{{ __('Postpone deadline') }}</flux:text>
                        <div>{{ $target->postpone_deadline_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}</div>
                    </div>
                    <div>
                        <flux:text class="font-medium">{{ __('First notified') }}</flux:text>
                        <div>{{ $target->first_notified_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}</div>
                    </div>
                    <div>
                        <flux:text class="font-medium">{{ __('Completed') }}</flux:text>
                        <div>{{ $target->completed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}</div>
                    </div>
                </div>

                @if ($target->error)
                    <div class="space-y-2">
                        <flux:text class="font-medium">{{ __('Error') }}</flux:text>
                        <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                            {{ $target->error }}
                        </div>
                    </div>
                @endif

                <div class="space-y-2">
                    <flux:text class="font-medium">{{ __('Result') }}</flux:text>
                    @if ($target->result_json)
                        <pre class="max-h-96 overflow-auto rounded-lg border border-zinc-200 bg-zinc-50 p-3 font-mono text-xs dark:border-zinc-700 dark:bg-zinc-900">{{ json_encode($target->result_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    @else
                        <flux:text>{{ __('No result payload yet.') }}</flux:text>
                    @endif
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" :href="route('assets.hardware.show', $target->hardware)" wire:navigate>
                        {{ __('Open device') }}
                    </flux:button>
                    <flux:button variant="primary" wire:click="closeTargetDetailModal">{{ __('Close') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
