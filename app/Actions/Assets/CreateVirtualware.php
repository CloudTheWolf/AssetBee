<?php

namespace App\Actions\Assets;

use App\Enums\VirtualwareCategory;
use App\Enums\VirtualwareProvider;
use App\Enums\VirtualwareStatus;
use App\Models\Organization;
use App\Models\Virtualware;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateVirtualware
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization, array $input): Virtualware
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', Rule::enum(VirtualwareProvider::class)],
            'external_id' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::enum(VirtualwareCategory::class)],
            'status' => ['required', Rule::enum(VirtualwareStatus::class)],
            'host_hardware_id' => [
                'nullable',
                'integer',
                Rule::exists('hardwares', 'id')->where('organization_id', $organization->id),
            ],
            'assigned_userware_id' => [
                'nullable',
                'integer',
                Rule::exists('userwares', 'id')->where('organization_id', $organization->id),
            ],
            'notes' => ['nullable', 'string'],
        ])->validate();

        return $organization->virtualwares()->create($validated);
    }
}
