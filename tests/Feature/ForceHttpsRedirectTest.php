<?php

use function Pest\Laravel\get;

test('http requests are redirected to https when force https is enabled', function () {
    config()->set('app.force_https', true);

    $response = get('http://localhost/');

    $response
        ->assertStatus(301)
        ->assertRedirect('https://localhost');
});

test('https requests are not redirected when force https is enabled', function () {
    config()->set('app.force_https', true);

    $response = get('https://localhost/');

    $response->assertOk();
});
