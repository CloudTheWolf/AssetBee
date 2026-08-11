<?php

use App\Enums\OrganizationLimit;
use App\Models\Organization;
use App\Models\SubscriptionPackage;
use App\Support\CurrentOrganization;
use App\Support\OrganizationSubscriptionLimits;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Laravel\Cashier\Subscription;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Billing')] class extends Component
{
    use AuthorizesRequests;

    public Organization $organization;

    public function mount(): void
    {
        $organization = CurrentOrganization::require();
        $this->authorize('manageBilling', $organization);

        $this->organization = $organization->load(['package', 'plan']);
    }

    /** @return Collection<int, SubscriptionPackage> */
    #[Computed]
    public function packages(): Collection
    {
        return SubscriptionPackage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function stripeSubscription(): ?Subscription
    {
        return $this->organization->subscription('default');
    }

    public function usageFor(OrganizationLimit $limit): int
    {
        return app(OrganizationSubscriptionLimits::class)->usage($this->organization, $limit);
    }
}; ?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Billing') }}</flux:heading>
        <flux:text>{{ __('Choose and manage the subscription for :organization.', ['organization' => $organization->name]) }}</flux:text>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('status') }}" />
    @endif

    @error('subscription')
        <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $message }}" />
    @enderror

    @php
        $stripeSubscription = $this->stripeSubscription();
        $currentPackage = $organization->package;
        $legacyPlan = $organization->plan;
        $limitSource = $currentPackage ?? $legacyPlan;
    @endphp

    @if ($organization->subscribed('default'))
        <section class="grid gap-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700 sm:grid-cols-2">
            <div>
                <flux:text class="text-sm">{{ __('Current package') }}</flux:text>
                <flux:heading size="lg">{{ $currentPackage?->name ?? __('Confirming subscription') }}</flux:heading>
                @if ($currentPackage)
                    <flux:text>{{ $currentPackage->currency }} {{ number_format((float) $currentPackage->price, 2) }} / {{ $currentPackage->billing_interval->label() }}</flux:text>
                @else
                    <flux:text>{{ __('Stripe has accepted the subscription. Package details will appear after webhook confirmation.') }}</flux:text>
                @endif
            </div>
            <div>
                <flux:text class="text-sm">{{ __('Billing status') }}</flux:text>
                <flux:heading size="lg">{{ ucfirst(str_replace('_', ' ', $stripeSubscription?->stripe_status ?? 'pending')) }}</flux:heading>
                @if ($stripeSubscription?->ends_at)
                    <flux:text>{{ __('Ends :date', ['date' => $stripeSubscription->ends_at->toFormattedDateString()]) }}</flux:text>
                @endif
            </div>
            <form method="POST" action="{{ route('organizations.billing.portal') }}" class="sm:col-span-2">
                @csrf
                <flux:button type="submit" variant="primary" icon="credit-card">{{ __('Manage billing') }}</flux:button>
            </form>
        </section>
    @else
        <section>
            <flux:heading size="lg">{{ __('Available packages') }}</flux:heading>
            <flux:text>{{ __('Select a package to continue securely in Stripe Checkout.') }}</flux:text>

            <div class="mt-5 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @forelse ($this->packages as $package)
                    <article class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                        <div>
                            <flux:heading size="lg">{{ $package->name }}</flux:heading>
                            <flux:text>{{ $package->description }}</flux:text>
                        </div>
                        <div>
                            <span class="text-2xl font-semibold">{{ $package->currency }} {{ number_format((float) $package->price, 2) }}</span>
                            <flux:text>/ {{ $package->billing_interval->label() }}</flux:text>
                        </div>
                        <div class="space-y-1 text-sm">
                            @foreach (OrganizationLimit::cases() as $limit)
                                @php($maximum = $package->getAttribute($limit->value))
                                <div>{{ $limit->label() }}: {{ $maximum === null ? __('Unlimited') : $maximum }}</div>
                            @endforeach
                        </div>
                        <form method="POST" action="{{ route('organizations.billing.checkout', $package) }}" class="mt-auto">
                            @csrf
                            <flux:button type="submit" variant="primary" class="w-full">{{ __('Choose :package', ['package' => $package->name]) }}</flux:button>
                        </form>
                    </article>
                @empty
                    <flux:callout icon="information-circle" heading="{{ __('No subscription packages are currently available.') }}" />
                @endforelse
            </div>
        </section>
    @endif

    @if ($limitSource)
        <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Current limits') }}</flux:heading>
            @if ($currentPackage === null && $legacyPlan)
                <flux:text>{{ __('Your existing custom plan remains active until you subscribe to a catalogue package.') }}</flux:text>
            @endif
            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (OrganizationLimit::cases() as $limit)
                    @php($configuredLimit = $limitSource->getAttribute($limit->value))
                    <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-900">
                        <flux:text class="font-medium">{{ $limit->label() }}</flux:text>
                        <flux:text>{{ $this->usageFor($limit) }} / {{ $configuredLimit === null ? __('Unlimited') : $configuredLimit }}</flux:text>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
