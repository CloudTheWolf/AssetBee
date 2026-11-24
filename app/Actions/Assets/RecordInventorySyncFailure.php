<?php

namespace App\Actions\Assets;

use App\Models\Hardware;
use App\Models\InventorySyncFailure;
use App\Models\Organization;
use App\Models\OrganizationApiKey;
use App\Models\Virtualware;
use Illuminate\Support\Str;

class RecordInventorySyncFailure
{
    /**
     * Persist a sanitized inventory sync failure for UI tracking.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, list<string>>  $errors
     */
    public function handle(
        Organization $organization,
        array $payload,
        string $message,
        array $errors,
        int $statusCode = 422,
        ?OrganizationApiKey $apiKey = null,
    ): ?InventorySyncFailure {
        $deviceName = trim((string) data_get($payload, 'deviceName.value', ''));
        $serialNumber = trim((string) data_get($payload, 'serialNumber.value', ''));
        $identityKey = InventorySyncFailure::identityKey($serialNumber, $deviceName);

        if ($identityKey === null) {
            return null;
        }

        $platform = data_get($payload, 'platform');
        $platform = is_string($platform) ? Str::limit($platform, 32, '') : null;
        $asset = $this->findAsset($organization, $serialNumber, $deviceName);
        $now = now();
        $sanitizedErrors = $this->sanitizeErrors($errors);
        $sanitizedMessage = Str::limit(trim($message) !== '' ? trim($message) : __('Inventory sync failed.'), 1000, '');

        /** @var InventorySyncFailure $failure */
        $failure = InventorySyncFailure::query()->firstOrNew([
            'organization_id' => $organization->id,
            'identity_key' => $identityKey,
        ]);

        if (! $failure->exists) {
            $failure->first_failed_at = $now;
            $failure->attempt_count = 1;
        } else {
            $failure->attempt_count = $failure->attempt_count + 1;
        }

        $failure->fill([
            'organization_api_key_id' => $apiKey?->id,
            'hardware_id' => $asset instanceof Hardware ? $asset->id : null,
            'virtualware_id' => $asset instanceof Virtualware ? $asset->id : null,
            'device_name' => $deviceName !== '' ? Str::limit($deviceName, 255, '') : null,
            'serial_number' => $serialNumber !== '' ? Str::limit($serialNumber, 255, '') : null,
            'platform' => $platform,
            'status_code' => $statusCode,
            'message' => $sanitizedMessage,
            'errors' => $sanitizedErrors === [] ? null : $sanitizedErrors,
            'last_failed_at' => $now,
        ]);
        $failure->save();

        return $failure;
    }

    private function findAsset(
        Organization $organization,
        string $serialNumber,
        string $deviceName,
    ): Hardware|Virtualware|null {
        if ($serialNumber !== '') {
            $hardware = $organization->hardwares()->where('serial_number', $serialNumber)->first();
            if ($hardware !== null) {
                return $hardware;
            }

            $virtualware = $organization->virtualwares()->where('serial_number', $serialNumber)->first();
            if ($virtualware !== null) {
                return $virtualware;
            }
        }

        if ($deviceName === '') {
            return null;
        }

        $normalizedName = mb_strtolower($deviceName);

        return $organization->hardwares()->whereRaw('LOWER(name) = ?', [$normalizedName])->first()
            ?? $organization->virtualwares()->whereRaw('LOWER(name) = ?', [$normalizedName])->first();
    }

    /**
     * @param  array<string, mixed>  $errors
     * @return array<string, list<string>>
     */
    private function sanitizeErrors(array $errors): array
    {
        $sanitized = [];

        foreach ($errors as $field => $messages) {
            if (! is_string($field) || ! is_array($messages)) {
                continue;
            }

            $cleanMessages = [];

            foreach ($messages as $message) {
                if (! is_string($message) || trim($message) === '') {
                    continue;
                }

                $cleanMessages[] = Str::limit($message, 500, '');
            }

            if ($cleanMessages !== []) {
                $sanitized[Str::limit($field, 255, '')] = array_values(array_slice($cleanMessages, 0, 20));
            }
        }

        return $sanitized;
    }
}
