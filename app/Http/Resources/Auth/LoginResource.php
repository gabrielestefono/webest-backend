<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Login realizado com sucesso!',
            'data' => [
                'token' => $this->resource['token'] ?? null,
                'username' => $this->resource['username'] ?? null,
                'user' => $this->resource['user'] ?? null,
            ],
        ];
    }
}

