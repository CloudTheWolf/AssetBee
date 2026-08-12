<?php

namespace App\Actions\Assets;

use App\Enums\VirtualwareCategory;
use App\Enums\VirtualwareProvider;
use App\Enums\VirtualwareStatus;
use App\Models\Hardware;
use App\Models\Virtualware;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorContract;

class UpdateVirtualware
{
    use Concerns\ValidatesVirtualwareInfrastructure;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Virtualware $virtualware, array $input): Virtualware
    {
        $validator = Validator::make($input, [
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
            'cloud_tenant_id' => [
                'nullable',
                'integer',
                Rule::exists('cloud_tenants', 'id')->where('organization_id', $virtualware->organization_id),
            ],
            'assigned_userware_id' => [
                'nullable',
                'integer',
                Rule::exists('userwares', 'id')->where('organization_id', $virtualware->organization_id),
            ],
            'notes' => ['nullable', 'string'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'inventory_collected_at' => ['nullable', 'date'],
            'inventory_payload' => ['nullable', 'array'],
            ...$this->infrastructureRules(),
        ]);

        $validator->after(function (ValidatorContract $validator) use ($input): void {
            if (! empty($input['host_hardware_id']) && ! empty($input['cloud_tenant_id'])) {
                $validator->errors()->add(
                    'host_hardware_id',
                    __('Virtualware can be linked to a cloud tenant or a VM host, not both.'),
                );
            }
        });

        $validated = $validator->validate();

        if (! empty($validated['host_hardware_id'])) {
            $host = Hardware::query()
                ->where('organization_id', $virtualware->organization_id)
                ->whereKey($validated['host_hardware_id'])
                ->first();

            if ($host === null || ! $host->is_vm_host || ! $host->category->canBeVmHost()) {
                throw ValidationException::withMessages([
                    'host_hardware_id' => __('The selected hardware must be a server marked as a VM host.'),
                ]);
            }

            $validated['cloud_tenant_id'] = null;
        }

        if (! empty($validated['cloud_tenant_id'])) {
            $validated['host_hardware_id'] = null;
        }

        $virtualware->update($validated);

        return $virtualware->refresh();
    }
}
