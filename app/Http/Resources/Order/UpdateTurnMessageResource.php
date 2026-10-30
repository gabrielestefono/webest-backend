<?php

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UpdateTurnMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Mensagem atualizada com sucesso! A versão anterior permanece salva no histórico.',
            'data' => $this->resource,
        ];
    }
}

