<?php

namespace App\Actions\Auth;

use App\Dto\Auth\RegisterDto;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterAction
{
    /**
     * Handle user registration, role assignment, and token creation.
     *
     * @return array<string, mixed>
     */
    public function handle(RegisterDto $dto): array
    {
        $user = User::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => Hash::make($dto->password),
        ]);

        $user->assignRole('client');

        $token = $user->createToken('webest_token')->plainTextToken;

        return [
            'token' => $token,
            'username' => $user->name,
            'user' => $user,
        ];
    }
}

