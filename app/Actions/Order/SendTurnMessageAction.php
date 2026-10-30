<?php

namespace App\Actions\Order;

use App\Dto\Order\SendTurnMessageDto;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Models\Order;
use App\Policies\OrderPolicy;
use App\Services\OrderTimelineService;

class SendTurnMessageAction
{
    public function __construct(
        private readonly OrderPolicy $policy,
        private readonly OrderTimelineService $timelineService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(SendTurnMessageDto $dto): array
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
            throw new ForbiddenException('Apenas o titular do pedido pode enviar mensagens como cliente.');
        }

        $this->timelineService->sendTurnMessage(
            order: $order,
            sender: $user,
            senderRole: 'client',
            content: $dto->content,
        );

        return $this->timelineService->serializeOrderRoom($order->fresh() ?? $order);
    }
}

