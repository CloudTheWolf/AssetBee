<?php

namespace App\Actions\Assets;

use App\Enums\AtlassianAddonSeatSource;
use App\Enums\AtlassianCostProduct;
use App\Enums\SoftwareCostSyncProvider;
use App\Models\Software;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorContract;

class UpdateSoftwareCostSyncSettings
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Software $software, array $input): Software
    {
        $validator = Validator::make($input, [
            'cost_sync_provider' => ['required', Rule::enum(SoftwareCostSyncProvider::class)],
            'organization_id' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'api_token' => ['nullable', 'string', 'max:2048'],
            'customer_id' => ['nullable', 'string', 'max:255'],
            'service_account_email' => ['nullable', 'string', 'max:255'],
            'service_account_json' => ['nullable', 'string'],
            'admin_email' => ['nullable', 'email', 'max:255'],
            'team_id' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:2048'],
            'bearer_token' => ['nullable', 'string', 'max:4096'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'header_name' => ['nullable', 'string', 'max:255'],
            'header_value' => ['nullable', 'string', 'max:4096'],
            'cost_sync_request' => ['nullable', 'array'],
            'cost_sync_request.method' => ['nullable', 'string', Rule::in(['GET', 'POST', 'PUT', 'PATCH'])],
            'cost_sync_request.url' => ['nullable', 'url', 'max:2048'],
            'cost_sync_request.headers' => ['nullable', 'array'],
            'cost_sync_request.headers.*.name' => ['nullable', 'string', 'max:255'],
            'cost_sync_request.headers.*.value' => ['nullable', 'string', 'max:2048'],
            'cost_sync_request.auth' => ['nullable', 'string', Rule::in(['none', 'bearer', 'basic', 'header'])],
            'cost_sync_request.body' => ['nullable', 'string', 'max:10000'],
            'cost_sync_request.response_amount_path' => ['nullable', 'string', 'max:255'],
            'cost_sync_request.response_currency_path' => ['nullable', 'string', 'max:255'],
            'cost_sync_request.response_seats_path' => ['nullable', 'string', 'max:255'],
            'cost_sync_request.amount_period' => ['nullable', 'string', Rule::in(['month', 'as_reported'])],
            'products' => ['nullable', 'array'],
            'products.*.slug' => ['nullable', 'string', 'max:100'],
            'products.*.label' => ['nullable', 'string', 'max:255'],
            'products.*.price_per_seat' => ['nullable', 'numeric', 'min:0'],
            'products.*.keys' => ['nullable', 'string', 'max:500'],
            'products.*.name_contains' => ['nullable', 'string', 'max:255'],
            'products.*.seat_source' => ['nullable', 'string', Rule::enum(AtlassianAddonSeatSource::class)],
            'products.*.manual_seats' => ['nullable', 'integer', 'min:0'],
            'products.*.custom' => ['nullable', 'boolean'],
        ]);

        $provider = SoftwareCostSyncProvider::from((string) $input['cost_sync_provider']);
        $this->assertProviderRequirements($validator, $software, $provider, $input);
        $validated = $validator->validate();

        if ($provider === SoftwareCostSyncProvider::None) {
            $software->update([
                'cost_sync_provider' => SoftwareCostSyncProvider::None,
                'cost_sync_credentials' => null,
                'cost_sync_request' => null,
                'cost_sync_error' => null,
            ]);

            return $software->refresh();
        }

        $software->update([
            'cost_sync_provider' => $provider,
            'cost_sync_credentials' => $this->mergeCredentials($software, $provider, $validated),
            'cost_sync_request' => $provider === SoftwareCostSyncProvider::CustomHttp
                ? ($validated['cost_sync_request'] ?? null)
                : null,
            'cost_sync_error' => null,
        ]);

        $software->organization?->update(['cost_sync_enabled' => true]);

        return $software->refresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function assertProviderRequirements(
        ValidatorContract $validator,
        Software $software,
        SoftwareCostSyncProvider $provider,
        array $input,
    ): void {
        $validator->after(function (ValidatorContract $validator) use ($software, $provider, $input): void {
            if ($provider === SoftwareCostSyncProvider::None) {
                return;
            }

            if ($provider === SoftwareCostSyncProvider::Atlassian) {
                if (blank($input['organization_id'] ?? null)) {
                    $validator->errors()->add('organization_id', __('The Atlassian organization ID is required.'));
                }
                if (! $software->hasCostSyncCredentials() && blank($input['api_token'] ?? null)) {
                    $validator->errors()->add('api_token', __('The organization API key is required when saving credentials for the first time.'));
                }

                $hasPricedProduct = false;
                foreach ($input['products'] ?? [] as $index => $product) {
                    if (! is_array($product)) {
                        continue;
                    }

                    if (is_numeric($product['price_per_seat'] ?? null) && (float) $product['price_per_seat'] > 0) {
                        $hasPricedProduct = true;
                    }

                    $isCustom = filter_var($product['custom'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    if (! $isCustom) {
                        continue;
                    }

                    if (blank($product['label'] ?? null)) {
                        $validator->errors()->add("products.$index.label", __('Add-on name is required.'));
                    }

                    $seatSource = AtlassianAddonSeatSource::tryFrom((string) ($product['seat_source'] ?? ''));
                    if ($seatSource === null) {
                        $validator->errors()->add("products.$index.seat_source", __('Choose where add-on seats come from.'));
                    } elseif ($seatSource === AtlassianAddonSeatSource::Manual && ! is_numeric($product['manual_seats'] ?? null)) {
                        $validator->errors()->add("products.$index.manual_seats", __('Enter a manual seat count for this add-on.'));
                    }
                }

                if (! $hasPricedProduct) {
                    $validator->errors()->add('products', __('Set a price per seat for at least one Atlassian product or add-on.'));
                }
            }

            if ($provider === SoftwareCostSyncProvider::GoogleWorkspace) {
                foreach (['customer_id', 'service_account_email', 'admin_email'] as $field) {
                    if (blank($input[$field] ?? null)) {
                        $validator->errors()->add($field, __('This field is required.'));
                    }
                }
                if (! $software->hasCostSyncCredentials() && blank($input['service_account_json'] ?? null)) {
                    $validator->errors()->add('service_account_json', __('The service account JSON is required when saving credentials for the first time.'));
                }
            }

            if ($provider === SoftwareCostSyncProvider::Cursor) {
                if (! $software->hasCostSyncCredentials() && blank($input['api_key'] ?? null)) {
                    $validator->errors()->add('api_key', __('The API key is required when saving credentials for the first time.'));
                }
            }

            if ($provider === SoftwareCostSyncProvider::CustomHttp) {
                $request = $input['cost_sync_request'] ?? [];
                if (blank($request['url'] ?? null)) {
                    $validator->errors()->add('cost_sync_request.url', __('A request URL is required.'));
                }
                if (blank($request['response_amount_path'] ?? null)) {
                    $validator->errors()->add('cost_sync_request.response_amount_path', __('An amount JSON path is required.'));
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>|null
     */
    protected function mergeCredentials(Software $software, SoftwareCostSyncProvider $provider, array $validated): ?array
    {
        $existing = $software->cost_sync_credentials ?? [];

        return match ($provider) {
            SoftwareCostSyncProvider::Atlassian => [
                'organization_id' => $validated['organization_id'],
                'api_token' => filled($validated['api_token'] ?? null)
                    ? $validated['api_token']
                    : ($existing['api_token'] ?? null),
                ...$this->mergeAtlassianProducts($existing, $validated['products'] ?? []),
            ],
            SoftwareCostSyncProvider::GoogleWorkspace => [
                'customer_id' => $validated['customer_id'],
                'service_account_email' => $validated['service_account_email'],
                'service_account_json' => filled($validated['service_account_json'] ?? null)
                    ? $validated['service_account_json']
                    : ($existing['service_account_json'] ?? null),
                'admin_email' => $validated['admin_email'],
            ],
            SoftwareCostSyncProvider::Cursor => [
                'team_id' => filled($validated['team_id'] ?? null)
                    ? $validated['team_id']
                    : ($existing['team_id'] ?? null),
                'api_key' => filled($validated['api_key'] ?? null)
                    ? $validated['api_key']
                    : ($existing['api_key'] ?? null),
            ],
            SoftwareCostSyncProvider::CustomHttp => [
                'bearer_token' => filled($validated['bearer_token'] ?? null)
                    ? $validated['bearer_token']
                    : ($existing['bearer_token'] ?? null),
                'username' => $validated['username'] ?? ($existing['username'] ?? null),
                'password' => filled($validated['password'] ?? null)
                    ? $validated['password']
                    : ($existing['password'] ?? null),
                'header_name' => $validated['header_name'] ?? ($existing['header_name'] ?? null),
                'header_value' => filled($validated['header_value'] ?? null)
                    ? $validated['header_value']
                    : ($existing['header_value'] ?? null),
            ],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $existing
     * @return array{products: array<string, array<string, mixed>>, addons: list<array<string, mixed>>}
     */
    protected function mergeAtlassianProducts(array $existing, mixed $inputProducts): array
    {
        $existingProducts = is_array($existing['products'] ?? null) ? $existing['products'] : [];
        $existingAddonsBySlug = [];

        foreach (AtlassianCostProduct::addonConfigsFromCredentials($existing) as $addon) {
            $existingAddonsBySlug[$addon['slug']] = $addon;
        }

        $inputRows = is_array($inputProducts) ? $inputProducts : [];
        $mergedProducts = [];
        $mergedAddons = [];
        $usedAddonSlugs = [];

        foreach ($inputRows as $product) {
            if (! is_array($product)) {
                continue;
            }

            $isCustom = filter_var($product['custom'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($isCustom) {
                $label = trim((string) ($product['label'] ?? ''));
                if ($label === '') {
                    continue;
                }

                $slug = trim((string) ($product['slug'] ?? ''));
                if ($slug === '' || isset($usedAddonSlugs[$slug]) || AtlassianCostProduct::tryFrom($slug) !== null) {
                    $slug = AtlassianCostProduct::slugForAddonLabel($label);
                    $base = $slug;
                    $suffix = 2;
                    while (isset($usedAddonSlugs[$slug])) {
                        $slug = $base.'_'.$suffix;
                        $suffix++;
                    }
                }

                $previous = $existingAddonsBySlug[$slug] ?? [];
                $keys = AtlassianCostProduct::normalizeKeys($product['keys'] ?? ($previous['keys'] ?? []));

                $seatSource = AtlassianAddonSeatSource::tryFrom((string) ($product['seat_source'] ?? ''))
                    ?? AtlassianAddonSeatSource::tryFrom((string) ($previous['seat_source'] ?? ''))
                    ?? AtlassianAddonSeatSource::Jira;

                $manualSeats = null;
                if ($seatSource === AtlassianAddonSeatSource::Manual) {
                    $manualSeats = is_numeric($product['manual_seats'] ?? null)
                        ? max(0, (int) $product['manual_seats'])
                        : (is_numeric($previous['manual_seats'] ?? null) ? max(0, (int) $previous['manual_seats']) : 0);
                }

                $price = array_key_exists('price_per_seat', $product) && blank($product['price_per_seat'])
                    ? 0.0
                    : (is_numeric($product['price_per_seat'] ?? null)
                        ? (float) $product['price_per_seat']
                        : (float) ($previous['price_per_seat'] ?? 0));

                $mergedAddons[] = [
                    'slug' => $slug,
                    'label' => $label,
                    'price_per_seat' => $price,
                    'keys' => $keys,
                    'seat_source' => $seatSource->value,
                    'manual_seats' => $manualSeats,
                    'child_software_id' => $previous['child_software_id'] ?? null,
                ];
                $usedAddonSlugs[$slug] = true;

                continue;
            }

            $slug = (string) ($product['slug'] ?? '');
            $enum = AtlassianCostProduct::tryFrom($slug);
            if ($enum === null) {
                continue;
            }

            $previous = is_array($existingProducts[$slug] ?? null) ? $existingProducts[$slug] : [];
            $resolved = $enum->resolvedConfig(array_merge($previous, $product));

            if (array_key_exists('price_per_seat', $product) && blank($product['price_per_seat'])) {
                $resolved['price_per_seat'] = 0.0;
            }

            if (array_key_exists('keys', $product) && filled($product['keys'])) {
                $resolved['keys'] = AtlassianCostProduct::normalizeKeys($product['keys']);
            }

            $mergedProducts[$slug] = [
                'price_per_seat' => $resolved['price_per_seat'],
                'keys' => $resolved['keys'],
                'child_software_id' => $resolved['child_software_id'],
            ];
        }

        foreach (AtlassianCostProduct::cases() as $product) {
            if (isset($mergedProducts[$product->value])) {
                continue;
            }

            $previous = is_array($existingProducts[$product->value] ?? null) ? $existingProducts[$product->value] : [];
            $resolved = $product->resolvedConfig($previous);
            $mergedProducts[$product->value] = [
                'price_per_seat' => $resolved['price_per_seat'],
                'keys' => $resolved['keys'],
                'child_software_id' => $resolved['child_software_id'],
            ];
        }

        return [
            'products' => $mergedProducts,
            'addons' => $mergedAddons,
        ];
    }
}
