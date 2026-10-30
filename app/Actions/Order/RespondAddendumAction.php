<?php

namespace App\Actions\Order;

use App\Dto\Order\RespondAddendumDto;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Policies\OrderPolicy;
use App\Services\OrderTimelineService;
use App\Services\StripeCheckoutService;
use Illuminate\Support\Facades\DB;

class RespondAddendumAction
{
    public function __construct(
        private readonly OrderPolicy $policy,
        private readonly StripeCheckoutService $stripeService,
        private readonly OrderTimelineService $timelineService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(RespondAddendumDto $dto): array
    {
        $user = $dto->user;
        if (! $user) {
            throw new UnauthorizedException('Usuário não autenticado.');
        }

        $order = Order::query()->find($dto->orderId);
        if (! $order) {
            throw new NotFoundException('Pedido não encontrado.');
        }

        if (! $this->policy->interactAsClient($user, $order)) {
            throw new ForbiddenException('Apenas o titular do pedido pode responder ao Termo Aditivo.');
        }

        $adjustment = OrderAdjustment::query()
            ->where('order_id', $order->id)
            ->whereKey($dto->adjustmentId)
            ->first();

        if (! $adjustment) {
            throw new NotFoundException('Termo Aditivo não encontrado.');
        }

        if ($adjustment->decision !== 'addendum_issued') {
            throw new BadRequestException('Este Termo Aditivo não está aguardando resposta do cliente no momento.');
        }

        if ($dto->decision === 'accept_and_pay') {
            $price = (float) ($adjustment->addendum_price ?? 0);
            if ($price <= 0) {
                throw new BadRequestException('Valor do Termo Aditivo inválido.');
            }

            if ($adjustment->stripe_payment_link === null || trim($adjustment->stripe_payment_link) === '') {
                $checkout = $this->stripeService->createAddendumCheckout($order, $adjustment, $price);
                $adjustment->update([
                    'stripe_payment_link' => $checkout['url'],
                    'stripe_session_id' => $checkout['session_id'],
                    'payment_status' => 'pending',
                ]);
            }

            return $this->timelineService->serializeOrderRoom($order->fresh() ?? $order);
        }

        if ($dto->decision === 'counter') {
            $counterPrice = (float) ($dto->counterPrice ?? 0);
            $formatted = number_format($counterPrice, 2, ',', '.');

            DB::transaction(function () use ($adjustment, $order, $user, $counterPrice, $dto, $formatted): void {
                $adjustment->update([
                    'decision' => 'addendum_countered',
                    'counter_price' => $counterPrice,
                    'counter_notes' => $dto->counterNotes,
                ]);

                $this->timelineService->recordEvent(
                    order: $order,
                    eventType: 'addendum_counter_proposed',
                    title: 'Contra-proposta no Termo Aditivo',
                    message: "O cliente enviou uma contra-proposta de R$ {$formatted} para o {$adjustment->contract_reference}.",
                    payload: [
                        'adjustment_id' => $adjustment->id,
                        'counter_price' => $counterPrice,
                    ],
                    actorId: $user->id,
                );
            });

            return $this->timelineService->serializeOrderRoom($order->fresh() ?? $order);
        }

        // Decline addendum & preserve original scope
        DB::transaction(function () use ($adjustment, $order, $user): void {
            $adjustment->update([
                'decision' => 'addendum_rejected',
                'payment_status' => 'cancelled',
                'resolved_at' => now(),
            ]);

            $order->update([
                'status' => 'homologation',
            ]);

            $this->timelineService->recordEvent(
                order: $order,
                eventType: 'addendum_declined',
                title: 'Termo Aditivo Recusado — Escopo Original Mantido',
                message: 'O cliente optou por recusar o Termo Aditivo sem custos extras. O projeto permanece em homologação respeitando o escopo original contratado.',
                payload: [
                    'adjustment_id' => $adjustment->id,
                ],
                actorId: $user->id,
            );
        });

        return $this->timelineService->serializeOrderRoom($order->fresh() ?? $order);
    }
}

