<?php

namespace App\Actions\Auth;

use App\Dto\Auth\LoginDto;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginAction
{
    /**
     * Handle authentication and token creation.
     *
     * @return array<string, mixed>
     */
    public function handle(LoginDto $dto): array
    {
        $user = User::where('email', $dto->email)->first();

        if (! $user || ! Hash::check($dto->password, $user->password)) {
            return [
                'error' => 'Credenciais inválidas. Verifique seu e-mail e senha.',
            ];
        }

        $token = $user->createToken('webest_token')->plainTextToken;

        return [
            'token' => $token,
            'username' => $user->name,
            'user' => $user,
        ];
    }
}
