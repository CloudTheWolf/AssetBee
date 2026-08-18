<?php

namespace App\Actions\Assets;

use App\Enums\UserwareStatus;
use App\Models\Organization;
use App\Models\Userware;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportUserwareFromCsv
{
    private const REQUIRED_HEADERS = [
        'First Name',
        'Last Name',
        'Email Address',
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
            $seenEmails = [];

            while (($row = fgetcsv($handle)) !== false) {
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $firstName = trim((string) ($row[$columnIndexes['First Name']] ?? ''));
                $lastName = trim((string) ($row[$columnIndexes['Last Name']] ?? ''));
                $email = strtolower(trim((string) ($row[$columnIndexes['Email Address']] ?? '')));

                Validator::make(
                    [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email,
                    ],
                    [
                        'first_name' => ['required', 'string', 'max:255'],
                        'last_name' => ['required', 'string', 'max:255'],
                        'email' => ['required', 'email', 'max:255'],
                    ],
                )->validate();

                if (isset($seenEmails[$email]) || $this->emailExists($organization, $email)) {
                    $skipped++;

                    continue;
                }

                $seenEmails[$email] = true;

                $organization->userwares()->create([
                    'name' => trim($firstName.' '.$lastName),
                    'email' => $email,
                    'employee_id' => null,
                    'department' => null,
                    'status' => UserwareStatus::Active,
                    'notes' => null,
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
     * @return array{'First Name': int, 'Last Name': int, 'Email Address': int}
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

    private function emailExists(Organization $organization, string $email): bool
    {
        return Userware::withTrashed()
            ->where('organization_id', $organization->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists();
    }
}
