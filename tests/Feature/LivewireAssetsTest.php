<?php

test('livewire javascript asset route is registered for the current app key', function () {
    $prefix = 'livewire-'.substr(hash('sha256', config('app.key').'livewire-endpoint'), 0, 8);
    $script = config('app.debug') ? 'livewire.js' : 'livewire.min.js';

    $this->get("/{$prefix}/{$script}")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
});
