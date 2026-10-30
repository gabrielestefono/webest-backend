<?php

namespace App\Http\Resources\Stripe;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StripeWebhookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Webhook processado.',
            'data' => $this->resource,
        ];
    }
}

