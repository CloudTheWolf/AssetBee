<?php

use App\Http\Controllers\Admin\ListUsersController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminMainController;
use App\Http\Controllers\Admin\AdminUserController;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Web routes for Admin pages
|
*/

Route::prefix('/admin')->group(function (){
    Route::get('/',[AdminMainController::class,'Get'])->name('admin');
    Route::get('/users',[ListUsersController::class,'Get'])->name('admin-users');
    Route::get('/users/{id}',[AdminUserController::class,'Get'])->name('admin-user');
    Route::patch('/users/{id}',[AdminUserController::class,'Patch'])->name('admin.user.update');
})->middleware(['auth', 'verified']);
