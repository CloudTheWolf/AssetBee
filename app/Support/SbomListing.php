<?php

namespace App\Support;

class SbomListing
{
    /**
     * @param  array<string, mixed>|null  $sbomProbe
     * @return list<array{target: array<string, mixed>, components: list<array<string, mixed>>, matchingCount: int}>
     */
    public static function filteredTargets(?array $sbomProbe, string $search = '', int $displayLimit = 500): array
    {
        if (($sbomProbe['status'] ?? null) !== 'available') {
            return [];
        }

        $needle = mb_strtolower(trim($search));
        $targets = [];

        foreach (data_get($sbomProbe, 'value.targets', []) as $target) {
            if (! is_array($target)) {
                continue;
            }

            $components = isset($target['components']) && is_array($target['components'])
                ? array_values(array_filter($target['components'], fn (mixed $component): bool => is_array($component)))
                : [];

            if ($needle !== '') {
                $components = array_values(array_filter(
                    $components,
                    function (array $component) use ($needle, $target): bool {
                        $haystack = mb_strtolower(implode(' ', array_filter([
                            $target['name'] ?? null,
                            $target['kind'] ?? null,
                            $target['bomRef'] ?? null,
                            $target['image'] ?? null,
                            $component['name'] ?? null,
                            $component['version'] ?? null,
                            $component['type'] ?? null,
                            $component['publisher'] ?? null,
                            $component['purl'] ?? null,
                        ], fn (mixed $value): bool => filled($value))));

                        return str_contains($haystack, $needle);
                    },
                ));
            }

            $matchingCount = count($components);

            if ($matchingCount === 0) {
                continue;
            }

            $targets[] = [
                'target' => $target,
                'components' => array_slice($components, 0, $displayLimit),
                'matchingCount' => $matchingCount,
            ];
        }

        return $targets;
    }

    /**
     * @param  array<string, mixed>|null  $sbomProbe
     */
    public static function componentCount(?array $sbomProbe): int
    {
        $count = 0;

        foreach (data_get($sbomProbe, 'value.targets', []) as $target) {
            if (is_array($target) && is_array($target['components'] ?? null)) {
                $count += count($target['components']);
            }
        }

        return $count;
    }

    /**
     * Return the version from the first SBOM component whose purl matches
     * `pkg:generic/{encodedName}@VERSION` (or a space instead of `%20`).
     *
     * @param  array<string, mixed>|null  $sbomValue  The sbom probe value (targets/format), not the status wrapper.
     */
    public static function versionForGenericPurl(?array $sbomValue, string $packageName): ?string
    {
        if ($sbomValue === null) {
            return null;
        }

        $encodedName = rawurlencode($packageName);
        $pattern = '#^pkg:generic/(?:'.preg_quote($encodedName, '#').'|'.preg_quote($packageName, '#').')@(.+)$#i';

        foreach ($sbomValue['targets'] ?? [] as $target) {
            if (! is_array($target) || ! is_array($target['components'] ?? null)) {
                continue;
            }

            foreach ($target['components'] as $component) {
                if (! is_array($component)) {
                    continue;
                }

                $purl = $component['purl'] ?? null;

                if (! is_string($purl) || $purl === '') {
                    continue;
                }

                if (preg_match($pattern, $purl, $matches) !== 1) {
                    continue;
                }

                $fromPurl = rawurldecode($matches[1]);

                if ($fromPurl !== '') {
                    return $fromPurl;
                }

                $fromField = $component['version'] ?? null;

                if (is_string($fromField) && trim($fromField) !== '') {
                    return trim($fromField);
                }
            }
        }

        return null;
    }
}
