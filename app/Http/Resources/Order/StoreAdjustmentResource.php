<?php

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreAdjustmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Solicitação de ajuste registrada! Nossa equipe avaliará o escopo contratual em breve.',
            'data' => $this->resource,
        ];
    }
}

