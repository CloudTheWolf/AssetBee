<?php

namespace App\Actions\Assets\Concerns;

use App\Enums\BitLockerStatus;
use App\Enums\HardwareCategory;
use App\Enums\HardwareOperatingSystem;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait NormalizesHardwareAttributes
{
    /**
     * @return array<string, mixed>
     */
    protected function hardwareAttributeRules(): array
    {
        return [
            'operating_system' => ['nullable', Rule::enum(HardwareOperatingSystem::class)],
            'cpu' => ['nullable', 'string', 'max:255'],
            'ram_gb' => ['nullable', 'integer', 'min:1', 'max:1048576'],
            'storage_gb' => ['nullable', 'integer', 'min:1', 'max:1048576'],
            'bitlocker_status' => ['nullable', Rule::enum(BitLockerStatus::class)],
            'bitlocker_recovery_key' => ['nullable', 'string', 'max:5000'],
            'is_vm_host' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function normalizeHardwareAttributes(array $validated): array
    {
        $category = $validated['category'] instanceof HardwareCategory
            ? $validated['category']
            : HardwareCategory::from($validated['category']);

        if (! $category->hasComputeSpecs()) {
            $validated['operating_system'] = null;
            $validated['cpu'] = null;
            $validated['ram_gb'] = null;
            $validated['storage_gb'] = null;
            $validated['bitlocker_status'] = null;
            $validated['bitlocker_recovery_key'] = null;
            $validated['is_vm_host'] = false;

            return $validated;
        }

        $operatingSystem = $validated['operating_system'] ?? null;

        if ($operatingSystem !== null && ! $operatingSystem instanceof HardwareOperatingSystem) {
            $operatingSystem = HardwareOperatingSystem::from($operatingSystem);
        }

        if ($operatingSystem === null || ! $operatingSystem->isWindows()) {
            $validated['bitlocker_status'] = null;
            $validated['bitlocker_recovery_key'] = null;
        }

        $validated['is_vm_host'] = $category->canBeVmHost()
            ? (bool) ($validated['is_vm_host'] ?? false)
            : false;

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function afterHardwareValidation(Validator $validator, array $input): void
    {
        $validator->after(function (Validator $validator) use ($input): void {
            $categoryValue = $input['category'] ?? null;

            if ($categoryValue === null) {
                return;
            }

            $category = $categoryValue instanceof HardwareCategory
                ? $categoryValue
                : HardwareCategory::tryFrom((string) $categoryValue);

            if ($category === null) {
                return;
            }

            if (($input['is_vm_host'] ?? false) && ! $category->canBeVmHost()) {
                $validator->errors()->add('is_vm_host', __('Only servers can be marked as a VM host.'));
            }
        });
    }
}
