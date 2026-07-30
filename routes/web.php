<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\SwitchOrganizationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('organizations/create', 'pages::organizations.create')->name('organizations.create');

    Route::post('organizations/{organization}/switch', SwitchOrganizationController::class)
        ->name('organizations.switch');
});

Route::middleware(['auth', 'verified', 'organization'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('organizations/manage', 'pages::organizations.manage')->name('organizations.manage');
});

Route::livewire('invitations/{token}', 'pages::invitations.show')->name('invitations.show');

require __DIR__.'/assets.php';
require __DIR__.'/settings.php';
