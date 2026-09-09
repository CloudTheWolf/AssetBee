<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum AtlassianCostProduct: string
{
    case Jira = 'jira';
    case Confluence = 'confluence';
    case Compass = 'compass';

    public function label(): string
    {
        return match ($this) {
            self::Jira => 'Jira',
            self::Confluence => 'Confluence',
            self::Compass => 'Compass',
        };
    }

    /**
     * Default Atlassian Admin `product_access.key` values.
     *
     * @return list<string>
     */
    public function defaultKeys(): array
    {
        return match ($this) {
            self::Jira => ['jira-software'],
            self::Confluence => ['confluence'],
            self::Compass => ['compass'],
        };
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array{
     *     slug: string,
     *     label: string,
     *     price_per_seat: float,
     *     keys: list<string>,
     *     name_contains: string|null,
     *     child_software_id: int|null,
     *     custom: bool
     * }
     */
    public function resolvedConfig(array $stored = []): array
    {
        $keys = self::normalizeKeys($stored['keys'] ?? null);
        if ($keys === []) {
            $keys = $this->defaultKeys();
        }

        $childId = $stored['child_software_id'] ?? null;

        return [
            'slug' => $this->value,
            'label' => $this->label(),
            'price_per_seat' => is_numeric($stored['price_per_seat'] ?? null)
                ? (float) $stored['price_per_seat']
                : 0.0,
            'keys' => $keys,
            'name_contains' => null,
            'child_software_id' => is_numeric($childId) ? (int) $childId : null,
            'custom' => false,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $credentials
     * @return list<array{
     *     slug: string,
     *     label: string,
     *     price_per_seat: float,
     *     keys: list<string>,
     *     name_contains: string|null,
     *     child_software_id: int|null,
     *     custom: bool
     * }>
     */
    public static function configsFromCredentials(?array $credentials): array
    {
        $storedProducts = is_array($credentials['products'] ?? null) ? $credentials['products'] : [];

        $builtIns = array_map(
            function (self $product) use ($storedProducts): array {
                $stored = $storedProducts[$product->value] ?? [];

                return $product->resolvedConfig(is_array($stored) ? $stored : []);
            },
            self::cases(),
        );

        return [...$builtIns, ...self::addonConfigsFromCredentials($credentials)];
    }

    /**
     * @param  array<string, mixed>|null  $credentials
     * @return list<array{
     *     slug: string,
     *     label: string,
     *     price_per_seat: float,
     *     keys: list<string>,
     *     name_contains: string|null,
     *     child_software_id: int|null,
     *     custom: bool
     * }>
     */
    public static function addonConfigsFromCredentials(?array $credentials): array
    {
        $addons = self::rawAddonsFromCredentials($credentials);
        $configs = [];

        foreach ($addons as $addon) {
            if (! is_array($addon)) {
                continue;
            }

            $label = trim((string) ($addon['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $slug = trim((string) ($addon['slug'] ?? ''));
            if ($slug === '') {
                $slug = self::slugForAddonLabel($label);
            }

            $nameContains = $addon['name_contains'] ?? null;
            $nameContains = is_string($nameContains) && trim($nameContains) !== ''
                ? trim($nameContains)
                : null;

            $keys = self::normalizeKeys($addon['keys'] ?? null);
            $childId = $addon['child_software_id'] ?? null;

            $configs[] = [
                'slug' => $slug,
                'label' => $label,
                'price_per_seat' => is_numeric($addon['price_per_seat'] ?? null)
                    ? (float) $addon['price_per_seat']
                    : 0.0,
                'keys' => $keys,
                'name_contains' => $nameContains,
                'child_software_id' => is_numeric($childId) ? (int) $childId : null,
                'custom' => true,
            ];
        }

        return $configs;
    }

    /**
     * @param  array<string, mixed>|null  $credentials
     * @return list<array{slug: string, label: string, price_per_seat: string, keys: string, name_contains: string, custom: bool}>
     */
    public static function formDefaults(?array $credentials): array
    {
        return array_map(
            static fn (array $config): array => [
                'slug' => $config['slug'],
                'label' => $config['label'],
                'price_per_seat' => $config['price_per_seat'] > 0
                    ? (string) $config['price_per_seat']
                    : '',
                'keys' => implode(', ', $config['keys']),
                'name_contains' => (string) ($config['name_contains'] ?? ''),
                'custom' => (bool) $config['custom'],
            ],
            self::configsFromCredentials($credentials),
        );
    }

    /**
     * @return array{slug: string, label: string, price_per_seat: string, keys: string, name_contains: string, custom: bool}
     */
    public static function blankAddonFormRow(?string $label = null): array
    {
        $label = trim((string) $label);

        return [
            'slug' => $label !== '' ? self::slugForAddonLabel($label) : '',
            'label' => $label,
            'price_per_seat' => '',
            'keys' => '',
            'name_contains' => $label,
            'custom' => true,
        ];
    }

    public static function slugForAddonLabel(string $label): string
    {
        $slug = Str::slug($label, '_');

        return $slug !== '' ? 'addon_'.$slug : 'addon_'.Str::lower(Str::random(8));
    }

    /**
     * @return list<string>
     */
    public static function normalizeKeys(mixed $keys): array
    {
        if (is_string($keys)) {
            $keys = preg_split('/\s*,\s*/', $keys, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        if (! is_array($keys)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $key): string => strtolower(trim((string) $key)),
            $keys,
        ))));
    }

    /**
     * @param  array<string, mixed>|null  $credentials
     * @return list<mixed>
     */
    protected static function rawAddonsFromCredentials(?array $credentials): array
    {
        $addons = is_array($credentials['addons'] ?? null) ? $credentials['addons'] : [];

        // Migrate the previously hard-coded marketplace product into addons.
        $legacy = is_array($credentials['products']['git_integration_for_jira'] ?? null)
            ? $credentials['products']['git_integration_for_jira']
            : null;

        if ($legacy !== null) {
            $alreadyPresent = collect($addons)->contains(function (mixed $addon): bool {
                if (! is_array($addon)) {
                    return false;
                }

                $slug = (string) ($addon['slug'] ?? '');
                $label = strtolower((string) ($addon['label'] ?? ''));

                return $slug === 'git_integration_for_jira'
                    || $slug === 'addon_git_integration_for_jira'
                    || str_contains($label, 'git integration');
            });

            if (! $alreadyPresent) {
                array_unshift($addons, [
                    'slug' => 'addon_git_integration_for_jira',
                    'label' => 'Git Integration for Jira',
                    'keys' => $legacy['keys'] ?? ['com.bigbrassband.jira-git-plugin'],
                    'name_contains' => $legacy['name_contains'] ?? 'Git Integration',
                    'price_per_seat' => $legacy['price_per_seat'] ?? 0,
                    'child_software_id' => $legacy['child_software_id'] ?? null,
                ]);
            }
        }

        return array_values($addons);
    }
}
