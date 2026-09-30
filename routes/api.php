<?php

use App\Http\Controllers\AdminProfileController;
use App\Http\Controllers\AdminStatisticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VehicleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/change-password', [ProfileController::class, 'changePassword'])->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/vehicles', [VehicleController::class, 'index']);
Route::get('/vehicles/{maXe}', [VehicleController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'getProfile']);
    Route::put('/profile', [ProfileController::class, 'updateProfile']);
    Route::post('/profile/upload-docs', [ProfileController::class, 'uploadDocs']);
    Route::post('/change-password', [ProfileController::class, 'changePassword']);

    Route::middleware('role:nhan_vien,admin')->group(function () {
        Route::get('/admin/profiles', [AdminProfileController::class, 'getProfiles']);
        Route::get('/admin/profiles/pending', [AdminProfileController::class, 'getPendingProfiles']);
        Route::post('/admin/profiles/update-status', [AdminProfileController::class, 'updateStatus']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/statistics', [AdminStatisticsController::class, 'index']);
    });
});