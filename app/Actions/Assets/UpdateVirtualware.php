<?php

namespace App\Actions\Assets;

use App\Enums\VirtualwareCategory;
use App\Enums\VirtualwareProvider;
use App\Enums\VirtualwareStatus;
use App\Models\Virtualware;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateVirtualware
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Virtualware $virtualware, array $input): Virtualware
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
                Rule::exists('hardwares', 'id')->where('organization_id', $virtualware->organization_id),
            ],
            'assigned_userware_id' => [
                'nullable',
                'integer',
                Rule::exists('userwares', 'id')->where('organization_id', $virtualware->organization_id),
            ],
            'notes' => ['nullable', 'string'],
        ])->validate();

        $virtualware->update($validated);

        return $virtualware->refresh();
    }
}
