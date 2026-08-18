<?php

use App\Models\SystemAudit;
use App\Support\CurrentOrganization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Audit log')] class extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $action = '';

    public function mount(): void
    {
        $this->authorize('viewAuditLog', CurrentOrganization::require());
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingAction(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, SystemAudit>
     */
    #[Computed]
    public function audits(): LengthAwarePaginator
    {
        $organization = CurrentOrganization::require();
        $memberIds = $organization->users()->pluck('users.id')->all();

        return SystemAudit::query()
            ->with('actor')
            ->visibleToOrganization($organization, $memberIds)
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('action', 'like', $term)
                        ->orWhere('actor_name', 'like', $term)
                        ->orWhere('summary', 'like', $term)
                        ->orWhere('ip_address', 'like', $term)
                        ->orWhere('target_type', 'like', $term)
                        ->orWhereHas('actor', function ($query) use ($term): void {
                            $query->where('name', 'like', $term)
                                ->orWhere('email', 'like', $term);
                        });
                });
            })
            ->when($this->action !== '', function ($query): void {
                match ($this->action) {
                    'created' => $query->where('action', 'like', '%.created'),
                    'updated' => $query->where('action', 'like', '%.updated'),
                    'deleted' => $query->where('action', 'like', '%.deleted'),
                    'auth' => $query->where('action', 'like', 'auth.%'),
                    'members' => $query->where(function ($query): void {
                        $query->where('action', 'like', 'organization_member.%')
                            ->orWhere('action', 'like', 'organization_invitation.%')
                            ->orWhere('action', 'like', 'organization_user.%');
                    }),
                    default => $query->where('action', $this->action),
                };
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(25);
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Audit log') }}</flux:heading>
        <flux:text>{{ __('Actions taken in :name, including asset changes, membership, and sign-in events.', ['name' => CurrentOrganization::require()->name]) }}</flux:text>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :placeholder="__('Search actor, action, target, or IP...')"
            class="flex-1"
        />
        <flux:select wire:model.live="action" class="sm:w-48">
            <option value="">{{ __('All actions') }}</option>
            <option value="created">{{ __('Created') }}</option>
            <option value="updated">{{ __('Updated') }}</option>
            <option value="deleted">{{ __('Deleted') }}</option>
            <option value="auth">{{ __('Sign-in and security') }}</option>
            <option value="members">{{ __('Members') }}</option>
        </flux:select>
    </div>

    <flux:table :paginate="$this->audits">
        <flux:table.columns>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column>{{ __('Actor') }}</flux:table.column>
            <flux:table.column>{{ __('Action') }}</flux:table.column>
            <flux:table.column>{{ __('Target') }}</flux:table.column>
            <flux:table.column>{{ __('IP address') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->audits as $audit)
                <flux:table.row :key="$audit->id">
                    <flux:table.cell class="whitespace-nowrap">
                        <span title="{{ $audit->occurred_at->timezone(config('app.timezone'))->toDayDateTimeString() }}">
                            {{ $audit->occurred_at->diffForHumans() }}
                        </span>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="font-medium">{{ $audit->actorLabel() }}</div>
                        @if ($audit->actor?->email)
                            <flux:text>{{ $audit->actor->email }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $audit->actionLabel() }}</flux:table.cell>
                    <flux:table.cell>{{ $audit->targetLabel() }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $audit->ip_address ?? '—' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <div class="py-8 text-center">
                            <flux:text>{{ __('No audit entries match your filters.') }}</flux:text>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
