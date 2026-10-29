<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VerifyEmailResource extends JsonResource
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
            'message' => $this->resource['already_verified'] ?? false
                ? 'Este e-mail já havia sido verificado anteriormente.'
                : 'E-mail verificado com sucesso!',
            'data' => [
                'verified' => $this->resource['verified'] ?? true,
                'already_verified' => $this->resource['already_verified'] ?? false,
            ],
        ];
    }
}

