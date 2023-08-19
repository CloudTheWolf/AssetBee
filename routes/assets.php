<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminMainController;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Web routes for Admin pages
|
*/

Route::prefix('/assets')->group(function (){
    Route::get('/',[AdminMainController::class,'Get'])->name('assets');
})->middleware(['auth', 'verified']);
