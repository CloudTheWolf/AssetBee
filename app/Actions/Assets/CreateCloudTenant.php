<?php

namespace App\Actions\Assets;

use App\Enums\CloudTenantProvider;
use App\Enums\CloudTenantStatus;
use App\Models\CloudTenant;
use App\Models\Organization;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateCloudTenant
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization, array $input): CloudTenant
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', Rule::enum(CloudTenantProvider::class)],
            'external_id' => ['nullable', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(CloudTenantStatus::class)],
            'notes' => ['nullable', 'string'],
        ])->validate();

        return $organization->cloudTenants()->create($validated);
    }
}
