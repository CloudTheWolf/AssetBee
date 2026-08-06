<?php

namespace App\Actions\Assets;

use App\Enums\CloudTenantProvider;
use App\Models\CloudTenant;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorContract;

class UpdateCloudTenantCredentials
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(CloudTenant $cloudTenant, array $input): CloudTenant
    {
        if (! $cloudTenant->provider->supportsCredentials()) {
            throw ValidationException::withMessages([
                'provider' => __('Credentials are not supported for this cloud provider.'),
            ]);
        }

        $validator = Validator::make($input, $this->rules($cloudTenant));
        $this->assertSecretProvidedWhenNeeded($validator, $cloudTenant, $input);
        $validated = $validator->validate();

        $credentials = $this->mergeCredentials($cloudTenant, $validated);

        $cloudTenant->update([
            'credentials' => $credentials,
            'credentials_verified_at' => null,
        ]);

        return $cloudTenant->refresh();
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(CloudTenant $cloudTenant): array
    {
        return match ($cloudTenant->provider) {
            CloudTenantProvider::Aws => [
                'access_key_id' => ['required', 'string', 'max:255'],
                'secret_access_key' => ['nullable', 'string', 'max:255'],
                'region' => ['required', 'string', 'max:64'],
                'session_token' => ['nullable', 'string', 'max:2048'],
            ],
            CloudTenantProvider::Azure => [
                'tenant_id' => ['required', 'string', 'max:255'],
                'client_id' => ['required', 'string', 'max:255'],
                'client_secret' => ['nullable', 'string', 'max:255'],
                'subscription_id' => ['required', 'string', 'max:255'],
            ],
            CloudTenantProvider::Gcp => [
                'project_id' => ['required', 'string', 'max:255'],
                'service_account_json' => [
                    Rule::requiredIf(! $cloudTenant->hasCredentials()),
                    'nullable',
                    'string',
                ],
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function assertSecretProvidedWhenNeeded(
        ValidatorContract $validator,
        CloudTenant $cloudTenant,
        array $input,
    ): void {
        $validator->after(function (ValidatorContract $validator) use ($cloudTenant, $input): void {
            if ($cloudTenant->hasCredentials()) {
                return;
            }

            $secretField = match ($cloudTenant->provider) {
                CloudTenantProvider::Aws => 'secret_access_key',
                CloudTenantProvider::Azure => 'client_secret',
                CloudTenantProvider::Gcp => 'service_account_json',
                default => null,
            };

            if ($secretField === null) {
                return;
            }

            if (blank($input[$secretField] ?? null)) {
                $validator->errors()->add($secretField, __('This field is required when saving credentials for the first time.'));
            }
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function mergeCredentials(CloudTenant $cloudTenant, array $validated): array
    {
        $existing = $cloudTenant->credentials ?? [];

        return match ($cloudTenant->provider) {
            CloudTenantProvider::Aws => [
                'access_key_id' => $validated['access_key_id'],
                'secret_access_key' => filled($validated['secret_access_key'] ?? null)
                    ? $validated['secret_access_key']
                    : ($existing['secret_access_key'] ?? null),
                'region' => $validated['region'],
                'session_token' => filled($validated['session_token'] ?? null)
                    ? $validated['session_token']
                    : ($existing['session_token'] ?? null),
            ],
            CloudTenantProvider::Azure => [
                'tenant_id' => $validated['tenant_id'],
                'client_id' => $validated['client_id'],
                'client_secret' => filled($validated['client_secret'] ?? null)
                    ? $validated['client_secret']
                    : ($existing['client_secret'] ?? null),
                'subscription_id' => $validated['subscription_id'],
            ],
            CloudTenantProvider::Gcp => [
                'project_id' => $validated['project_id'],
                'service_account_json' => filled($validated['service_account_json'] ?? null)
                    ? $validated['service_account_json']
                    : ($existing['service_account_json'] ?? null),
            ],
            default => [],
        };
    }
}
