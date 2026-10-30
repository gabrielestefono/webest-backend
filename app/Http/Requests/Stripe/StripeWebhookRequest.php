<?php

namespace App\Http\Requests\Stripe;

use App\Dto\Stripe\StripeWebhookDto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StripeWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    public function toDto(): StripeWebhookDto
    {
        $raw = (string) $this->getContent();
        $decoded = json_decode($raw, true);

        return new StripeWebhookDto(
            rawPayload: $raw,
            signatureHeader: $this->header('Stripe-Signature'),
            decodedPayload: is_array($decoded) ? $decoded : [],
        );
    }
}

