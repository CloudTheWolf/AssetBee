<?php

namespace App\Services\Costs;

use App\Contracts\Costs\FetchesAssetCosts;
use App\Data\FetchedCostPeriod;
use App\Enums\CloudTenantCostSyncProvider;
use App\Enums\CostSyncSource;
use App\Enums\CustomHttpAmountSource;
use App\Enums\SoftwareCostSyncProvider;
use App\Models\CloudTenant;
use App\Models\Software;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CustomHttpCostFetcher implements FetchesAssetCosts
{
    public function supports(Software|CloudTenant $asset): bool
    {
        if ($asset instanceof Software) {
            return $asset->cost_sync_provider === SoftwareCostSyncProvider::CustomHttp
                && filled($asset->cost_sync_request);
        }

        return $asset->cost_sync_provider === CloudTenantCostSyncProvider::CustomHttp
            && filled($asset->cost_sync_request);
    }

    public function fetch(Software|CloudTenant $asset, CarbonInterface $from, CarbonInterface $to): array
    {
        $request = $asset->cost_sync_request ?? [];
        $credentials = $asset instanceof Software
            ? ($asset->cost_sync_credentials ?? [])
            : (is_array($request['auth_credentials'] ?? null) ? $request['auth_credentials'] : []);

        $url = (string) ($request['url'] ?? '');
        $method = strtoupper((string) ($request['method'] ?? 'GET'));

        if ($url === '') {
            throw new RuntimeException(__('Custom HTTP cost sync requires a URL.'));
        }

        $pending = $this->configureAuth(Http::acceptJson()->timeout(30), $request, $credentials);

        /** @var array<string, string> $headers */
        $headers = [];
        foreach (($request['headers'] ?? []) as $header) {
            $name = trim((string) ($header['name'] ?? ''));
            $value = (string) ($header['value'] ?? '');
            if ($name !== '') {
                $headers[$name] = $value;
            }
        }

        if ($headers !== []) {
            $pending = $pending->withHeaders($headers);
        }

        $body = trim((string) ($request['body'] ?? ''));
        $response = $body !== '' && in_array($method, ['POST', 'PUT', 'PATCH'], true)
            ? $pending->send($method, $url, ['body' => $body])
            : $pending->send($method, $url);

        if (! $response->successful()) {
            throw new RuntimeException(__('Custom HTTP cost sync failed with status :status.', [
                'status' => $response->status(),
            ]));
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException(__('Custom HTTP cost sync response was not JSON.'));
        }

        $amountSource = CustomHttpAmountSource::tryFrom((string) ($request['amount_source'] ?? CustomHttpAmountSource::Response->value))
            ?? CustomHttpAmountSource::Response;

        $seatsPath = (string) ($request['response_seats_path'] ?? '');
        $seatCount = null;
        if ($seatsPath !== '') {
            $seats = Arr::get($payload, $seatsPath);
            $seatCount = is_numeric($seats) ? (int) $seats : null;
        }

        if ($amountSource === CustomHttpAmountSource::Seats) {
            [$amount, $currency, $meta] = $this->amountFromSeats($request, $seatCount);
        } else {
            [$amount, $currency, $meta] = $this->amountFromResponse($request, $payload, $seatCount);
        }

        $periodEnd = $to->copy()->startOfDay();
        $periodStart = $from->copy()->startOfMonth()->startOfDay();
        if (($request['amount_period'] ?? 'month') === 'month') {
            $periodStart = $to->copy()->startOfMonth()->startOfDay();
            $periodEnd = $to->copy()->endOfMonth()->startOfDay();
            if ($periodEnd->greaterThan(now())) {
                $periodEnd = now()->startOfDay();
            }
        }

        return [
            new FetchedCostPeriod(
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                amount: round((float) $amount, 2),
                currency: $currency,
                provider: CostSyncSource::CustomHttp,
                seatCount: $seatCount,
                meta: $meta,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $payload
     * @return array{0: float, 1: string, 2: array<string, mixed>}
     */
    protected function amountFromResponse(array $request, array $payload, ?int $seatCount): array
    {
        $amountPath = (string) ($request['response_amount_path'] ?? 'amount');
        $currencyPath = (string) ($request['response_currency_path'] ?? 'currency');

        $amount = Arr::get($payload, $amountPath);
        if (! is_numeric($amount)) {
            throw new RuntimeException(__('Custom HTTP cost sync could not resolve the amount path.'));
        }

        $currency = Arr::get($payload, $currencyPath);
        $currency = is_string($currency) && $currency !== '' ? strtoupper($currency) : 'USD';

        return [
            (float) $amount,
            $currency,
            [
                'source' => 'custom_http',
                'amount_source' => CustomHttpAmountSource::Response->value,
                'seat_count' => $seatCount,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array{0: float, 1: string, 2: array<string, mixed>}
     */
    protected function amountFromSeats(array $request, ?int $seatCount): array
    {
        if ($seatCount === null) {
            throw new RuntimeException(__('Custom HTTP cost sync could not resolve the seats path.'));
        }

        $includedSeats = max(0, (int) ($request['calculation_included_seats'] ?? 0));
        $pricePerSeat = (float) ($request['calculation_price_per_seat'] ?? 0);
        $billableSeats = max(0, $seatCount - $includedSeats);
        $amount = $billableSeats * $pricePerSeat;

        $currency = strtoupper(trim((string) ($request['calculation_currency'] ?? 'USD')));
        if ($currency === '') {
            $currency = 'USD';
        }

        return [
            $amount,
            $currency,
            [
                'source' => 'custom_http',
                'amount_source' => CustomHttpAmountSource::Seats->value,
                'seat_count' => $seatCount,
                'included_seats' => $includedSeats,
                'billable_seats' => $billableSeats,
                'price_per_seat' => $pricePerSeat,
                'formula' => sprintf('(seats - %d) * %s', $includedSeats, $pricePerSeat),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $credentials
     */
    protected function configureAuth(PendingRequest $pending, array $request, array $credentials): PendingRequest
    {
        return match ((string) ($request['auth'] ?? 'none')) {
            'bearer' => $pending->withToken((string) ($credentials['bearer_token'] ?? '')),
            'basic' => $pending->withBasicAuth(
                (string) ($credentials['username'] ?? ''),
                (string) ($credentials['password'] ?? ''),
            ),
            'header' => filled($credentials['header_name'] ?? null)
                ? $pending->withHeaders([
                    (string) $credentials['header_name'] => (string) ($credentials['header_value'] ?? ''),
                ])
                : $pending,
            default => $pending,
        };
    }
}
