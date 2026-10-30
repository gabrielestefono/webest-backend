<?php

namespace App\Actions\Order;

use App\Dto\Order\CompleteOrderDto;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Models\Order;
use App\Policies\OrderPolicy;
use App\Services\OrderTimelineService;
use Illuminate\Support\Facades\DB;

class CompleteOrderAction
{
    public function __construct(
        private readonly OrderPolicy $policy,
        private readonly OrderTimelineService $timelineService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CompleteOrderDto $dto): array
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
            throw new ForbiddenException('Apenas o titular do pedido pode aprovar e finalizar como cliente.');
        }

        if (! in_array($order->status, ['homologation', 'in_progress'], true) && $order->client_completed_at === null) {
            throw new BadRequestException('O pedido precisa estar liberado em homologação antes de ser finalizado.');
        }

        if ($order->client_completed_at !== null) {
            throw new BadRequestException('Você já confirmou a finalização deste projeto.');
        }

        DB::transaction(function () use ($order, $user): void {
            $order->update([
                'client_completed_at' => now(),
            ]);

            $this->timelineService->recordEvent(
                order: $order,
                eventType: 'client_marked_done',
                title: 'Cliente Aprovou a Entrega',
                message: 'O cliente definiu o projeto como finalizado. Aguardando confirmação final do prestador para liberação definitiva e ativação da garantia.',
                actorId: $user->id,
            );

            $this->timelineService->tryFinalizeOrderIfBothConfirmed($order, $user->id);
        });

        return $this->timelineService->serializeOrderRoom($order->fresh() ?? $order);
    }
}

