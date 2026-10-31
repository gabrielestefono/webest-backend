<?php

use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::redirect('/dashboard/login', '/admin/login');
Route::get('/dashboard', function () {
    return redirect((string) (config('app.frontend_url') ?: 'http://localhost:3000').'/dashboard');
});

Route::get('/admin/orders/{order}/attachments/{attachment}/download', [OrderController::class, 'downloadAttachment'])
    ->middleware('auth')
    ->name('filament.orders.attachments.download');

Route::get('/admin/orders/{order}/contract/pdf', [OrderController::class, 'downloadContractPdf'])
    ->middleware('auth')
    ->name('filament.orders.contract.pdf');
