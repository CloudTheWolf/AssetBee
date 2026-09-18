<?php

namespace App\Actions\Assets;

use App\Enums\HardwareCategory;
use App\Enums\HardwareOperatingSystem;
use App\Enums\HardwareStatus;
use App\Models\Hardware;
use App\Models\Organization;
use App\Models\Userware;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportHardwareFromCsv
{
    private const REQUIRED_HEADERS = [
        'Device Name',
        'Name',
        'Email',
        'OS',
        'Serial Number',
    ];

    /**
     * @return array{created: int, skipped: int}
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization, UploadedFile $file): array
    {
        Validator::make(['importFile' => $file], [
            'importFile' => ['required', 'file', 'extensions:csv,txt', 'max:10240'],
        ])->validate();

        $path = $file->getRealPath();

        if ($path === false) {
            throw ValidationException::withMessages([
                'importFile' => __('Unable to read the uploaded CSV file.'),
            ]);
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'importFile' => __('Unable to open the uploaded CSV file.'),
            ]);
        }

        try {
            $headerRow = fgetcsv($handle);

            if ($headerRow === false) {
                throw ValidationException::withMessages([
                    'importFile' => __('The CSV file is empty.'),
                ]);
            }

            $headers = $this->normalizeHeaders($headerRow);
            $columnIndexes = $this->resolveColumnIndexes($headers);

            $created = 0;
            $skipped = 0;
            $seenSerialNumbers = [];

            while (($row = fgetcsv($handle)) !== false) {
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $deviceName = trim((string) ($row[$columnIndexes['Device Name']] ?? ''));
                $email = strtolower(trim((string) ($row[$columnIndexes['Email']] ?? '')));
                $os = trim((string) ($row[$columnIndexes['OS']] ?? ''));
                $serialNumber = trim((string) ($row[$columnIndexes['Serial Number']] ?? ''));

                if ($deviceName === '' || $email === '' || $serialNumber === '') {
                    $skipped++;

                    continue;
                }

                if (isset($seenSerialNumbers[$serialNumber]) || $this->serialNumberExists($organization, $serialNumber)) {
                    $skipped++;

                    continue;
                }

                $userware = $this->findUserwareByEmail($organization, $email);

                if ($userware === null) {
                    $skipped++;

                    continue;
                }

                $seenSerialNumbers[$serialNumber] = true;

                $organization->hardwares()->create([
                    'name' => $deviceName,
                    'serial_number' => $serialNumber,
                    'operating_system' => $this->resolveOperatingSystem($os),
                    'category' => HardwareCategory::Laptop,
                    'status' => HardwareStatus::Assigned,
                    'assigned_userware_id' => $userware->id,
                ]);

                $created++;
            }
        } finally {
            fclose($handle);
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  list<string|null>  $headerRow
     * @return list<string>
     */
    private function normalizeHeaders(array $headerRow): array
    {
        return array_map(function (mixed $header): string {
            $value = trim((string) $header);

            return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        }, $headerRow);
    }

    /**
     * @param  list<string>  $headers
     * @return array{'Device Name': int, 'Name': int, 'Email': int, 'OS': int, 'Serial Number': int}
     *
     * @throws ValidationException
     */
    private function resolveColumnIndexes(array $headers): array
    {
        $indexes = [];

        foreach (self::REQUIRED_HEADERS as $requiredHeader) {
            $index = array_search($requiredHeader, $headers, true);

            if ($index === false) {
                throw ValidationException::withMessages([
                    'importFile' => __('The CSV must include the columns: :columns.', [
                        'columns' => implode(', ', self::REQUIRED_HEADERS),
                    ]),
                ]);
            }

            $indexes[$requiredHeader] = $index;
        }

        return $indexes;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function serialNumberExists(Organization $organization, string $serialNumber): bool
    {
        return Hardware::withTrashed()
            ->where('organization_id', $organization->id)
            ->where('serial_number', $serialNumber)
            ->exists();
    }

    private function findUserwareByEmail(Organization $organization, string $email): ?Userware
    {
        return Userware::query()
            ->where('organization_id', $organization->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    private function resolveOperatingSystem(string $os): ?HardwareOperatingSystem
    {
        if ($os === '') {
            return null;
        }

        $normalized = strtolower(str_replace([' ', '-'], '_', $os));

        $fromValue = HardwareOperatingSystem::tryFrom($normalized);

        if ($fromValue !== null) {
            return $fromValue;
        }

        foreach (HardwareOperatingSystem::cases() as $case) {
            if (strcasecmp($case->label(), $os) === 0) {
                return $case;
            }
        }

        return match ($normalized) {
            'win', 'win11', 'windows11' => HardwareOperatingSystem::Windows11,
            'win10', 'windows10' => HardwareOperatingSystem::Windows10,
            'mac', 'osx', 'mac_os' => HardwareOperatingSystem::Macos,
            'chrome_os', 'chrome' => HardwareOperatingSystem::Chromeos,
            default => HardwareOperatingSystem::Other,
        };
    }
}
