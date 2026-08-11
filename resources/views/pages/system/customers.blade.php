<?php

use App\Enums\OrganizationRole;
use App\Enums\UserAccountType;
use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('System customers')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Organization>
     */
    #[Computed]
    public function customers(): LengthAwarePaginator
    {
        return Organization::query()
            ->with([
                'googleDomains:id,organization_id,domain',
                'users' => fn ($query) => $query
                    ->where('users.account_type', UserAccountType::Customer)
                    ->where('organization_user.role', OrganizationRole::Owner)
                    ->orderBy('users.name'),
            ])
            ->withCount([
                'users as members_count' => fn ($query) => $query->where('users.account_type', UserAccountType::Customer),
                'userwares',
                'hardwares',
                'virtualwares',
                'softwares',
                'cloudTenants',
            ])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('slug', 'like', '%'.$this->search.'%')
                        ->orWhereHas('googleDomains', fn ($query) => $query->where('domain', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('users', fn ($query) => $query
                            ->where('organization_user.role', OrganizationRole::Owner)
                            ->where(function ($query): void {
                                $query->where('users.name', 'like', '%'.$this->search.'%')
                                    ->orWhere('users.email', 'like', '%'.$this->search.'%');
                            }));
                });
            })
            ->orderBy('name')
            ->paginate(15);
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Customers') }}</flux:heading>
        <flux:text>{{ __('Select a customer explicitly before viewing or changing its data.') }}</flux:text>
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        :placeholder="__('Search customers, domains, or owners...')"
    />

    <flux:table :paginate="$this->customers">
        <flux:table.columns>
            <flux:table.column>{{ __('Customer') }}</flux:table.column>
            <flux:table.column>{{ __('Domains') }}</flux:table.column>
            <flux:table.column>{{ __('Owners') }}</flux:table.column>
            <flux:table.column>{{ __('Members') }}</flux:table.column>
            <flux:table.column>{{ __('Assets') }}</flux:table.column>
            <flux:table.column>{{ __('Created') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->customers as $organization)
                <flux:table.row :key="$organization->id">
                    <flux:table.cell>
                        <div class="font-medium">{{ $organization->name }}</div>
                        <flux:text>{{ $organization->slug }}</flux:text>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $organization->googleDomains->pluck('domain')->join(', ') ?: '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @forelse ($organization->users as $owner)
                            <div>{{ $owner->name }}</div>
                            <flux:text>{{ $owner->email }}</flux:text>
                        @empty
                            —
                        @endforelse
                    </flux:table.cell>
                    <flux:table.cell>{{ $organization->members_count }}</flux:table.cell>
                    <flux:table.cell>
                        <div>{{ __('Userware: :count', ['count' => $organization->userwares_count]) }}</div>
                        <div>{{ __('Hardware: :count', ['count' => $organization->hardwares_count]) }}</div>
                        <div>{{ __('Virtualware: :count', ['count' => $organization->virtualwares_count]) }}</div>
                        <div>{{ __('Software: :count', ['count' => $organization->softwares_count]) }}</div>
                        <div>{{ __('Cloud tenants: :count', ['count' => $organization->cloud_tenants_count]) }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $organization->created_at?->format('j M Y') }}</flux:table.cell>
                    <flux:table.cell>
                        <form method="POST" action="{{ route('organizations.switch', $organization) }}">
                            @csrf
                            <flux:button type="submit" size="sm" variant="primary" class="w-full">
                                {{ __('Manage customer') }}
                            </flux:button>
                        </form>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">
                        <div class="py-8 text-center">
                            <flux:text>{{ __('No customers match your search.') }}</flux:text>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
