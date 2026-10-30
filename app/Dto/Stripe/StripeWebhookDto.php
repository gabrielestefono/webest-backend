<?php

namespace App\Dto\Stripe;

readonly class StripeWebhookDto
{
    /**
     * @param  array<string, mixed>  $decodedPayload
     */
    public function __construct(
        public string $rawPayload,
        public ?string $signatureHeader,
        public array $decodedPayload,
    ) {}
}
