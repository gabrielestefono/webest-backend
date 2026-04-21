<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('filament login is available under dashboard path', function () {
    $response = get('/dashboard/login');

    $response->assertOk();
});

test('legacy admin path is not available', function () {
    $response = get('/admin/login');

    $response->assertNotFound();
});

test('authenticated user can access filament dashboard', function () {
    $user = User::factory()->create();

    actingAs($user);

    $response = get('/dashboard');

    $response->assertOk();
});
