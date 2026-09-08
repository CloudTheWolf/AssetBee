<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateOrganization
{
    public function __construct(
        private SyncOrganizationGoogleDomains $syncOrganizationGoogleDomains,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization, array $input): Organization
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'google_hosted_domains' => ['nullable'],
            'virtualware_sync_enabled' => ['sometimes', 'boolean'],
            'cost_sync_enabled' => ['sometimes', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($organization, $validated, $input) {
            $organization->update([
                'name' => $validated['name'],
                'slug' => $organization->slug === Str::slug($organization->name)
                    ? $this->uniqueSlug($validated['name'], $organization->id)
                    : $organization->slug,
                ...array_key_exists('virtualware_sync_enabled', $validated)
                    ? ['virtualware_sync_enabled' => (bool) $validated['virtualware_sync_enabled']]
                    : [],
                ...array_key_exists('cost_sync_enabled', $validated)
                    ? ['cost_sync_enabled' => (bool) $validated['cost_sync_enabled']]
                    : [],
            ]);

            $this->syncOrganizationGoogleDomains->handle(
                $organization,
                $input['google_hosted_domains'] ?? [],
            );

            return $organization->refresh();
        });
    }

    private function uniqueSlug(string $name, int $ignoreId): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;
        $suffix = 1;

        while (Organization::query()->where('slug', $slug)->where('id', '!=', $ignoreId)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
