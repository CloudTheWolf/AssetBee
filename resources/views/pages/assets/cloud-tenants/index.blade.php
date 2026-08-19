<?php

use App\Actions\Assets\CreateCloudTenant;
use App\Actions\Assets\DeleteCloudTenant;
use App\Enums\CloudTenantProvider;
use App\Enums\CloudTenantStatus;
use App\Models\CloudTenant;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Cloud Tenants')] class extends Component {
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public string $name = '';

    public string $provider = 'microsoft365';

    public string $external_id = '';

    public string $domain = '';

    public string $createStatus = 'active';

    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', CloudTenant::class);
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

    public function create(CreateCloudTenant $createCloudTenant): void
    {
        $this->authorize('create', CloudTenant::class);

        $createCloudTenant->handle(CurrentOrganization::require(), [
            'name' => $this->name,
            'provider' => $this->provider,
            'external_id' => $this->external_id !== '' ? $this->external_id : null,
            'domain' => $this->domain !== '' ? $this->domain : null,
            'status' => $this->createStatus,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ]);

        $this->reset(['name', 'external_id', 'domain', 'notes']);
        $this->provider = CloudTenantProvider::Microsoft365->value;
        $this->createStatus = CloudTenantStatus::Active->value;

        Flux::modal('create-cloud-tenant')->close();
        Flux::toast(variant: 'success', text: __('Cloud tenant created.'));
    }

    public function delete(CloudTenant $cloudTenant, DeleteCloudTenant $deleteCloudTenant): void
    {
        $this->authorize('delete', $cloudTenant);
        $deleteCloudTenant->handle($cloudTenant);
        Flux::toast(variant: 'success', text: __('Cloud tenant deleted.'));
    }

    #[Computed]
    public function cloudTenants()
    {
        $sortable = ['name', 'provider', 'status', 'domain'];
        $sortBy = in_array($this->sortBy, $sortable, true) ? $this->sortBy : 'name';

        return CloudTenant::query()
            ->withCount('virtualwares')
            ->where('organization_id', CurrentOrganization::require()->id)
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('domain', 'like', '%'.$this->search.'%')
                        ->orWhere('external_id', 'like', '%'.$this->search.'%');
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
            <flux:heading size="xl">{{ __('Cloud Tenants') }}</flux:heading>
            <flux:text>{{ __('Cloud accounts that host virtualware.') }}</flux:text>
        </div>
        @can('create', App\Models\CloudTenant::class)
            <flux:modal.trigger name="create-cloud-tenant">
                <flux:button variant="primary" icon="plus">{{ __('Add tenant') }}</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search name, domain, external ID...')" class="flex-1" />
        <flux:select wire:model.live="status" class="sm:w-48">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (App\Enums\CloudTenantStatus::cases() as $statusOption)
                <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->cloudTenants">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'provider'" :direction="$sortDirection" wire:click="sort('provider')">{{ __('Provider') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'domain'" :direction="$sortDirection" wire:click="sort('domain')">{{ __('Domain') }}</flux:table.column>
            <flux:table.column>{{ __('Virtualware') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->cloudTenants as $tenant)
                <flux:table.row :key="$tenant->id">
                    <flux:table.cell>
                        <a href="{{ route('assets.cloud-tenants.show', $tenant) }}" class="font-medium text-accent" wire:navigate>{{ $tenant->name }}</a>
                        @if ($tenant->external_id)
                            <div class="text-xs text-zinc-500">{{ $tenant->external_id }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $tenant->provider->label() }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $tenant->domain ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $tenant->virtualwares_count }}</flux:table.cell>
                    <flux:table.cell class="py-0">
                        <flux:badge size="sm" :color="$tenant->status->color()">{{ $tenant->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end">
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item :href="route('assets.cloud-tenants.show', $tenant)" wire:navigate icon="eye">{{ __('View') }}</flux:menu.item>
                                    @can('delete', $tenant)
                                        <flux:menu.separator />
                                        <flux:menu.item variant="danger" icon="trash" wire:click="delete({{ $tenant->id }})" wire:confirm="{{ __('Delete this cloud tenant?') }}">
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
                            <flux:heading size="sm">{{ __('No cloud tenants yet') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Add a tenant to link virtualware to a cloud account.') }}</flux:text>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="create-cloud-tenant" class="max-w-lg">
        <form wire:submit="create" class="space-y-6">
            <flux:heading size="lg">{{ __('Add cloud tenant') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:select wire:model="provider" :label="__('Provider')">
                @foreach (App\Enums\CloudTenantProvider::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="external_id" :label="__('External ID')" />
            <flux:input wire:model="domain" :label="__('Domain')" />
            <flux:select wire:model="createStatus" :label="__('Status')">
                @foreach (App\Enums\CloudTenantStatus::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
