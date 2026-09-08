<?php

namespace App\Services\Costs;

use App\Contracts\Costs\FetchesAssetCosts;
use App\Data\FetchedCostPeriod;
use App\Enums\CloudTenantCostSyncProvider;
use App\Enums\CloudTenantProvider;
use App\Enums\CostSyncSource;
use App\Enums\SoftwareCostSyncProvider;
use App\Models\CloudTenant;
use App\Models\Software;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleWorkspaceCostFetcher implements FetchesAssetCosts
{
    public function supports(Software|CloudTenant $asset): bool
    {
        if ($asset instanceof Software) {
            return $asset->cost_sync_provider === SoftwareCostSyncProvider::GoogleWorkspace
                && $asset->hasCostSyncCredentials();
        }

        return $asset->cost_sync_provider === CloudTenantCostSyncProvider::Native
            && $asset->provider === CloudTenantProvider::GoogleWorkspace
            && $asset->hasCredentials();
    }

    public function fetch(Software|CloudTenant $asset, CarbonInterface $from, CarbonInterface $to): array
    {
        $credentials = $asset instanceof Software
            ? ($asset->cost_sync_credentials ?? [])
            : ($asset->credentials ?? []);

        $customerId = (string) ($credentials['customer_id'] ?? '');
        $accessToken = $this->accessToken($credentials);

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(30)
            ->get('https://www.googleapis.com/admin/directory/v1/customer/'.$customerId.'/subscriptions');

        if (! $response->successful()) {
            throw new RuntimeException(__('Google Workspace Admin API request failed with status :status.', [
                'status' => $response->status(),
            ]));
        }

        $items = $response->json('items') ?? $response->json('subscriptions') ?? [];
        $seatCount = 0;
        $amount = 0.0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $seatCount += (int) data_get($item, 'seats.numberOfSeats', data_get($item, 'seats.licensedNumberOfSeats', 0));
            $amount += (float) data_get($item, 'plan.amount', 0);
        }

        if ($amount <= 0 && is_numeric($asset->billing_amount)) {
            $amount = (float) $asset->billing_amount;
        }

        $currency = strtoupper($asset->currency ?: 'USD');

        $periodStart = CarbonImmutable::parse($to)->startOfMonth()->startOfDay();
        $periodEnd = CarbonImmutable::parse($to)->endOfMonth()->startOfDay();
        if ($periodEnd->greaterThan(now())) {
            $periodEnd = now()->startOfDay();
        }

        return [
            new FetchedCostPeriod(
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                amount: round($amount, 2),
                currency: $currency,
                provider: CostSyncSource::GoogleWorkspace,
                seatCount: $seatCount > 0 ? $seatCount : null,
                meta: ['source' => 'google_workspace_subscriptions'],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function accessToken(array $credentials): string
    {
        $serviceAccountJson = (string) ($credentials['service_account_json'] ?? '');
        $adminEmail = (string) ($credentials['admin_email'] ?? '');

        $decoded = json_decode($serviceAccountJson, true);
        if (! is_array($decoded) || blank($decoded['private_key'] ?? null) || blank($decoded['client_email'] ?? null)) {
            throw new RuntimeException(__('Google Workspace service account JSON is invalid.'));
        }

        $assertion = $this->buildAssertion(
            (string) $decoded['client_email'],
            (string) $decoded['private_key'],
            $adminEmail,
        );

        $response = Http::asForm()
            ->timeout(30)
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

        if (! $response->successful() || blank($response->json('access_token'))) {
            throw new RuntimeException(__('Unable to authenticate with Google Workspace for cost sync.'));
        }

        return (string) $response->json('access_token');
    }

    protected function buildAssertion(string $clientEmail, string $privateKey, string $adminEmail): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $now = time();
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'sub' => $adminEmail,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/apps.licensing https://www.googleapis.com/auth/admin.directory.user.readonly',
        ], JSON_THROW_ON_ERROR));

        $data = $header.'.'.$payload;
        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) {
            throw new RuntimeException(__('Unable to read Google Workspace private key.'));
        }

        $signature = '';
        if (! openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException(__('Unable to sign Google Workspace auth assertion.'));
        }

        return $data.'.'.$this->base64UrlEncode($signature);
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
