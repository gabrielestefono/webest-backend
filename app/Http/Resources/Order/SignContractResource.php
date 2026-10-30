<?php

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SignContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Contrato assinado com sucesso! O link de pagamento via Stripe foi liberado.',
            'data' => $this->resource,
        ];
    }
}

