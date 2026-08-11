<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class SailRuntime
{
    /**
     * Ensure the compiled-view path can be written and utime()'d.
     *
     * Windows/WSL bind mounts frequently allow file creation but reject touch/utime,
     * which breaks Livewire's compiler (and Blade expiry checks).
     */
    public static function ensureCompiledViewPath(?string $compiledPath = null): string
    {
        $compiledPath ??= (string) config('view.compiled');

        if ($compiledPath !== '' && self::pathSupportsUtime($compiledPath)) {
            return $compiledPath;
        }

        $fallback = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .'assetbee-compiled-views-'
            .getmyuid();

        File::ensureDirectoryExists($fallback, 0775);

        if (self::pathSupportsUtime($fallback)) {
            config(['view.compiled' => $fallback]);

            return $fallback;
        }

        return $compiledPath;
    }

    public static function pathSupportsUtime(string $directory): bool
    {
        $probe = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.assetbee-utime-probe';

        try {
            File::ensureDirectoryExists($directory, 0775);

            if (File::put($probe, '1') === false) {
                return false;
            }

            $original = filemtime($probe);

            if ($original === false) {
                return false;
            }

            if (! @touch($probe, $original - 1)) {
                return false;
            }

            return true;
        } catch (\Throwable) {
            return false;
        } finally {
            if (is_file($probe)) {
                @unlink($probe);
            }
        }
    }
}
