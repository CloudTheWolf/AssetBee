<?php

namespace App\Listeners;

use App\Models\Organization;
use App\Models\SubscriptionPackage;
use Laravel\Cashier\Events\WebhookHandled;

class SyncOrganizationSubscriptionPackage
{
    public function handle(WebhookHandled $event): void
    {
        $type = $event->payload['type'] ?? null;

        if (! in_array($type, [
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
        ], true)) {
            return;
        }

        $stripeCustomerId = $event->payload['data']['object']['customer'] ?? null;

        if (! is_string($stripeCustomerId)) {
            return;
        }

        $organization = Organization::query()->where('stripe_id', $stripeCustomerId)->first();

        if ($organization === null) {
            return;
        }

        if ($type === 'customer.subscription.deleted') {
            $organization->forceFill(['subscription_package_id' => null])->save();

            return;
        }

        $stripePriceId = $event->payload['data']['object']['items']['data'][0]['price']['id'] ?? null;
        $packageId = is_string($stripePriceId)
            ? SubscriptionPackage::query()->where('stripe_price_id', $stripePriceId)->value('id')
            : null;

        $organization->forceFill(['subscription_package_id' => $packageId])->save();
    }
}
