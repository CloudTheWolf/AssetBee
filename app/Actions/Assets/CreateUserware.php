<?php

namespace App\Actions\Assets;

use App\Enums\UserwareStatus;
use App\Models\Organization;
use App\Models\Userware;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateUserware
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization, array $input): Userware
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('userwares', 'email')->where('organization_id', $organization->id),
            ],
            'employee_id' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(UserwareStatus::class)],
            'notes' => ['nullable', 'string'],
        ])->validate();

        return $organization->userwares()->create($validated);
    }
}
