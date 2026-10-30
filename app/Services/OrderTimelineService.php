<?php

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenException;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderMessage;
use App\Models\OrderMessageVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderTimelineService
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function recordEvent(
        Order $order,
        string $eventType,
        string $title,
        string $message,
        ?array $payload = null,
        ?int $actorId = null,
    ): OrderEvent {
        return OrderEvent::query()->create([
            'order_id' => $order->id,
            'actor_id' => $actorId,
            'event_type' => $eventType,
            'title' => $title,
            'message' => $message,
            'payload' => $payload,
        ]);
    }

    public function sendTurnMessage(
        Order $order,
        User $sender,
        string $senderRole,
        string $content,
    ): OrderMessage {
        $trimmed = trim($content);
        if ($trimmed === '') {
            throw new BadRequestException('O conteúdo da mensagem não pode estar vazio.');
        }

        if (in_array($order->status, ['cancelled', 'rejected'], true)) {
            throw new BadRequestException('Este pedido foi encerrado e não aceita novas mensagens.');
        }

        return DB::transaction(function () use ($order, $sender, $senderRole, $trimmed): OrderMessage {
            $latestHumanMessage = OrderMessage::query()
                ->where('order_id', $order->id)
                ->whereIn('sender_role', ['client', 'provider'])
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($latestHumanMessage instanceof OrderMessage && $latestHumanMessage->sender_role === $senderRole && ! $latestHumanMessage->is_locked) {
                throw new BadRequestException(
                    'Você já possui uma mensagem ativa neste turno aguardando resposta da outra parte. Edite sua mensagem atual caso deseje alterar algo.'
                );
            }

            $oppositeRole = $senderRole === 'client' ? 'provider' : 'client';

            OrderMessage::query()
                ->where('order_id', $order->id)
                ->where('sender_role', $oppositeRole)
                ->where('is_locked', false)
                ->update(['is_locked' => true]);

            $maxTurn = (int) OrderMessage::query()
                ->where('order_id', $order->id)
                ->max('turn_number');

            $message = OrderMessage::query()->create([
                'order_id' => $order->id,
                'sender_id' => $sender->id,
                'sender_role' => $senderRole,
                'turn_number' => $maxTurn + 1,
                'current_content' => $trimmed,
                'is_locked' => false,
                'edited_at' => null,
            ]);

            OrderMessageVersion::query()->create([
                'order_message_id' => $message->id,
                'version_number' => 1,
                'content' => $trimmed,
                'edited_by_id' => $sender->id,
                'created_at' => now(),
            ]);

            return $message->load(['sender', 'versions.editedBy']);
        });
    }

    public function updateTurnMessage(
        Order $order,
        OrderMessage $message,
        User $editor,
        string $expectedRole,
        string $newContent,
    ): OrderMessage {
        if ($message->order_id !== $order->id) {
            throw new ForbiddenException('Esta mensagem não pertence ao pedido informado.');
        }

        if ($message->sender_role !== $expectedRole) {
            throw new ForbiddenException('Você só pode editar mensagens enviadas pela sua parte.');
        }

        if ($expectedRole === 'client' && $message->sender_id !== $editor->id) {
            throw new ForbiddenException('Você não tem permissão para editar esta mensagem.');
        }

        $trimmed = trim($newContent);
        if ($trimmed === '') {
            throw new BadRequestException('O conteúdo da mensagem não pode estar vazio.');
        }

        return DB::transaction(function () use ($order, $message, $editor, $expectedRole, $trimmed): OrderMessage {
            $freshMessage = OrderMessage::query()
                ->whereKey($message->id)
                ->lockForUpdate()
                ->first();

            if (! $freshMessage instanceof OrderMessage) {
                throw new BadRequestException('Mensagem não localizada.');
            }

            $oppositeRole = $expectedRole === 'client' ? 'provider' : 'client';

            $hasReplyAfter = OrderMessage::query()
                ->where('order_id', $order->id)
                ->where('sender_role', $oppositeRole)
                ->where('id', '>', $freshMessage->id)
                ->exists();

            if ($freshMessage->is_locked || $hasReplyAfter) {
                if (! $freshMessage->is_locked) {
                    $freshMessage->update(['is_locked' => true]);
                }

                throw new BadRequestException(
                    'A outra parte já respondeu a este turno. A mensagem está congelada e não pode mais ser editada.'
                );
            }

            if ($freshMessage->current_content === $trimmed) {
                throw new BadRequestException('O texto informado é idêntico à versão atual da mensagem.');
            }

            $nextVersion = ((int) $freshMessage->versions()->max('version_number')) + 1;

            OrderMessageVersion::query()->create([
                'order_message_id' => $freshMessage->id,
                'version_number' => $nextVersion,
                'content' => $trimmed,
                'edited_by_id' => $editor->id,
                'created_at' => now(),
            ]);

            $freshMessage->update([
                'current_content' => $trimmed,
                'edited_at' => now(),
            ]);

            return $freshMessage->load(['sender', 'versions.editedBy']);
        });
    }

    public function tryFinalizeOrderIfBothConfirmed(Order $order, ?int $actorId = null): bool
    {
        $order->refresh();

        if ($order->client_completed_at === null || $order->provider_completed_at === null) {
            return false;
        }

        if ($order->status === 'done') {
            return false;
        }

        $warrantyUntil = now()->addDays(30);

        $order->update([
            'status' => 'done',
            'warranty_until' => $warrantyUntil,
        ]);

        $this->recordEvent(
            order: $order,
            eventType: 'warranty_activated',
            title: 'Projeto Finalizado & Garantia de 30 Dias Ativada',
            message: 'Caro cliente, lembre-se que você tem 30 dias para pedir correções de acordo com o contrato caso encontre algo que não te agrade, após este prazo a garantia está encerrada. A garantia também se perde em caso de outro prestador fora da WeBest Coding alterar o projeto. Obrigado e volte sempre!',
            payload: [
                'warranty_until' => $warrantyUntil->toIso8601String(),
                'warranty_days' => 30,
            ],
            actorId: $actorId,
        );

        return true;
    }

    /**
     * Build a complete serialized payload of an Order room for API responses.
     *
     * @return array<string, mixed>
     */
    public function serializeOrderRoom(Order $order): array
    {
        $order->load([
            'user',
            'product',
            'products',
            'attachments',
            'proposals.sender',
            'messages.sender',
            'messages.versions.editedBy',
            'adjustments.requester',
            'events.actor',
            'project',
        ]);

        $products = $order->products;
        if ($products->isEmpty() && $order->product !== null) {
            $products = collect([$order->product]);
        }

        $latestHumanMessage = $order->messages
            ->whereIn('sender_role', ['client', 'provider'])
            ->sortByDesc('id')
            ->first();

        $canClientSendNewTurn = ! in_array($order->status, ['cancelled', 'rejected'], true)
            && ($latestHumanMessage === null || $latestHumanMessage->sender_role !== 'client' || $latestHumanMessage->is_locked);

        $editableClientMessageId = ($latestHumanMessage !== null && $latestHumanMessage->sender_role === 'client' && ! $latestHumanMessage->is_locked)
            ? $latestHumanMessage->id
            : null;

        /** @var list<array<string, mixed>> $timeline */
        $timeline = [];

        foreach ($order->messages as $msg) {
            $versions = $msg->versions->map(static fn (OrderMessageVersion $v): array => [
                'id' => $v->id,
                'version_number' => $v->version_number,
                'content' => $v->content,
                'edited_by' => $v->editedBy?->name ?? 'Usuário',
                'created_at' => $v->created_at?->toIso8601String(),
                'is_current' => $v->version_number === $msg->versions->max('version_number'),
            ])->values()->all();

            $timeline[] = [
                'item_type' => 'message',
                'sort_timestamp' => $msg->created_at?->getTimestamp() ?? 0,
                'sort_id' => $msg->id,
                'id' => $msg->id,
                'sender_id' => $msg->sender_id,
                'sender_name' => $msg->sender?->name ?? ($msg->sender_role === 'provider' ? 'Equipe WeBest' : 'Cliente'),
                'sender_role' => $msg->sender_role,
                'turn_number' => $msg->turn_number,
                'current_content' => $msg->current_content,
                'is_locked' => $msg->is_locked,
                'is_edited' => count($versions) > 1,
                'edited_at' => $msg->edited_at?->toIso8601String(),
                'created_at' => $msg->created_at?->toIso8601String(),
                'versions' => $versions,
            ];
        }

        foreach ($order->events as $event) {
            $timeline[] = [
                'item_type' => 'bot_event',
                'sort_timestamp' => $event->created_at?->getTimestamp() ?? 0,
                'sort_id' => $event->id + 1000000,
                'id' => $event->id,
                'event_type' => $event->event_type,
                'title' => $event->title,
                'message' => $event->message,
                'payload' => $event->payload ?? [],
                'created_at' => $event->created_at?->toIso8601String(),
            ];
        }

        usort($timeline, static function (array $a, array $b): int {
            $timeCmp = ((int) $a['sort_timestamp']) <=> ((int) $b['sort_timestamp']);
            if ($timeCmp !== 0) {
                return $timeCmp;
            }

            return ((int) $a['sort_id']) <=> ((int) $b['sort_id']);
        });

        $latestProposal = $order->proposals->sortByDesc('id')->first();
        $activeProposalForClient = null;
        if ($latestProposal !== null && $latestProposal->is_accepted === null && $latestProposal->status === 'pending' && $latestProposal->sender_id !== $order->user_id) {
            $activeProposalForClient = [
                'id' => $latestProposal->id,
                'price' => (float) $latestProposal->price,
                'estimated_days' => $latestProposal->estimated_days,
                'content' => $latestProposal->content,
                'sender_name' => $latestProposal->sender?->name ?? 'Prestador WeBest',
                'created_at' => $latestProposal->created_at?->toIso8601String(),
            ];
        }

        return [
            'id' => $order->id,
            'code' => sprintf('#ORD-%04d', $order->id),
            'title' => $order->title,
            'description' => $order->description,
            'figma_url' => $order->figma_url,
            'status' => $order->status,
            'status_label' => Order::statusLabel($order->status),
            'status_color' => Order::statusColor($order->status),
            'final_price' => $order->final_price !== null ? (float) $order->final_price : null,
            'estimated_days' => $order->estimated_days,
            'contract_url' => $order->contract_url,
            'contract_signed_at' => $order->contract_signed_at?->toIso8601String(),
            'stripe_checkout_url' => $order->stripe_checkout_url ?? $order->project?->payment_link,
            'staging_url' => $order->staging_url ?? $order->project?->deploy_url,
            'repository_url' => $order->repository_url ?? $order->project?->github_url,
            'deadline' => $order->deadline?->toIso8601String(),
            'client_completed_at' => $order->client_completed_at?->toIso8601String(),
            'provider_completed_at' => $order->provider_completed_at?->toIso8601String(),
            'warranty_until' => $order->warranty_until?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
            'products' => $products->map(static fn ($product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'base_price' => $product->base_price !== null ? (float) $product->base_price : null,
                'billing_type' => $product->billing_type ?? 'one_time',
                'billing_interval' => $product->billing_interval,
                'billing_label' => $product->billingLabel(),
            ])->values()->all(),
            'attachments' => $order->attachments->map(static fn ($att): array => [
                'id' => $att->id,
                'original_name' => $att->original_name,
                'mime_type' => $att->mime_type,
                'file_size' => $att->file_size,
                'created_at' => $att->created_at?->toIso8601String(),
            ])->values()->all(),
            'proposals' => $order->proposals->sortBy('id')->map(static fn ($prop): array => [
                'id' => $prop->id,
                'sender_id' => $prop->sender_id,
                'sender_name' => $prop->sender?->name ?? 'Usuário',
                'is_from_client' => $prop->sender_id === $order->user_id,
                'content' => $prop->content,
                'price' => (float) $prop->price,
                'estimated_days' => $prop->estimated_days,
                'is_accepted' => $prop->is_accepted,
                'status' => $prop->status,
                'created_at' => $prop->created_at?->toIso8601String(),
            ])->values()->all(),
            'active_proposal_for_client' => $activeProposalForClient,
            'adjustments' => $order->adjustments->sortBy('id')->map(static fn ($adj): array => [
                'id' => $adj->id,
                'client_description' => $adj->client_description,
                'decision' => $adj->decision,
                'decision_label' => $adj->decisionLabel(),
                'provider_notes' => $adj->provider_notes,
                'billing_mode' => $adj->billing_mode,
                'estimated_hours' => $adj->estimated_hours,
                'hourly_rate' => $adj->hourly_rate !== null ? (float) $adj->hourly_rate : null,
                'addendum_scope' => $adj->addendum_scope,
                'addendum_price' => $adj->addendum_price !== null ? (float) $adj->addendum_price : null,
                'counter_price' => $adj->counter_price !== null ? (float) $adj->counter_price : null,
                'counter_notes' => $adj->counter_notes,
                'addendum_extra_days' => $adj->addendum_extra_days,
                'contract_reference' => $adj->contract_reference,
                'stripe_payment_link' => $adj->stripe_payment_link,
                'payment_status' => $adj->payment_status,
                'resolved_at' => $adj->resolved_at?->toIso8601String(),
                'created_at' => $adj->created_at?->toIso8601String(),
            ])->values()->all(),
            'turn_state' => [
                'can_client_send_new_turn' => $canClientSendNewTurn,
                'editable_client_message_id' => $editableClientMessageId,
                'editable_client_content' => $editableClientMessageId !== null && $latestHumanMessage !== null
                    ? $latestHumanMessage->current_content
                    : null,
            ],
            'timeline' => $timeline,
        ];
    }
}
