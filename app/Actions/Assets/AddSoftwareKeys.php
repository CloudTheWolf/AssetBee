<?php

namespace App\Actions\Assets;

use App\Enums\SoftwareLicenseType;
use App\Models\Software;
use App\Models\SoftwareKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AddSoftwareKeys
{
    /**
     * @return Collection<int, SoftwareKey>
     *
     * @throws ValidationException
     */
    public function handle(Software $software, string $keysInput, ?string $label = null): Collection
    {
        if ($software->license_type !== SoftwareLicenseType::Key) {
            throw ValidationException::withMessages([
                'newKeys' => __('License keys can only be added to key-based software.'),
            ]);
        }

        $validated = Validator::make(
            [
                'keys' => $keysInput,
                'label' => $label,
            ],
            [
                'keys' => ['required', 'string'],
                'label' => ['nullable', 'string', 'max:255'],
            ],
        )->validate();

        $values = collect(preg_split('/\r\n|\r|\n/', $validated['keys']) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => $line !== '')
            ->values();

        if ($values->isEmpty()) {
            throw ValidationException::withMessages([
                'newKeys' => __('Enter at least one license key.'),
            ]);
        }

        $labelValue = isset($validated['label']) && is_string($validated['label']) && $validated['label'] !== ''
            ? $validated['label']
            : null;

        return DB::transaction(function () use ($software, $values, $labelValue): Collection {
            return $values->map(function (string $value) use ($software, $labelValue): SoftwareKey {
                return SoftwareKey::query()->create([
                    'software_id' => $software->id,
                    'value' => $value,
                    'label' => $labelValue,
                ]);
            });
        });
    }
}
