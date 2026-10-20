<?php

use App\Http\Controllers\Assets\DownloadAssetDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'organization'])->group(function () {
    Route::livewire('assets/userware', 'pages::assets.userware.index')->name('assets.userware.index');
    Route::livewire('assets/userware/{userware}', 'pages::assets.userware.show')->name('assets.userware.show');

    Route::livewire('assets/hardware', 'pages::assets.hardware.index')->name('assets.hardware.index');
    Route::livewire('assets/hardware/{hardware}', 'pages::assets.hardware.show')->name('assets.hardware.show');

    Route::livewire('assets/virtualware', 'pages::assets.virtualware.index')->name('assets.virtualware.index');
    Route::livewire('assets/virtualware/{virtualware}', 'pages::assets.virtualware.show')->name('assets.virtualware.show');

    Route::livewire('assets/software', 'pages::assets.software.index')->name('assets.software.index');
    Route::livewire('assets/software/{software}', 'pages::assets.software.show')->name('assets.software.show');

    Route::livewire('assets/cloud-tenants', 'pages::assets.cloud-tenants.index')->name('assets.cloud-tenants.index');
    Route::livewire('assets/cloud-tenants/{cloudTenant}', 'pages::assets.cloud-tenants.show')->name('assets.cloud-tenants.show');

    Route::livewire('assets/device-groups', 'pages::assets.device-groups.index')->name('assets.device-groups.index');
    Route::livewire('assets/device-groups/{deviceGroup}', 'pages::assets.device-groups.show')->name('assets.device-groups.show');

    Route::livewire('assets/remote-packages', 'pages::assets.remote-packages.index')->name('assets.remote-packages.index');

    Route::livewire('assets/remote-pushes', 'pages::assets.remote-pushes.index')->name('assets.remote-pushes.index');
    Route::livewire('assets/remote-pushes/create', 'pages::assets.remote-pushes.create')->name('assets.remote-pushes.create');
    Route::livewire('assets/remote-pushes/{remotePush}', 'pages::assets.remote-pushes.show')->name('assets.remote-pushes.show');

    Route::get('assets/documents/{document}/download', DownloadAssetDocumentController::class)
        ->name('assets.documents.download');
});
