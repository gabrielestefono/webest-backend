<?php

namespace App\Services;

use App\Exceptions\InternalServerErrorException;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Http;

class StripeCheckoutService
{
    /**
     * Create a Stripe Checkout session for the main order payment (one-time or recurring).
     *
     * @return array{url: string, session_id: string}
     */
    public function createOrderCheckout(Order $order, float $amount): array
    {
        $secret = $this->stripeSecret();
        $frontendUrl = $this->frontendUrl();
        $successUrl = "{$frontendUrl}/dashboard/pedidos/{$order->id}?payment=success";
        $cancelUrl = "{$frontendUrl}/dashboard/pedidos/{$order->id}?payment=cancelled";

        if ($secret === null) {
            return [
                'url' => "{$frontendUrl}/dashboard/pedidos/{$order->id}?stripe_unconfigured=1",
                'session_id' => 'cs_local_ord_'.$order->id.'_'.uniqid(),
            ];
        }

        $unitAmount = (int) round($amount * 100);
        $recurringProduct = $order->products()
            ->where('billing_type', 'recurring')
            ->first();

        $isSubscription = $recurringProduct instanceof Product
            && $order->products()->where('billing_type', 'one_time')->count() === 0;

        /** @var array<string, mixed> $payload */
        $payload = [
            'mode' => $isSubscription ? 'subscription' : 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $order->id,
            'metadata' => [
                'order_id' => (string) $order->id,
                'payment_purpose' => 'order_main',
            ],
            'line_items' => [
                [
                    'quantity' => 1,
                    'price_data' => array_filter([
                        'currency' => 'brl',
                        'unit_amount' => $unitAmount,
                        'product_data' => [
                            'name' => "Pedido #{$order->id} - {$order->title}",
                        ],
                        'recurring' => $isSubscription && $recurringProduct instanceof Product
                            ? ['interval' => $recurringProduct->billing_interval === 'year' ? 'year' : 'month']
                            : null,
                    ]),
                ],
            ],
        ];

        return $this->dispatchSessionRequest($secret, $payload);
    }

    public function verifyWebhookSignature(string $rawPayload, ?string $signatureHeader): bool
    {
        $webhookSecret = config('services.stripe.webhook_secret');
        if (! is_string($webhookSecret) || trim($webhookSecret) === '') {
            return false;
        }

        if ($signatureHeader === null || trim($signatureHeader) === '') {
            return false;
        }

        $parts = explode(',', $signatureHeader);
        $timestamp = null;
        /** @var list<string> $signatures */
        $signatures = [];

        foreach ($parts as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) {
                continue;
            }
            if ($kv[0] === 't') {
                $timestamp = $kv[1];
            } elseif ($kv[0] === 'v1') {
                $signatures[] = $kv[1];
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $webhookSecret);

        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{url: string, session_id: string}
     */
    private function dispatchSessionRequest(string $secret, array $payload): array
    {
        $response = Http::asForm()
            ->withToken($secret)
            ->post('https://api.stripe.com/v1/checkout/sessions', $payload);

        if (! $response->successful()) {
            throw new InternalServerErrorException('Não foi possível iniciar a sessão de pagamento no Stripe.');
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];
        $url = isset($data['url']) && is_string($data['url']) ? $data['url'] : '';
        $id = isset($data['id']) && is_string($data['id']) ? $data['id'] : '';

        if ($url === '' || $id === '') {
            throw new InternalServerErrorException('Resposta inesperada do gateway Stripe.');
        }

        return [
            'url' => $url,
            'session_id' => $id,
        ];
    }

    private function stripeSecret(): ?string
    {
        $secret = config('services.stripe.secret');

        return is_string($secret) && trim($secret) !== '' ? trim($secret) : null;
    }

    private function frontendUrl(): string
    {
        $url = config('services.stripe.frontend_url');

        return is_string($url) && trim($url) !== '' ? rtrim(trim($url), '/') : 'http://localhost:3000';
    }
}
