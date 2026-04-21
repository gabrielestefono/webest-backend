<?php

use function Pest\Laravel\get;

test('filament login is available under dashboard path', function () {
    $response = get('/dashboard/login');

    $response->assertOk();
});

test('legacy admin path is not available', function () {
    $response = get('/admin/login');

    $response->assertNotFound();
});
