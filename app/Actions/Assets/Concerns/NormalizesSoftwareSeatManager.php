<?php

namespace App\Actions\Assets\Concerns;

use App\Enums\SoftwareSeatManagerType;
use App\Models\Organization;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait NormalizesSoftwareSeatManager
{
    /**
     * @return array<string, mixed>
     */
    protected function seatManagerRules(Organization $organization): array
    {
        return [
            'seat_manager_type' => ['nullable', Rule::enum(SoftwareSeatManagerType::class)],
            'seat_manager_userware_id' => [
                'nullable',
                'integer',
                Rule::exists('userwares', 'id')
                    ->where('organization_id', $organization->id)
                    ->whereNull('deleted_at'),
            ],
            'seat_manager_department' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function normalizeSeatManager(array $validated, Organization $organization): array
    {
        $type = $validated['seat_manager_type'] ?? null;

        if ($type === null || $type === '') {
            $validated['seat_manager_type'] = null;
            $validated['seat_manager_userware_id'] = null;
            $validated['seat_manager_department'] = null;

            return $validated;
        }

        $type = $type instanceof SoftwareSeatManagerType
            ? $type
            : SoftwareSeatManagerType::from((string) $type);

        $validated['seat_manager_type'] = $type;

        if ($type === SoftwareSeatManagerType::Userware) {
            Validator::make($validated, [
                'seat_manager_userware_id' => [
                    'required',
                    'integer',
                    Rule::exists('userwares', 'id')
                        ->where('organization_id', $organization->id)
                        ->whereNull('deleted_at'),
                ],
            ])->validate();

            $validated['seat_manager_department'] = null;

            return $validated;
        }

        Validator::make($validated, [
            'seat_manager_department' => ['required', 'string', 'max:255'],
        ])->validate();

        $validated['seat_manager_userware_id'] = null;

        return $validated;
    }
}
