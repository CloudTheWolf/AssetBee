<?php

use App\Actions\RemoteAdmin\CreateRemotePackage;
use App\Actions\RemoteAdmin\UpdateRemotePackage;
use App\Enums\RemotePackageSource;
use App\Models\RemotePackage;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Remote packages')] class extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';

    public string $name = '';

    public string $source = 'winget';

    public string $msi_url = '';

    public string $msi_args = '';

    public string $winget_id = '';

    public string $choco_id = '';

    public string $silent_args = '';

    public string $sha256 = '';

    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', RemotePackage::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->reset([
            'name', 'msi_url', 'msi_args', 'winget_id', 'choco_id', 'silent_args', 'sha256', 'editingId',
        ]);
        $this->source = RemotePackageSource::Winget->value;
    }

    public function edit(RemotePackage $remotePackage): void
    {
        $this->authorize('update', $remotePackage);
        abort_unless($remotePackage->organization_id === CurrentOrganization::require()->id, 404);

        $this->editingId = $remotePackage->id;
        $this->name = $remotePackage->name;
        $this->source = $remotePackage->source->value;
        $this->msi_url = (string) $remotePackage->msi_url;
        $this->msi_args = (string) $remotePackage->msi_args;
        $this->winget_id = (string) $remotePackage->winget_id;
        $this->choco_id = (string) $remotePackage->choco_id;
        $this->silent_args = (string) $remotePackage->silent_args;
        $this->sha256 = (string) $remotePackage->sha256;

        Flux::modal('package-form')->show();
    }

    public function save(CreateRemotePackage $createRemotePackage, UpdateRemotePackage $updateRemotePackage): void
    {
        $payload = [
            'name' => $this->name,
            'source' => $this->source,
            'msi_url' => $this->msi_url !== '' ? $this->msi_url : null,
            'msi_args' => $this->msi_args !== '' ? $this->msi_args : null,
            'winget_id' => $this->winget_id !== '' ? $this->winget_id : null,
            'choco_id' => $this->choco_id !== '' ? $this->choco_id : null,
            'silent_args' => $this->silent_args !== '' ? $this->silent_args : null,
            'sha256' => $this->sha256 !== '' ? $this->sha256 : null,
        ];

        if ($this->editingId !== null) {
            $package = RemotePackage::query()
                ->where('organization_id', CurrentOrganization::require()->id)
                ->findOrFail($this->editingId);
            $this->authorize('update', $package);
            $updateRemotePackage->handle($package, $payload);
            Flux::toast(variant: 'success', text: __('Package updated.'));
        } else {
            $this->authorize('create', RemotePackage::class);
            $createRemotePackage->handle(CurrentOrganization::require(), $payload);
            Flux::toast(variant: 'success', text: __('Package created.'));
        }

        $this->resetForm();
        Flux::modal('package-form')->close();
    }

    public function delete(RemotePackage $remotePackage): void
    {
        $this->authorize('delete', $remotePackage);
        abort_unless($remotePackage->organization_id === CurrentOrganization::require()->id, 404);
        $remotePackage->delete();
        Flux::toast(variant: 'success', text: __('Package deleted.'));
    }

    #[Computed]
    public function packages()
    {
        return RemotePackage::query()
            ->where('organization_id', CurrentOrganization::require()->id)
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate(25);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Remote packages') }}</flux:heading>
            <flux:text>{{ __('Curated MSI, winget, and Chocolatey packages for remote installs.') }}</flux:text>
        </div>
        @can('create', App\Models\RemotePackage::class)
            <flux:modal.trigger name="package-form" wire:click="resetForm">
                <flux:button variant="primary" icon="plus">{{ __('Add package') }}</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search packages…')" icon="magnifying-glass" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Source') }}</flux:table.column>
            <flux:table.column>{{ __('Identifier') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->packages as $package)
                <flux:table.row wire:key="package-{{ $package->id }}">
                    <flux:table.cell class="font-medium">{{ $package->name }}</flux:table.cell>
                    <flux:table.cell>{{ $package->source->label() }}</flux:table.cell>
                    <flux:table.cell>
                        <code class="text-sm">
                            {{ $package->winget_id ?: $package->choco_id ?: $package->msi_url ?: '—' }}
                        </code>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:button size="sm" wire:click="edit({{ $package->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="sm" variant="danger" wire:click="delete({{ $package->id }})" wire:confirm="{{ __('Delete this package?') }}">
                                {{ __('Delete') }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">{{ __('No packages in the catalog yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->packages->links() }}

    <flux:modal name="package-form" class="md:w-[32rem]">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit package') : __('New package') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:select wire:model.live="source" :label="__('Source')">
                @foreach (App\Enums\RemotePackageSource::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </flux:select>

            @if ($source === 'msi')
                <flux:input wire:model="msi_url" :label="__('MSI HTTPS URL')" required />
                <flux:input wire:model="msi_args" :label="__('Extra msiexec args')" />
                <flux:input wire:model="sha256" :label="__('SHA-256 (optional)')" />
            @elseif ($source === 'winget')
                <flux:input wire:model="winget_id" :label="__('Winget package ID')" required />
            @else
                <flux:input wire:model="choco_id" :label="__('Chocolatey package ID')" required />
            @endif

            <flux:input wire:model="silent_args" :label="__('Silent args (optional)')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost" wire:click="resetForm">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
