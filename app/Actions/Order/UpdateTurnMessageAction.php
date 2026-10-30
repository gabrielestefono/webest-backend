<?php

namespace App\Actions\Order;

use App\Dto\Order\UpdateTurnMessageDto;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Policies\OrderPolicy;
use App\Services\OrderTimelineService;

class UpdateTurnMessageAction
{
    public function __construct(
        private readonly OrderPolicy $policy,
        private readonly OrderTimelineService $timelineService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(UpdateTurnMessageDto $dto): array
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
            throw new ForbiddenException('Apenas o titular do pedido pode editar esta mensagem.');
        }

        $message = OrderMessage::query()->find($dto->messageId);
        if (! $message) {
            throw new NotFoundException('Mensagem não encontrada.');
        }

        $this->timelineService->updateTurnMessage(
            order: $order,
            message: $message,
            editor: $user,
            expectedRole: 'client',
            newContent: $dto->content,
        );

        return $this->timelineService->serializeOrderRoom($order->fresh() ?? $order);
    }
}

