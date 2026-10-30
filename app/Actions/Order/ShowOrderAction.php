<?php

namespace App\Actions\Order;

use App\Dto\Order\ShowOrderDto;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Models\Order;
use App\Policies\OrderPolicy;
use App\Services\OrderTimelineService;

class ShowOrderAction
{
    public function __construct(
        private readonly OrderPolicy $policy,
        private readonly OrderTimelineService $timelineService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(ShowOrderDto $dto): array
    {
        $user = $dto->user;
        if (! $user) {
            throw new UnauthorizedException('Usuário não autenticado.');
        }

        $order = Order::query()->find($dto->orderId);
        if (! $order) {
            throw new NotFoundException('Pedido não encontrado.');
        }

        if (! $this->policy->view($user, $order)) {
            throw new ForbiddenException('Você não tem permissão para visualizar este pedido.');
        }

        return $this->timelineService->serializeOrderRoom($order);
    }
}

