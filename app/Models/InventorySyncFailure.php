<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\InventorySyncFailureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $organization_api_key_id
 * @property int|null $hardware_id
 * @property int|null $virtualware_id
 * @property string $identity_key
 * @property string|null $device_name
 * @property string|null $serial_number
 * @property string|null $platform
 * @property int $status_code
 * @property string $message
 * @property array<string, list<string>>|null $errors
 * @property int $attempt_count
 * @property CarbonInterface $first_failed_at
 * @property CarbonInterface $last_failed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'organization_id',
    'organization_api_key_id',
    'hardware_id',
    'virtualware_id',
    'identity_key',
    'device_name',
    'serial_number',
    'platform',
    'status_code',
    'message',
    'errors',
    'attempt_count',
    'first_failed_at',
    'last_failed_at',
])]
class InventorySyncFailure extends Model
{
    /** @use HasFactory<InventorySyncFailureFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'status_code' => 'integer',
            'attempt_count' => 'integer',
            'first_failed_at' => 'datetime',
            'last_failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<OrganizationApiKey, $this>
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(OrganizationApiKey::class, 'organization_api_key_id');
    }

    /**
     * @return BelongsTo<Hardware, $this>
     */
    public function hardware(): BelongsTo
    {
        return $this->belongsTo(Hardware::class);
    }

    /**
     * @return BelongsTo<Virtualware, $this>
     */
    public function virtualware(): BelongsTo
    {
        return $this->belongsTo(Virtualware::class);
    }

    public static function identityKey(string $serialNumber, string $deviceName): ?string
    {
        $serialNumber = trim($serialNumber);
        $deviceName = trim($deviceName);

        if ($serialNumber !== '') {
            return 'serial:'.mb_strtolower($serialNumber);
        }

        if ($deviceName !== '') {
            return 'name:'.mb_strtolower($deviceName);
        }

        return null;
    }

    public function displayName(): string
    {
        return $this->device_name
            ?: $this->serial_number
            ?: __('Unknown device');
    }

    public function detailSummary(): string
    {
        $when = $this->last_failed_at->diffForHumans();

        return __('HTTP :status · :count attempt(s) · last :when', [
            'status' => $this->status_code,
            'count' => $this->attempt_count,
            'when' => $when,
        ]).' — '.$this->message;
    }
}
