<?php

use Illuminate\Support\Facades\File;

test('views only use full page navigation', function () {
    $unsafeNavigation = [];

    foreach (File::allFiles(resource_path('views')) as $view) {
        $contents = $view->getContents();

        if (str_contains($contents, 'wire:navigate')) {
            $unsafeNavigation[$view->getRelativePathname()][] = 'wire:navigate';
        }

        if (preg_match('/navigate\s*:\s*true/', $contents) === 1) {
            $unsafeNavigation[$view->getRelativePathname()][] = 'navigate: true';
        }

        if (str_contains($contents, 'Livewire.navigate(')) {
            $unsafeNavigation[$view->getRelativePathname()][] = 'Livewire.navigate()';
        }
    }

    expect($unsafeNavigation)->toBeEmpty();
});
