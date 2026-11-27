<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserController;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/change-password', [ProfileController::class, 'changePassword']);

    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update']);
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);
    Route::post('/expenses/{expense}/restore', [ExpenseController::class, 'restore']);

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);

    Route::get('/logs', function () {
        if (! auth()->user()->isAdmin()) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        return response()->json(AuditLog::authCompany()->latest()->paginate(20), 200);
    });
});

Route::middleware(['throttle:3,1'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware(['throttle:5,1'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['throttle:10,1'])->group(function () {
    Route::post('/verify-email', [EmailVerificationController::class, 'verify'])->name('verify-email');
    Route::post('/resend-verification-email', [EmailVerificationController::class, 'resend'])->name('resend-verification');
});

Route::middleware(['throttle:5,1'])->group(function () {
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
});
