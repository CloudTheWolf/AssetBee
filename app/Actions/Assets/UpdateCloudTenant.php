<?php

namespace App\Actions\Assets;

use App\Enums\CloudTenantProvider;
use App\Enums\CloudTenantStatus;
use App\Models\CloudTenant;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateCloudTenant
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(CloudTenant $cloudTenant, array $input): CloudTenant
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', Rule::enum(CloudTenantProvider::class)],
            'external_id' => ['nullable', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(CloudTenantStatus::class)],
            'notes' => ['nullable', 'string'],
        ])->validate();

        $cloudTenant->update($validated);

        if (! $cloudTenant->provider->supportsCredentials() && $cloudTenant->hasCredentials()) {
            $cloudTenant->update([
                'credentials' => null,
                'credentials_verified_at' => null,
            ]);
        }

        return $cloudTenant->refresh();
    }
}
