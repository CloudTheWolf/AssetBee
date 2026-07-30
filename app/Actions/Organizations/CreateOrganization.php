<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateOrganization
{
    public function __construct(
        private SyncOrganizationGoogleDomains $syncOrganizationGoogleDomains,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $input): Organization
    {
        $domains = $this->syncOrganizationGoogleDomains->normalize(
            $input['google_hosted_domains'] ?? $input['google_hosted_domain'] ?? [],
        );

        Validator::make(
            [
                'name' => $input['name'] ?? null,
                'google_hosted_domains' => $domains->all(),
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'google_hosted_domains' => ['array'],
                'google_hosted_domains.*' => [
                    'required',
                    'string',
                    'max:255',
                    'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/',
                    'unique:organization_google_domains,domain',
                ],
            ],
            [
                'google_hosted_domains.*.regex' => __('Each Google Workspace domain must be a valid domain name.'),
                'google_hosted_domains.*.unique' => __('One or more Google Workspace domains are already linked to another organization.'),
            ],
        )->validate();

        return DB::transaction(function () use ($user, $input, $domains) {
            $organization = Organization::create([
                'name' => $input['name'],
                'slug' => $this->uniqueSlug($input['name']),
            ]);

            $this->syncOrganizationGoogleDomains->handle($organization, $domains->all());

            $organization->users()->attach($user->id, [
                'role' => OrganizationRole::Owner->value,
            ]);

            CurrentOrganization::set($organization);

            return $organization;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;
        $suffix = 1;

        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
