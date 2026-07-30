<?php

use App\Actions\Assets\DeleteCloudTenant;
use App\Actions\Assets\UpdateCloudTenant;
use App\Enums\CloudTenantProvider;
use App\Enums\CloudTenantStatus;
use App\Models\CloudTenant;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Cloud Tenant')] class extends Component {
    use AuthorizesRequests;

    public CloudTenant $cloudTenant;

    public string $name = '';

    public string $provider = '';

    public string $external_id = '';

    public string $domain = '';

    public string $status = '';

    public string $notes = '';

    public function mount(CloudTenant $cloudTenant): void
    {
        $this->authorize('view', $cloudTenant);
        abort_unless($cloudTenant->organization_id === CurrentOrganization::require()->id, 404);

        $this->cloudTenant = $cloudTenant->load('virtualwares');
        $this->fillForm();
    }

    public function fillForm(): void
    {
        $this->name = $this->cloudTenant->name;
        $this->provider = $this->cloudTenant->provider->value;
        $this->external_id = (string) ($this->cloudTenant->external_id ?? '');
        $this->domain = (string) ($this->cloudTenant->domain ?? '');
        $this->status = $this->cloudTenant->status->value;
        $this->notes = (string) ($this->cloudTenant->notes ?? '');
    }

    public function save(UpdateCloudTenant $updateCloudTenant): void
    {
        $this->authorize('update', $this->cloudTenant);

        $this->cloudTenant = $updateCloudTenant->handle($this->cloudTenant, [
            'name' => $this->name,
            'provider' => $this->provider,
            'external_id' => $this->external_id !== '' ? $this->external_id : null,
            'domain' => $this->domain !== '' ? $this->domain : null,
            'status' => $this->status,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ])->load('virtualwares');

        Flux::toast(variant: 'success', text: __('Cloud tenant updated.'));
    }

    public function delete(DeleteCloudTenant $deleteCloudTenant): void
    {
        $this->authorize('delete', $this->cloudTenant);
        $deleteCloudTenant->handle($this->cloudTenant);
        $this->redirect(route('assets.cloud-tenants.index', absolute: false), navigate: true);
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="flex items-center gap-3">
        <flux:button size="sm" :href="route('assets.cloud-tenants.index')" wire:navigate icon="arrow-left">{{ __('Back') }}</flux:button>
        <div>
            <flux:heading size="xl">{{ $cloudTenant->name }}</flux:heading>
            <flux:text>{{ $cloudTenant->provider->label() }} · {{ $cloudTenant->status->label() }}</flux:text>
        </div>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:input wire:model="name" :label="__('Name')" required @disabled(! auth()->user()->can('update', $cloudTenant)) />
        <flux:select wire:model="provider" :label="__('Provider')" @disabled(! auth()->user()->can('update', $cloudTenant))>
            @foreach (CloudTenantProvider::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model="external_id" :label="__('External ID')" @disabled(! auth()->user()->can('update', $cloudTenant)) />
        <flux:input wire:model="domain" :label="__('Domain')" @disabled(! auth()->user()->can('update', $cloudTenant)) />
        <flux:select wire:model="status" :label="__('Status')" @disabled(! auth()->user()->can('update', $cloudTenant))>
            @foreach (CloudTenantStatus::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </flux:select>
        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" @disabled(! auth()->user()->can('update', $cloudTenant)) />
        @can('update', $cloudTenant)
            <div class="flex justify-between">
                <flux:button variant="danger" type="button" wire:click="delete" wire:confirm="{{ __('Delete this cloud tenant?') }}">{{ __('Delete') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        @endcan
    </form>

    <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading size="lg">{{ __('Linked virtualware') }}</flux:heading>
        <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse ($cloudTenant->virtualwares as $virtualware)
                <li class="flex items-center justify-between py-3">
                    <div>
                        <a href="{{ route('assets.virtualware.show', $virtualware) }}" class="font-medium text-accent" wire:navigate>{{ $virtualware->name }}</a>
                        <flux:text>{{ $virtualware->provider->label() }} · {{ $virtualware->status->label() }}</flux:text>
                    </div>
                    <flux:button size="sm" :href="route('assets.virtualware.show', $virtualware)" wire:navigate>{{ __('View') }}</flux:button>
                </li>
            @empty
                <li class="py-3"><flux:text>{{ __('No virtualware linked to this tenant.') }}</flux:text></li>
            @endforelse
        </ul>
    </div>
</div>
