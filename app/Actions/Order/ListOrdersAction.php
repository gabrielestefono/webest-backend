<?php

namespace App\Actions\Order;

use App\Dto\Order\ListOrdersDto;
use App\Exceptions\UnauthorizedException;
use App\Models\Order;

class ListOrdersAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(ListOrdersDto $dto): array
    {
        $user = $dto->user;
        if (! $user) {
            throw new UnauthorizedException('Usuário não autenticado.');
        }

        $query = Order::query()
            ->with(['products', 'product', 'events', 'messages', 'proposals'])
            ->where('user_id', $user->id)
            ->latest('updated_at');

        if ($dto->statusFilter !== null && $dto->statusFilter !== '' && $dto->statusFilter !== 'all') {
            $query->where('status', $dto->statusFilter);
        }

        $items = $query->get()->map(static function (Order $order): array {
            $products = $order->products->isNotEmpty()
                ? $order->products
                : ($order->product !== null ? collect([$order->product]) : collect());

            $lastEvent = $order->events->sortByDesc('id')->first();
            $lastMessage = $order->messages->sortByDesc('id')->first();

            $hasPendingProviderProposal = $order->proposals
                ->where('status', 'pending')
                ->whereNull('is_accepted')
                ->where('sender_id', '!=', $order->user_id)
                ->isNotEmpty();

            $snippet = $order->description;
            if ($lastMessage !== null) {
                $snippet = $lastMessage->current_content;
            } elseif ($lastEvent !== null) {
                $snippet = $lastEvent->message;
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
                'has_pending_proposal' => $hasPendingProviderProposal,
                'products' => array_values($products->map(static fn ($p): array => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'billing_type' => $p->billing_type ?? 'one_time',
                    'billing_label' => $p->billingLabel(),
                ])->all()),
                'last_snippet' => $snippet,
                'created_at' => $order->created_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
            ];
        })->all();

        return array_values($items);
    }
}
