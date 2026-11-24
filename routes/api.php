<?php

use App\Http\Controllers\AdminProfileController;
use App\Http\Controllers\AdminStatisticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/vehicles', [VehicleController::class, 'index']);
Route::get('/vehicles/{maXe}', [VehicleController::class, 'show']);

// Endpoint kiểm tra trạng thái đơn và Webhook xử lý biến động số dư VietQR
Route::get('/bookings/{id}/status', [BookingController::class, 'status']);
Route::post('/payments/webhook', [PaymentController::class, 'webhook']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [ProfileController::class, 'changePassword']);

    Route::get('/profile', [ProfileController::class, 'getProfile']);
    Route::put('/profile', [ProfileController::class, 'updateProfile']);
    Route::post('/profile/upload-docs', [ProfileController::class, 'uploadDocs']);

    // Endpoint quản lý & tạo đơn đặt xe cho khách hàng đã xác thực
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);

    Route::middleware('role:nhan_vien,admin')->group(function () {
        Route::get('/admin/profiles', [AdminProfileController::class, 'getProfiles']);
        Route::get('/admin/profiles/pending', [AdminProfileController::class, 'getPendingProfiles']);
        Route::post('/admin/profiles/update-status', [AdminProfileController::class, 'updateStatus']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/statistics', [AdminStatisticsController::class, 'index']);
    });
});
