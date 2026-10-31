<?php

namespace App\Actions\Order;

use App\Dto\Order\StoreAdjustmentDto;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Policies\OrderPolicy;
use App\Services\OrderTimelineService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StoreAdjustmentAction
{
    public function __construct(
        private readonly OrderPolicy $policy,
        private readonly OrderTimelineService $timelineService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(StoreAdjustmentDto $dto): array
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
            throw new ForbiddenException('Apenas o titular do pedido pode solicitar ajustes.');
        }

        $isInWarranty = $order->status === 'done'
            && $order->warranty_until !== null
            && Carbon::parse($order->warranty_until)->isFuture();

        if ($order->status === 'done' && ! $isInWarranty) {
            throw new BadRequestException('O prazo contratual de garantia de 30 dias já foi encerrado.');
        }

        if ($order->status !== 'homologation' && ! $isInWarranty) {
            throw new BadRequestException('Ajustes só podem ser solicitados durante a fase de homologação ou vigência da garantia.');
        }

        $hasOpenAdjustment = $order->adjustments()
            ->where('decision', 'pending')
            ->exists();

        if ($hasOpenAdjustment) {
            throw new BadRequestException('Já existe uma solicitação de ajuste em triagem contratual neste pedido.');
        }

        DB::transaction(function () use ($dto, $order, $user): void {
            $adjustment = OrderAdjustment::query()->create([
                'order_id' => $order->id,
                'requester_id' => $user->id,
                'client_description' => trim($dto->description),
                'decision' => 'pending',
                'payment_status' => 'not_applicable',
            ]);

            $order->update([
                'status' => 'adjustment_review',
                'client_completed_at' => null,
            ]);

            $this->timelineService->recordEvent(
                order: $order,
                eventType: 'adjustment_requested',
                title: 'Solicitação de Ajuste em Triagem Contratual',
                message: 'O cliente pediu revisão no projeto. A solicitação está sob triagem do prestador para verificar aderência ao escopo contratado.',
                payload: [
                    'adjustment_id' => $adjustment->id,
                ],
                actorId: $user->id,
            );
        });

        return $this->timelineService->serializeOrderRoom($order->fresh() ?? $order);
    }
}
