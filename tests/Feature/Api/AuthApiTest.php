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

test('register requires name, email, and password', function () {
    $response = $this->postJson('/api/auth/register', []);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'data' => null,
            'message' => 'O campo nome é obrigatório.',
        ])
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

test('register validates unique email and password confirmation', function () {
    User::factory()->create(['email' => 'existing@webest.com']);

    $response = $this->postJson('/api/auth/register', [
        'name' => 'Novo Usuário',
        'email' => 'existing@webest.com',
        'password' => 'secret123',
        'password_confirmation' => 'mismatch123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

test('register creates user with client role and returns sanctum token', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Cliente Teste',
        'email' => 'cliente@webest.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Cadastro realizado com sucesso!',
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

    $user = User::where('email', 'cliente@webest.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole('client'))->toBeTrue()
        ->and($user->tokens()->count())->toBe(1);
});

test('send confirmation email dispatches notification', function () {
    Illuminate\Support\Facades\Notification::fake();

    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/auth/email-confirmation');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'E-mail de verificação enviado com sucesso!',
            'data' => [
                'sent' => true,
                'already_verified' => false,
            ],
        ]);

    Illuminate\Support\Facades\Notification::assertSentTo(
        $user,
        Illuminate\Auth\Notifications\VerifyEmail::class
    );
});

test('verify email successfully marks email as verified', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $expires = time() + 3600;
    $id = $user->id;
    $hash = sha1($user->getEmailForVerification());
    $key = (string) config('app.key');
    $signature = hash_hmac('sha256', "id={$id}&hash={$hash}&expires={$expires}", $key);

    $response = $this->getJson("/api/auth/email-validation?id={$id}&hash={$hash}&expires={$expires}&signature={$signature}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'E-mail verificado com sucesso!',
            'data' => [
                'verified' => true,
                'already_verified' => false,
            ],
        ]);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('verify email fails with invalid signature', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $expires = time() + 3600;
    $id = $user->id;
    $hash = sha1($user->getEmailForVerification());

    $response = $this->getJson("/api/auth/email-validation?id={$id}&hash={$hash}&expires={$expires}&signature=invalid_signature");

    $response->assertStatus(400);
});
