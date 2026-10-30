<?php

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SendTurnMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Mensagem enviada! Você poderá editá-la enquanto o prestador não responder.',
            'data' => $this->resource,
        ];
    }
}

