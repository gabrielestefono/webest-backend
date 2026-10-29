<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::redirect('/dashboard/login', '/admin/login');
Route::get('/dashboard', function () {
    return redirect((string) (config('app.frontend_url') ?: 'http://localhost:3000') . '/dashboard');
});
