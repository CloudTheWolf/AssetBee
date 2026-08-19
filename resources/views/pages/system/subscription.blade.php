<?php

use App\Actions\Subscriptions\UpdateOrganizationSubscription;
use App\Enums\OrganizationLimit;
use App\Enums\SubscriptionBillingInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Support\OrganizationSubscriptionLimits;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Customer subscription')] class extends Component
{
    use AuthorizesRequests;

    public Organization $organization;

    public string $plan_name = 'Custom';

    public string $status = 'active';

    public string $price = '0.00';

    public string $currency = 'GBP';

    public string $billing_interval = 'monthly';

    public ?string $stripe_price_id = null;

    public ?string $renews_at = null;

    public ?int $member_limit = null;

    public ?int $userware_limit = null;

    public ?int $hardware_limit = null;

    public ?int $virtualware_limit = null;

    public ?int $software_limit = null;

    public ?int $cloud_tenant_limit = null;

    public ?int $asset_document_limit = null;

    public ?int $userware_account_limit = null;

    public ?int $api_key_limit = null;

    public function mount(Organization $organization): void
    {
        $this->authorize('manageSubscription', $organization);
        $this->organization = $organization;

        $subscription = $organization->plan;

        if ($subscription === null) {
            return;
        }

        $this->plan_name = $subscription->plan_name;
        $this->status = $subscription->status->value;
        $this->price = $subscription->price;
        $this->currency = $subscription->currency;
        $this->billing_interval = $subscription->billing_interval->value;
        $this->stripe_price_id = $subscription->stripe_price_id;
        $this->renews_at = $subscription->renews_at?->format('Y-m-d');

        foreach (OrganizationLimit::cases() as $limit) {
            $this->{$limit->value} = $subscription->getAttribute($limit->value);
        }
    }

    public function save(UpdateOrganizationSubscription $updateSubscription): void
    {
        $this->authorize('manageSubscription', $this->organization);

        $input = [
            'plan_name' => $this->plan_name,
            'status' => $this->status,
            'price' => $this->price,
            'currency' => $this->currency,
            'billing_interval' => $this->billing_interval,
            'stripe_price_id' => $this->stripe_price_id,
            'renews_at' => $this->renews_at,
        ];

        foreach (OrganizationLimit::cases() as $limit) {
            $input[$limit->value] = $this->{$limit->value};
        }

        $updateSubscription->handle($this->organization, $input);
        $this->organization->unsetRelation('plan');

        Flux::toast(variant: 'success', text: __('Subscription updated.'));
    }

    public function usageFor(OrganizationLimit $limit): int
    {
        return app(OrganizationSubscriptionLimits::class)->usage($this->organization, $limit);
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-col gap-8">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Subscription') }}</flux:heading>
            <flux:text>{{ __('Manage pricing and usage limits for :customer.', ['customer' => $organization->name]) }}</flux:text>
        </div>
        <flux:button :href="route('system.customers')">{{ __('Back to customers') }}</flux:button>
    </div>

    <form wire:submit="save" class="flex flex-col gap-8">
        <section class="grid gap-5 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <flux:heading size="lg">{{ __('Plan and pricing') }}</flux:heading>
                <flux:text>{{ __('Set the Stripe Price ID customers will use at checkout. Pricing shown here should match Stripe.') }}</flux:text>
            </div>
            <flux:input wire:model="plan_name" :label="__('Plan name')" required />
            <flux:select wire:model="status" :label="__('Status')">
                @foreach (SubscriptionStatus::cases() as $subscriptionStatus)
                    <option value="{{ $subscriptionStatus->value }}">{{ $subscriptionStatus->label() }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="price" type="number" step="0.01" min="0" :label="__('Price')" required />
            <flux:input wire:model="currency" maxlength="3" :label="__('Currency')" required />
            <flux:select wire:model="billing_interval" :label="__('Billing interval')">
                @foreach (SubscriptionBillingInterval::cases() as $interval)
                    <option value="{{ $interval->value }}">{{ $interval->label() }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="stripe_price_id" :label="__('Stripe Price ID')" placeholder="price_..." />
            <flux:input wire:model="renews_at" type="date" :label="__('Renews on')" />
        </section>

        <section class="grid gap-5 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700 sm:grid-cols-2 lg:grid-cols-3">
            <div class="sm:col-span-2 lg:col-span-3">
                <flux:heading size="lg">{{ __('Usage limits') }}</flux:heading>
                <flux:text>{{ __('Leave a limit empty for unlimited. Set it to zero to prevent new records.') }}</flux:text>
            </div>
            @foreach (OrganizationLimit::cases() as $limit)
                <flux:input
                    wire:model="{{ $limit->value }}"
                    type="number"
                    min="0"
                    :label="$limit->label()"
                    :description="__('Currently used: :count', ['count' => $this->usageFor($limit)])"
                    placeholder="Unlimited"
                />
            @endforeach
        </section>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">{{ __('Save subscription') }}</flux:button>
        </div>
    </form>
</div>
