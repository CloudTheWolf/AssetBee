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
}
