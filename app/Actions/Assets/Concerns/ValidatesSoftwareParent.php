<?php

namespace App\Actions\Assets\Concerns;

use App\Models\Organization;
use App\Models\Software;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorContract;

trait ValidatesSoftwareParent
{
    /**
     * @return array<string, mixed>
     */
    protected function parentSoftwareRules(Organization $organization, ?Software $software = null): array
    {
        return [
            'parent_software_id' => [
                'nullable',
                'integer',
                Rule::exists('softwares', 'id')->where(function ($query) use ($organization, $software): void {
                    $query->where('organization_id', $organization->id)
                        ->whereNull('parent_software_id')
                        ->whereNull('deleted_at');

                    if ($software !== null) {
                        $query->where('id', '!=', $software->id);
                    }
                }),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function normalizeParentSoftwareId(array $validated): array
    {
        if (! array_key_exists('parent_software_id', $validated) || blank($validated['parent_software_id'])) {
            $validated['parent_software_id'] = null;
        } else {
            $validated['parent_software_id'] = (int) $validated['parent_software_id'];
        }

        return $validated;
    }

    protected function assertParentSoftwareAllowed(
        ValidatorContract $validator,
        Software $software,
        mixed $parentSoftwareId,
    ): void {
        if (blank($parentSoftwareId)) {
            return;
        }

        if ($software->childSoftwares()->exists()) {
            $validator->errors()->add(
                'parent_software_id',
                __('Software that already has sub-software cannot become a child of another product.'),
            );
        }
    }
}
