<?php

namespace App\Support;

class InventoryProbe
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function status(array $payload, string $key): ?string
    {
        $status = data_get($payload, "{$key}.status");

        return is_string($status) ? $status : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public static function list(array $payload, string $key): array
    {
        if (self::status($payload, $key) !== 'available') {
            return [];
        }

        $value = data_get($payload, "{$key}.value");

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public static function value(array $payload, string $key): ?array
    {
        if (self::status($payload, $key) !== 'available') {
            return null;
        }

        $value = data_get($payload, "{$key}.value");

        return is_array($value) ? $value : null;
    }

    /**
     * Prefer protectionStatus when present; otherwise fall back to state containing "encrypted".
     *
     * @param  array<string, mixed>  $volume
     */
    public static function volumeIsProtected(array $volume): bool
    {
        $protectionStatus = $volume['protectionStatus'] ?? null;

        if (is_string($protectionStatus) && $protectionStatus !== '') {
            return strcasecmp($protectionStatus, 'On') === 0;
        }

        $state = $volume['state'] ?? null;

        return is_string($state) && str_contains(strtolower($state), 'encrypted');
    }

    /**
     * @param  array<string, mixed>  $volume
     */
    public static function volumeIsTpmOnly(array $volume): bool
    {
        $state = $volume['state'] ?? null;

        return is_string($state) && str_contains(strtolower($state), 'tpm only');
    }

    /**
     * @param  array<string, mixed>  $volume
     */
    public static function volumeHasRecoveryKey(array $volume): bool
    {
        foreach ($volume['keyProtectors'] ?? [] as $protector) {
            if (
                is_array($protector)
                && is_string($protector['recoveryKey'] ?? null)
                && $protector['recoveryKey'] !== ''
            ) {
                return true;
            }
        }

        foreach ($volume['recoveryKeys'] ?? [] as $recoveryKey) {
            if (is_string($recoveryKey) && $recoveryKey !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function hasRecoveryKey(array $payload): bool
    {
        foreach (self::list($payload, 'diskEncryption') as $volume) {
            if (self::volumeHasRecoveryKey($volume)) {
                return true;
            }
        }

        return false;
    }
}
