<?php

use App\Http\Controllers\Assets\ManageHardwareController;
use App\Http\Controllers\Assets\ManageUserwareController;
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
    Route::prefix('/hardware')->group(function(){
        Route::get('/',[ManageHardwareController::class,'list'])->name('assets.hardware-list');
    });

    Route::prefix('/softwareware')->group(function(){

    });
    Route::prefix('/virtualware')->group(function(){

    });
    Route::prefix('/userware')->group(function(){
        Route::get('/',[ManageUserwareController::class,'list'])->name('assets.userware-list');
    });
})->middleware(['auth', 'verified']);
