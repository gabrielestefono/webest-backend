<?php

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Pedido enviado com sucesso! Agora ele está aguardando revisão da nossa equipe.',
            'data' => $this->resource,
        ];
    }
}

