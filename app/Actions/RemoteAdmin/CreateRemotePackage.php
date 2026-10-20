<?php

namespace App\Actions\RemoteAdmin;

use App\Enums\RemotePackageSource;
use App\Models\Organization;
use App\Models\RemotePackage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateRemotePackage
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization, array $input): RemotePackage
    {
        $validated = $this->validate($input);

        return $organization->remotePackages()->create($validated);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $input): array
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'source' => ['required', Rule::enum(RemotePackageSource::class)],
            'msi_url' => ['nullable', 'string', 'max:2048', 'url:https'],
            'msi_args' => ['nullable', 'string', 'max:500'],
            'winget_id' => ['nullable', 'string', 'max:255'],
            'choco_id' => ['nullable', 'string', 'max:255'],
            'silent_args' => ['nullable', 'string', 'max:500'],
            'sha256' => ['nullable', 'string', 'size:64', 'regex:/^[a-fA-F0-9]+$/'],
        ])->validate();

        $source = $validated['source'] instanceof RemotePackageSource
            ? $validated['source']
            : RemotePackageSource::from((string) $validated['source']);

        Validator::make($validated, match ($source) {
            RemotePackageSource::Msi => [
                'msi_url' => ['required', 'string', 'max:2048', 'url:https'],
            ],
            RemotePackageSource::Winget => [
                'winget_id' => ['required', 'string', 'max:255'],
            ],
            RemotePackageSource::Choco => [
                'choco_id' => ['required', 'string', 'max:255'],
            ],
        })->validate();

        return [
            'name' => $validated['name'],
            'source' => $source,
            'msi_url' => $source === RemotePackageSource::Msi ? $validated['msi_url'] : null,
            'msi_args' => $source === RemotePackageSource::Msi ? ($validated['msi_args'] ?? null) : null,
            'winget_id' => $source === RemotePackageSource::Winget ? $validated['winget_id'] : null,
            'choco_id' => $source === RemotePackageSource::Choco ? $validated['choco_id'] : null,
            'silent_args' => $validated['silent_args'] ?? null,
            'sha256' => isset($validated['sha256']) ? strtolower($validated['sha256']) : null,
        ];
    }
}
