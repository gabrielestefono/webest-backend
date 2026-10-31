<?php

namespace App\Actions\Stripe;

use App\Dto\Stripe\StripeWebhookDto;
use App\Exceptions\UnauthorizedException;
use App\Models\Order;
use App\Models\Project;
use App\Services\OrderTimelineService;
use App\Services\StripeCheckoutService;
use Illuminate\Support\Facades\DB;

class HandleStripeWebhookAction
{
    public function __construct(
        private readonly StripeCheckoutService $stripeService,
        private readonly OrderTimelineService $timelineService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(StripeWebhookDto $dto): array
    {
        if (! $this->stripeService->verifyWebhookSignature($dto->rawPayload, $dto->signatureHeader)) {
            throw new UnauthorizedException('Assinatura do webhook Stripe inválida.');
        }

        $eventType = isset($dto->decodedPayload['type']) && is_string($dto->decodedPayload['type'])
            ? $dto->decodedPayload['type']
            : '';

        /** @var array<string, mixed> $object */
        $object = isset($dto->decodedPayload['data']['object']) && is_array($dto->decodedPayload['data']['object'])
            ? $dto->decodedPayload['data']['object']
            : [];

        if (! in_array($eventType, ['checkout.session.completed', 'invoice.paid'], true)) {
            return ['handled' => false, 'event' => $eventType];
        }

        /** @var array<string, mixed> $metadata */
        $metadata = isset($object['metadata']) && is_array($object['metadata'])
            ? $object['metadata']
            : [];

        $orderId = isset($metadata['order_id']) && is_numeric($metadata['order_id'])
            ? (int) $metadata['order_id']
            : 0;

        if ($orderId <= 0) {
            return ['handled' => false, 'reason' => 'missing_order_id'];
        }

        $order = Order::query()->find($orderId);
        if (! $order) {
            return ['handled' => false, 'reason' => 'order_not_found'];
        }

        // Main order payment -> awaiting_development
        DB::transaction(function () use ($order): void {
            $project = Project::query()->firstOrCreate(
                ['order_id' => $order->id],
                [
                    'payment_link' => $order->stripe_checkout_url ?? '#',
                    'payment_status' => 'pending',
                    'current_progress' => 0,
                ]
            );

            $project->update([
                'payment_status' => 'paid',
            ]);

            $order->update([
                'status' => 'awaiting_development',
            ]);

            $days = $order->estimated_days ?? 7;

            $this->timelineService->recordEvent(
                order: $order,
                eventType: 'payment_confirmed',
                title: 'Pagamento Confirmado via Stripe!',
                message: "O cliente pagou pelo pedido! A entrega estimada do projeto é de {$days} dias úteis após o início técnico. Status atual: Aguardando Desenvolvimento.",
                payload: [
                    'estimated_days' => $days,
                ],
            );
        });

        return ['handled' => true, 'purpose' => 'order_main'];
    }
}
