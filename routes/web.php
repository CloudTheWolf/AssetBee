<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrganizationBillingController;
use App\Http\Controllers\SwitchOrganizationController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');
});

Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->name('auth.google.callback');

Route::middleware(['auth', 'verified', 'password.confirm'])->group(function () {
    Route::get('auth/google/link', [GoogleAuthController::class, 'linkRedirect'])
        ->name('auth.google.link');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('organizations/create', 'pages::organizations.create')->name('organizations.create');

    Route::post('organizations/{organization}/switch', SwitchOrganizationController::class)
        ->name('organizations.switch');

    Route::delete('system/customer-context', [SwitchOrganizationController::class, 'exit'])
        ->middleware('system')
        ->name('system.customers.exit');
});

Route::middleware(['auth', 'verified', 'system'])->group(function () {
    Route::livewire('system/customers', 'pages::system.customers')->name('system.customers');
    Route::livewire('system/packages', 'pages::system.packages')->name('system.packages');
});

Route::middleware(['auth', 'verified', 'organization'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('organizations/manage', 'pages::organizations.manage')->name('organizations.manage');
    Route::livewire('organizations/audit-log', 'pages::organizations.audit-log')->name('organizations.audit-log');
    Route::livewire('organizations/billing', 'pages::organizations.billing')->name('organizations.billing');
    Route::post('organizations/billing/packages/{package}/checkout', [OrganizationBillingController::class, 'checkout'])
        ->name('organizations.billing.checkout');
    Route::post('organizations/billing/portal', [OrganizationBillingController::class, 'portal'])
        ->name('organizations.billing.portal');
    Route::get('organizations/billing/success', [OrganizationBillingController::class, 'success'])
        ->name('organizations.billing.success');
    Route::get('organizations/billing/cancel', [OrganizationBillingController::class, 'cancel'])
        ->name('organizations.billing.cancel');
});

Route::livewire('invitations/{token}', 'pages::invitations.show')->name('invitations.show');

require __DIR__.'/assets.php';
require __DIR__.'/settings.php';
