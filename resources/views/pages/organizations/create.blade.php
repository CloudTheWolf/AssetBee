<?php

use App\Actions\Organizations\CreateOrganization;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create organization')] class extends Component {
    use AuthorizesRequests;

    public string $name = '';

    public string $google_hosted_domains = '';

    public function mount(): void
    {
        $this->authorize('create', \App\Models\Organization::class);
    }

    public function create(CreateOrganization $createOrganization): void
    {
        $this->authorize('create', \App\Models\Organization::class);

        $createOrganization->handle(Auth::user(), [
            'name' => $this->name,
            'google_hosted_domains' => $this->google_hosted_domains,
        ]);

        Flux::toast(variant: 'success', text: __('Organization created.'));

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="mx-auto flex w-full max-w-lg flex-col gap-6">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl">{{ __('Create an organization') }}</flux:heading>
        <flux:text>{{ __('Organizations hold your hardware, virtualware, software, and userware inventory.') }}</flux:text>
    </div>

    <form wire:submit="create" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:input wire:model="name" :label="__('Organization name')" required autofocus />
        <flux:textarea
            wire:model="google_hosted_domains"
            :label="__('Google Workspace domains')"
            :description="__('Optional. Comma or newline separated. Users signing in from these domains join automatically.')"
            placeholder="example.com, example.org"
            rows="3"
        />

        <div class="flex justify-end">
            <flux:button variant="primary" type="submit">{{ __('Create organization') }}</flux:button>
        </div>
    </form>
</div>
