<?php

use App\Http\Controllers\Assets\ManageHardwareController;
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
    Route::get('/hardware',[ManageHardwareController::class,'list'])->name('assets.hardware-list');
})->middleware(['auth', 'verified']);
