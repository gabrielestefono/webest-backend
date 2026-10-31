<?php

namespace App\Actions\Order;

use App\Dto\Order\SignContractDto;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Models\Order;
use App\Models\Project;
use App\Policies\OrderPolicy;
use App\Services\OrderTimelineService;
use App\Services\StripeCheckoutService;
use Illuminate\Support\Facades\DB;

class SignContractAction
{
    public function __construct(
        private readonly OrderPolicy $policy,
        private readonly StripeCheckoutService $stripeService,
        private readonly OrderTimelineService $timelineService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(SignContractDto $dto): array
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
            throw new ForbiddenException('Apenas o titular do pedido pode assinar o contrato.');
        }

        if (! in_array($order->status, ['awaiting_contract', 'approved'], true)) {
            throw new BadRequestException('Este pedido não está aguardando assinatura contratual no momento.');
        }

        if (! $order->hasCompleteScope()) {
            throw new BadRequestException('O Escopo do Serviço precisa estar cadastrado antes da assinatura digital.');
        }

        $amount = (float) ($order->final_price ?? 0);
        if ($amount <= 0) {
            $agreed = $order->proposals()->where('is_accepted', true)->latest('id')->first();
            $amount = $agreed !== null ? (float) $agreed->price : 0.0;
        }

        if ($amount <= 0) {
            throw new BadRequestException('Valor acordado inválido para emissão da cobrança.');
        }

        $checkout = $this->stripeService->createOrderCheckout($order, $amount);

        DB::transaction(function () use ($order, $user, $amount, $checkout): void {
            $signedAt = now();

            $order->update([
                'final_price' => $amount,
                'contract_signed_at' => $signedAt,
                'stripe_checkout_url' => $checkout['url'],
                'stripe_session_id' => $checkout['session_id'],
                'status' => 'awaiting_payment',
            ]);

            $order->scope()
                ->whereNull('locked_at')
                ->update(['locked_at' => $signedAt]);

            Project::query()->updateOrCreate(
                ['order_id' => $order->id],
                [
                    'payment_link' => $checkout['url'],
                    'payment_status' => 'pending',
                    'current_progress' => 0,
                ]
            );

            $formattedPrice = number_format($amount, 2, ',', '.');

            $this->timelineService->recordEvent(
                order: $order,
                eventType: 'payment_link_ready',
                title: 'Contrato Assinado & Link de Pagamento Disponível',
                message: "O cliente aceitou e assinou digitalmente o contrato! Segue o link seguro do Stripe para o pagamento de R$ {$formattedPrice}.",
                payload: [
                    'payment_url' => $checkout['url'],
                    'amount' => $amount,
                ],
                actorId: $user->id,
            );
        });

        return $this->timelineService->serializeOrderRoom($order->fresh() ?? $order);
    }
}
