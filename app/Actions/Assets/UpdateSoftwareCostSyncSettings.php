<?php

namespace App\Actions\Assets;

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
}
