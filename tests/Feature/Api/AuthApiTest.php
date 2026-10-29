<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('login requires email and password', function () {
    $response = $this->postJson('/api/auth/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

test('login fails with invalid credentials', function () {
    User::factory()->create([
        'email' => 'auth-test@webest.com',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'auth-test@webest.com',
        'password' => 'wrong-pass',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Credenciais inválidas. Verifique seu e-mail e senha.',
        ]);
});

test('login succeeds and generates sanctum token', function () {
    $user = User::factory()->create([
        'email' => 'auth-success@webest.com',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'auth-success@webest.com',
        'password' => 'secret123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'token',
            'username',
            'user' => ['id', 'email', 'name'],
        ]);
});

test('check token validates and returns user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->postJson('/api/auth/check-token', [
        'token' => $token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('email', $user->email);
});

test('check token fails for invalid token', function () {
    $response = $this->postJson('/api/auth/check-token', [
        'token' => 'invalid-token-string',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Token inválido ou expirado.',
        ]);
});

test('logout revokes the token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->postJson('/api/auth/logout', [
        'token' => $token,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    expect($user->tokens()->count())->toBe(0);
});
