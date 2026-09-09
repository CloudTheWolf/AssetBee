<?php

namespace App\Actions\Assets;

use App\Enums\CloudTenantCostSyncProvider;
use App\Models\CloudTenant;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorContract;

class UpdateCloudTenantCostSyncSettings
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(CloudTenant $cloudTenant, array $input): CloudTenant
    {
        $validator = Validator::make($input, [
            'cost_sync_provider' => ['required', Rule::enum(CloudTenantCostSyncProvider::class)],
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
            'bearer_token' => ['nullable', 'string', 'max:4096'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'header_name' => ['nullable', 'string', 'max:255'],
            'header_value' => ['nullable', 'string', 'max:4096'],
        ]);

        $provider = CloudTenantCostSyncProvider::from((string) $input['cost_sync_provider']);
        $this->assertProviderRequirements($validator, $cloudTenant, $provider, $input);
        $validated = $validator->validate();

        if ($provider === CloudTenantCostSyncProvider::None) {
            $cloudTenant->update([
                'cost_sync_provider' => CloudTenantCostSyncProvider::None,
                'cost_sync_request' => null,
                'cost_sync_error' => null,
            ]);

            return $cloudTenant->refresh();
        }

        $request = null;
        if ($provider === CloudTenantCostSyncProvider::CustomHttp) {
            $request = $validated['cost_sync_request'] ?? [];
            $request['auth_credentials'] = $this->mergeCustomCredentials($cloudTenant, $validated);
        }

        $cloudTenant->update([
            'cost_sync_provider' => $provider,
            'cost_sync_request' => $request,
            'cost_sync_error' => null,
        ]);

        $cloudTenant->organization?->update(['cost_sync_enabled' => true]);

        return $cloudTenant->refresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function assertProviderRequirements(
        ValidatorContract $validator,
        CloudTenant $cloudTenant,
        CloudTenantCostSyncProvider $provider,
        array $input,
    ): void {
        $validator->after(function (ValidatorContract $validator) use ($cloudTenant, $provider, $input): void {
            if ($provider === CloudTenantCostSyncProvider::Native) {
                if (! $cloudTenant->provider->supportsCostSync()) {
                    $validator->errors()->add('cost_sync_provider', __('Native cost sync is not available for this cloud provider.'));
                }
                if (! $cloudTenant->hasCredentials()) {
                    $validator->errors()->add('cost_sync_provider', __('Save provider credentials before enabling native cost sync.'));
                }
            }

            if ($provider === CloudTenantCostSyncProvider::CustomHttp) {
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
     * @return array<string, mixed>
     */
    protected function mergeCustomCredentials(CloudTenant $cloudTenant, array $validated): array
    {
        $existing = ($cloudTenant->cost_sync_request['auth_credentials'] ?? []) ?: [];

        return [
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
        ];
    }
}
