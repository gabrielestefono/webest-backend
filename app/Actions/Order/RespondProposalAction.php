<?php

namespace App\Actions\Order;

use App\Dto\Order\RespondProposalDto;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Models\Order;
use App\Models\Proposal;
use App\Policies\OrderPolicy;
use App\Services\OrderTimelineService;
use Illuminate\Support\Facades\DB;

class RespondProposalAction
{
    public function __construct(
        private readonly OrderPolicy $policy,
        private readonly OrderTimelineService $timelineService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(RespondProposalDto $dto): array
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
            throw new ForbiddenException('Apenas o titular do pedido pode responder propostas.');
        }

        if ($dto->decision === 'cancel') {
            $cancellableStatuses = [
                'pending',
                'under_review',
                'proposal_sent',
                'proposal_negotiation',
                'approved',
                'awaiting_contract',
                'awaiting_payment',
            ];

            if (! in_array($order->status, $cancellableStatuses, true)) {
                throw new BadRequestException('O pedido não pode ser cancelado nesta etapa após confirmação de pagamento.');
            }

            DB::transaction(function () use ($order, $user): void {
                $order->proposals()
                    ->whereNull('is_accepted')
                    ->update([
                        'is_accepted' => false,
                        'status' => 'cancelled',
                    ]);

                $order->update(['status' => 'cancelled']);

                $this->timelineService->recordEvent(
                    order: $order,
                    eventType: 'order_cancelled',
                    title: 'Pedido Cancelado pelo Cliente',
                    message: 'O cliente optou por encerrar a negociação e cancelar o pedido.',
                    actorId: $user->id,
                );
            });

            return $this->timelineService->serializeOrderRoom($order->fresh() ?? $order);
        }

        $latestProposal = $order->proposals()->orderByDesc('id')->first();
        if (
            ! $latestProposal instanceof Proposal
            || $latestProposal->is_accepted !== null
            || $latestProposal->status !== 'pending'
            || $latestProposal->sender_id === $user->id
        ) {
            throw new BadRequestException('Não há uma proposta ativa do prestador aguardando sua decisão no momento.');
        }

        if ($dto->decision === 'accept' && ! $order->hasCompleteScope()) {
            throw new BadRequestException('O Escopo do Serviço deste pedido ainda não foi finalizado pelo prestador.');
        }

        DB::transaction(function () use ($dto, $order, $user, $latestProposal): void {
            if ($dto->decision === 'accept') {
                $order->proposals()
                    ->where('is_accepted', true)
                    ->whereNull('superseded_at')
                    ->whereKeyNot($latestProposal->id)
                    ->update(['superseded_at' => now()]);

                $latestProposal->update([
                    'is_accepted' => true,
                    'status' => 'accepted',
                    'superseded_at' => null,
                ]);

                $order->update([
                    'status' => 'awaiting_contract',
                    'final_price' => $latestProposal->price,
                    'estimated_days' => $latestProposal->estimated_days,
                    'contract_url' => null,
                ]);

                $formattedPrice = number_format((float) $latestProposal->price, 2, ',', '.');

                $this->timelineService->recordEvent(
                    order: $order,
                    eventType: 'contract_available',
                    title: 'Proposta Aceita! Escopo & Contrato Consolidado Disponíveis',
                    message: "Você aceitou a proposta comercial de R$ {$formattedPrice} ({$latestProposal->estimated_days} dias úteis). O PDF consolidado com o Escopo do Serviço e todo o histórico da negociação está pronto para download e assinatura digital.",
                    payload: [
                        'proposal_id' => $latestProposal->id,
                        'price' => (float) $latestProposal->price,
                        'estimated_days' => $latestProposal->estimated_days,
                    ],
                    actorId: $user->id,
                );

                return;
            }

            // Counter-proposal ping-pong
            $counterPrice = (float) ($dto->counterPrice ?? 0);
            $counterDays = (int) ($dto->counterDays ?? $latestProposal->estimated_days);
            $counterContent = $dto->counterContent ?? '';

            $latestProposal->update([
                'is_accepted' => false,
                'status' => 'countered',
            ]);

            $newProposal = Proposal::query()->create([
                'order_id' => $order->id,
                'sender_id' => $user->id,
                'content' => $counterContent,
                'price' => $counterPrice,
                'estimated_days' => $counterDays,
                'is_accepted' => null,
                'status' => 'pending',
            ]);

            $order->update([
                'status' => 'proposal_negotiation',
            ]);

            $formattedPrice = number_format($counterPrice, 2, ',', '.');

            $this->timelineService->recordEvent(
                order: $order,
                eventType: 'counter_proposal_sent',
                title: 'Contra-proposta do Cliente Enviada',
                message: "O cliente fez uma contra-proposta de R$ {$formattedPrice} com prazo sugerido de {$counterDays} dias úteis.",
                payload: [
                    'proposal_id' => $newProposal->id,
                    'price' => $counterPrice,
                    'estimated_days' => $counterDays,
                ],
                actorId: $user->id,
            );
        });

        return $this->timelineService->serializeOrderRoom($order->fresh() ?? $order);
    }
}

