<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SendConfirmationEmailResource extends JsonResource
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
                ? 'Este e-mail já foi verificado anteriormente.'
                : 'E-mail de verificação enviado com sucesso!',
            'data' => [
                'sent' => $this->resource['sent'] ?? false,
                'already_verified' => $this->resource['already_verified'] ?? false,
            ],
        ];
    }
}

