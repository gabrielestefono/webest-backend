<?php

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RespondAddendumResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Sua decisão sobre o Termo Aditivo foi processada com sucesso.',
            'data' => $this->resource,
        ];
    }
}

