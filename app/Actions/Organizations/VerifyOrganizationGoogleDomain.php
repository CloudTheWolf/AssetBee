<?php

namespace App\Actions\Organizations;

use App\Contracts\DomainDnsLookup;
use App\Models\OrganizationGoogleDomain;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VerifyOrganizationGoogleDomain
{
    public function __construct(
        private DomainDnsLookup $dnsLookup,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(OrganizationGoogleDomain $googleDomain): OrganizationGoogleDomain
    {
        if ($googleDomain->isVerified()) {
            return $googleDomain;
        }

        Validator::make(
            ['domain' => $googleDomain->domain],
            [
                'domain' => [
                    function (string $attribute, mixed $value, \Closure $fail) use ($googleDomain): void {
                        if (
                            $googleDomain->verification_last_checked_at !== null
                            && $googleDomain->verification_last_checked_at->gt(now()->subSeconds(30))
                        ) {
                            $fail(__('Please wait a moment before checking DNS again.'));
                        }
                    },
                ],
            ],
        )->validate();

        $googleDomain->forceFill([
            'verification_last_checked_at' => now(),
        ])->save();

        $expected = $googleDomain->txtRecordValue();
        $records = $this->dnsLookup->txtRecords($googleDomain->domain);

        $matched = collect($records)->contains(
            fn (string $record): bool => str_contains($record, $expected),
        );

        if (! $matched) {
            throw ValidationException::withMessages([
                'domain' => __('We could not find the TXT record :record on :domain. DNS changes can take a few minutes to appear.', [
                    'record' => $expected,
                    'domain' => $googleDomain->domain,
                ]),
            ]);
        }

        $googleDomain->forceFill([
            'verified_at' => now(),
        ])->save();

        return $googleDomain->refresh();
    }
}
