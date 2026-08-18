<?php

namespace App\Actions\Assets;

use App\Enums\SoftwareLicenseType;
use App\Models\Software;
use App\Models\SoftwareAssignment;
use App\Models\Userware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BulkAssignSoftwareSeats
{
    /**
     * @param  list<int|string>  $userwareIds
     * @return array{assigned: int, skipped: int, assignments: Collection<int, SoftwareAssignment>}
     *
     * @throws ValidationException
     */
    public function handle(Software $software, array $userwareIds): array
    {
        $validated = Validator::make(
            ['userware_ids' => $userwareIds],
            [
                'userware_ids' => ['required', 'array', 'min:1'],
                'userware_ids.*' => [
                    'required',
                    'integer',
                    Rule::exists('userwares', 'id')
                        ->where('organization_id', $software->organization_id)
                        ->whereNull('deleted_at'),
                ],
            ],
            [
                'userware_ids.required' => __('Select at least one identity to assign.'),
                'userware_ids.min' => __('Select at least one identity to assign.'),
            ],
        )->validate();

        /** @var list<int|string> $validatedUserwareIds */
        $validatedUserwareIds = $validated['userware_ids'];

        /** @var list<int> $uniqueIds */
        $uniqueIds = collect($validatedUserwareIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $alreadyAssignedIds = SoftwareAssignment::query()
            ->where('software_id', $software->id)
            ->whereIn('userware_id', $uniqueIds)
            ->pluck('userware_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $idsToAssign = array_values(array_diff($uniqueIds, $alreadyAssignedIds));

        if ($idsToAssign === []) {
            throw ValidationException::withMessages([
                'selectedUserwareIds' => __('All selected identities already have a seat for this license.'),
            ]);
        }

        if (
            $software->license_type === SoftwareLicenseType::Seat
            && $software->total_seats !== null
            && count($idsToAssign) > (int) $software->seatsAvailable()
        ) {
            throw ValidationException::withMessages([
                'selectedUserwareIds' => __('Only :count seats are available for this license.', [
                    'count' => $software->seatsAvailable(),
                ]),
            ]);
        }

        $userwares = Userware::query()
            ->where('organization_id', $software->organization_id)
            ->whereIn('id', $idsToAssign)
            ->get()
            ->keyBy('id');

        if ($userwares->count() !== count($idsToAssign)) {
            throw ValidationException::withMessages([
                'selectedUserwareIds' => __('One or more selected identities could not be found.'),
            ]);
        }

        $assignments = DB::transaction(function () use ($software, $idsToAssign): Collection {
            $created = collect();

            foreach ($idsToAssign as $userwareId) {
                $created->push(SoftwareAssignment::query()->create([
                    'software_id' => $software->id,
                    'userware_id' => $userwareId,
                    'assigned_at' => now(),
                    'notes' => null,
                ]));
            }

            return $created;
        });

        return [
            'assigned' => $assignments->count(),
            'skipped' => count($alreadyAssignedIds),
            'assignments' => $assignments,
        ];
    }
}
