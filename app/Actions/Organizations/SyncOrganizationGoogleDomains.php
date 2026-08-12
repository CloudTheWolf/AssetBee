<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SyncOrganizationGoogleDomains
{
    /**
     * @param  array<int, string>|string|null  $domains
     * @return Collection<int, string>
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization, array|string|null $domains): Collection
    {
        $normalized = $this->normalize($domains);

        Validator::make(
            ['google_hosted_domains' => $normalized->all()],
            [
                'google_hosted_domains' => ['array'],
                'google_hosted_domains.*' => [
                    'required',
                    'string',
                    'max:255',
                    'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/',
                    Rule::unique('organization_google_domains', 'domain')
                        ->whereNot('organization_id', $organization->id),
                ],
            ],
            [
                'google_hosted_domains.*.regex' => __('Each Google Workspace domain must be a valid domain name.'),
                'google_hosted_domains.*.unique' => __('One or more Google Workspace domains are already linked to another organization.'),
            ],
        )->validate();

        $organization->googleDomains()
            ->whereNotIn('domain', $normalized->all())
            ->delete();

        foreach ($normalized as $domain) {
            $organization->googleDomains()->firstOrCreate(
                ['domain' => $domain],
                // New claims start unverified; existing rows keep their token / verified_at.
                [],
            );
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>|string|null  $domains
     * @return Collection<int, string>
     */
    public function normalize(array|string|null $domains): Collection
    {
        if (is_string($domains)) {
            $domains = preg_split('/[\s,]+/', $domains) ?: [];
        }

        /** @var list<string> $normalized */
        $normalized = collect($domains ?? [])
            ->map(fn (mixed $domain): string => Str::lower(trim((string) $domain)))
            ->filter(fn (string $domain): bool => $domain !== '')
            ->unique()
            ->values()
            ->all();

        return collect($normalized);
    }
}
