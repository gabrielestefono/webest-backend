<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/user', [AuthController::class, 'checkToken'])->middleware('auth:sanctum');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth:sanctum');
Route::get('/products', [ProductController::class, 'index'])->middleware('auth:sanctum');

Route::prefix('orders')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);
    Route::get('/{order}', [OrderController::class, 'show']);
    Route::get('/{order}/attachments/{attachment}/download', [OrderController::class, 'downloadAttachment']);
    Route::post('/{order}/messages', [OrderController::class, 'sendMessage']);
    Route::put('/{order}/messages/{message}', [OrderController::class, 'updateMessage']);
    Route::post('/{order}/proposals/respond', [OrderController::class, 'respondProposal']);
    Route::post('/{order}/contract/sign', [OrderController::class, 'signContract']);
    Route::post('/{order}/adjustments', [OrderController::class, 'storeAdjustment']);
    Route::post('/{order}/adjustments/{adjustment}/respond', [OrderController::class, 'respondAddendum']);
    Route::post('/{order}/complete', [OrderController::class, 'completeOrder']);
});

Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/check-token', [AuthController::class, 'checkToken']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/email-confirmation', [AuthController::class, 'sendConfirmationEmail'])->middleware('auth:sanctum');
    Route::get('/email-validation', [AuthController::class, 'verifyEmail']);
});
