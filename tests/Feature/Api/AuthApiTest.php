<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('login requires email and password', function () {
    $response = $this->postJson('/api/auth/login', []);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'data' => null,
            'message' => 'O campo e-mail é obrigatório.',
        ])
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
            'success' => false,
            'data' => null,
            'message' => 'Credenciais inválidas. Verifique seu e-mail e senha.',
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
        ->assertJson([
            'success' => true,
            'message' => 'Login realizado com sucesso!',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'token',
                'username',
                'user' => ['id', 'email', 'name'],
            ],
        ]);
});

test('check token validates and returns user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->postJson('/api/auth/check-token', [
        'token' => $token,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Token válido.',
        ])
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});

test('check token fails for invalid token', function () {
    $response = $this->postJson('/api/auth/check-token', [
        'token' => 'invalid-token-string',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'data' => null,
            'message' => 'Token inválido ou expirado.',
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
            'message' => 'Logout realizado com sucesso!',
            'data' => null,
        ]);

    expect($user->tokens()->count())->toBe(0);
});
