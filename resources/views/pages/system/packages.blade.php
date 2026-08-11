<?php

use App\Actions\Subscriptions\SaveSubscriptionPackage;
use App\Enums\OrganizationLimit;
use App\Enums\SubscriptionBillingInterval;
use App\Models\SubscriptionPackage;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Subscription packages')] class extends Component
{
    public ?int $editingPackageId = null;

    public string $name = '';

    public ?string $description = null;

    public bool $is_active = true;

    public string $price = '0.00';

    public string $currency = 'GBP';

    public string $billing_interval = 'monthly';

    public string $stripe_price_id = '';

    public int $sort_order = 0;

    public ?int $member_limit = null;

    public ?int $userware_limit = null;

    public ?int $hardware_limit = null;

    public ?int $virtualware_limit = null;

    public ?int $software_limit = null;

    public ?int $cloud_tenant_limit = null;

    public ?int $asset_document_limit = null;

    public ?int $userware_account_limit = null;

    public ?int $api_key_limit = null;

    public function mount(): void
    {
        $this->authorizeSystemAccess();
    }

    /** @return Collection<int, SubscriptionPackage> */
    #[Computed]
    public function packages(): Collection
    {
        return SubscriptionPackage::query()
            ->withCount('organizations')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function save(SaveSubscriptionPackage $savePackage): void
    {
        $this->authorizeSystemAccess();
        $package = $this->editingPackageId === null
            ? null
            : SubscriptionPackage::query()->findOrFail($this->editingPackageId);

        $savePackage->handle($package, $this->formData());
        $this->newPackage();
        unset($this->packages);

        Flux::toast(variant: 'success', text: __('Package saved.'));
    }

    public function edit(int $packageId): void
    {
        $this->authorizeSystemAccess();
        $package = SubscriptionPackage::query()->findOrFail($packageId);
        $this->editingPackageId = $package->id;
        $this->name = $package->name;
        $this->description = $package->description;
        $this->is_active = $package->is_active;
        $this->price = $package->price;
        $this->currency = $package->currency;
        $this->billing_interval = $package->billing_interval->value;
        $this->stripe_price_id = $package->stripe_price_id;
        $this->sort_order = $package->sort_order;

        foreach (OrganizationLimit::cases() as $limit) {
            $this->{$limit->value} = $package->getAttribute($limit->value);
        }
    }

    public function newPackage(): void
    {
        $this->editingPackageId = null;
        $this->reset(
            'name', 'description', 'price', 'stripe_price_id',
            ...array_map(fn (OrganizationLimit $limit) => $limit->value, OrganizationLimit::cases()),
        );
        $this->is_active = true;
        $this->currency = 'GBP';
        $this->billing_interval = SubscriptionBillingInterval::Monthly->value;
        $this->sort_order = 0;
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'price' => $this->price,
            'currency' => $this->currency,
            'billing_interval' => $this->billing_interval,
            'stripe_price_id' => $this->stripe_price_id,
            'sort_order' => $this->sort_order,
        ];

        foreach (OrganizationLimit::cases() as $limit) {
            $data[$limit->value] = $this->{$limit->value};
        }

        return $data;
    }

    private function authorizeSystemAccess(): void
    {
        abort_unless(auth()->user()?->hasSystemAccess(), 403);
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Subscription packages') }}</flux:heading>
        <flux:text>{{ __('Create reusable packages that customer organizations can select and purchase.') }}</flux:text>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ $editingPackageId ? __('Edit package') : __('New package') }}</flux:heading>
            @if ($editingPackageId)
                <flux:button type="button" wire:click="newPackage" variant="ghost">{{ __('Cancel editing') }}</flux:button>
            @endif
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="stripe_price_id" :label="__('Stripe Price ID')" placeholder="price_..." required />
            <flux:input wire:model="sort_order" type="number" min="0" :label="__('Display order')" required />
            <flux:input wire:model="price" type="number" step="0.01" min="0" :label="__('Displayed price')" required />
            <flux:input wire:model="currency" maxlength="3" :label="__('Currency')" required />
            <flux:select wire:model="billing_interval" :label="__('Billing interval')">
                @foreach (SubscriptionBillingInterval::cases() as $interval)
                    <option value="{{ $interval->value }}">{{ $interval->label() }}</option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="description" :label="__('Description')" class="sm:col-span-2" />
            <flux:switch wire:model="is_active" :label="__('Available for new subscriptions')" />
        </div>

        <div>
            <flux:heading size="lg">{{ __('Package limits') }}</flux:heading>
            <flux:text>{{ __('Leave empty for unlimited. Set zero to prevent new records.') }}</flux:text>
            <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (OrganizationLimit::cases() as $limit)
                    <flux:input wire:model="{{ $limit->value }}" type="number" min="0" :label="$limit->label()" placeholder="Unlimited" />
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">{{ __('Save package') }}</flux:button>
        </div>
    </form>

    <section class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->packages as $package)
            <article class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ $package->name }}</flux:heading>
                        <flux:text>{{ $package->currency }} {{ number_format((float) $package->price, 2) }} / {{ $package->billing_interval->label() }}</flux:text>
                    </div>
                    <flux:badge :color="$package->is_active ? 'green' : 'zinc'">{{ $package->is_active ? __('Active') : __('Unavailable') }}</flux:badge>
                </div>
                <flux:text>{{ $package->description }}</flux:text>
                <flux:text>{{ trans_choice(':count organization|:count organizations', $package->organizations_count, ['count' => $package->organizations_count]) }}</flux:text>
                <flux:button type="button" wire:click="edit({{ $package->id }})" class="mt-auto">{{ __('Edit') }}</flux:button>
            </article>
        @empty
            <flux:callout icon="information-circle" heading="{{ __('No packages have been created yet.') }}" />
        @endforelse
    </section>
</div>
