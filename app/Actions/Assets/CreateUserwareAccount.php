<?php

namespace App\Actions\Assets;

use App\Models\Userware;
use App\Models\UserwareAccount;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorContract;

class CreateUserwareAccount
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Userware $userware, array $input): UserwareAccount
    {
        $validator = Validator::make($input, [
            'software_id' => [
                'nullable',
                'integer',
                Rule::exists('softwares', 'id')->where('organization_id', $userware->organization_id),
            ],
            'site_name' => ['nullable', 'string', 'max:255'],
            'site_url' => ['nullable', 'string', 'max:2048', 'url'],
            'username' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->assertAccountTarget($validator, $input);

        $validated = $this->normalizeAccountTarget($validator->validate());

        return $userware->accounts()->create([
            ...$validated,
            'organization_id' => $userware->organization_id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function assertAccountTarget(ValidatorContract $validator, array $input): void
    {
        $validator->after(function (ValidatorContract $validator) use ($input): void {
            $hasSoftware = ! empty($input['software_id']);
            $hasSiteName = filled($input['site_name'] ?? null);
            $hasSiteUrl = filled($input['site_url'] ?? null);

            if ($hasSoftware && ($hasSiteName || $hasSiteUrl)) {
                $validator->errors()->add(
                    'software_id',
                    __('Choose either linked software or an external site, not both.'),
                );
            }

            if (! $hasSoftware && (! $hasSiteName || ! $hasSiteUrl)) {
                $validator->errors()->add(
                    'site_name',
                    __('Provide linked software, or both a site name and URL.'),
                );
            }
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function normalizeAccountTarget(array $validated): array
    {
        if (! empty($validated['software_id'])) {
            $validated['site_name'] = null;
            $validated['site_url'] = null;
        } else {
            $validated['software_id'] = null;
        }

        return $validated;
    }
}
