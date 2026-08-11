<?php

use App\Support\SailRuntime;
use Illuminate\Support\Facades\File;

test('sail runtime accepts directories that support utime', function () {
    $directory = storage_path('framework/testing/sail-runtime-'.uniqid());

    expect(SailRuntime::pathSupportsUtime($directory))->toBeTrue();
    expect(SailRuntime::ensureCompiledViewPath($directory))->toBe($directory);

    File::deleteDirectory($directory);
});

test('sail runtime falls back when the preferred compiled path cannot be created', function () {
    $original = config('view.compiled');

    // Point at a path under a file so directory creation fails.
    $blocker = storage_path('framework/testing/sail-runtime-blocker-'.uniqid());
    File::put($blocker, 'not-a-directory');
    $unusable = $blocker.DIRECTORY_SEPARATOR.'views';

    try {
        config(['view.compiled' => $unusable]);

        $result = SailRuntime::ensureCompiledViewPath($unusable);

        expect($result)->not->toBe($unusable)
            ->and($result)->toContain('assetbee-compiled-views-')
            ->and(SailRuntime::pathSupportsUtime($result))->toBeTrue()
            ->and(config('view.compiled'))->toBe($result);
    } finally {
        config(['view.compiled' => $original]);
        File::delete($blocker);
    }
});
