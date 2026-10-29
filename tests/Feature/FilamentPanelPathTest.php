<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('filament login is available under admin path', function () {
    $response = get('/admin/login');

    $response->assertOk();
});

test('legacy dashboard path redirects to admin path', function () {
    $response = get('/dashboard/login');

    $response->assertRedirect('/admin/login');
});

test('authenticated user can access filament admin', function () {
    $user = User::factory()->create();

    actingAs($user);

    $response = get('/admin');

    $response->assertOk();
});
