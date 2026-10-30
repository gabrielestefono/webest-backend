<?php

namespace App\Actions\Dashboard;

use App\Dto\Dashboard\DashboardOverviewDto;
use App\Exceptions\UnauthorizedException;
use App\Models\Order;
use App\Models\Proposal;

class DashboardOverviewAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(DashboardOverviewDto $dto): array
    {
        $user = $dto->user;
        if (! $user) {
            throw new UnauthorizedException('Usuário não autenticado.');
        }

        $activeProjects = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                'awaiting_development',
                'in_progress',
                'homologation',
                'adjustment_review',
                'in_revision',
                'review',
                'paid',
            ])
            ->count();

        $pendingOrders = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                'pending',
                'under_review',
                'proposal_sent',
                'proposal_negotiation',
                'approved',
                'awaiting_contract',
                'awaiting_payment',
            ])
            ->count();

        $proposalsReceived = Proposal::query()
            ->whereHas('order', static function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            })
            ->where('sender_id', '!=', $user->id)
            ->count();

        $recentOrders = Order::query()
            ->with(['products', 'product'])
            ->where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get()
            ->map(static function (Order $order): array {
                $products = $order->products->isNotEmpty()
                    ? $order->products
                    : ($order->product !== null ? collect([$order->product]) : collect());

                return [
                    'id' => $order->id,
                    'code' => sprintf('#ORD-%04d', $order->id),
                    'title' => $order->title,
                    'status' => $order->status,
                    'status_label' => Order::statusLabel($order->status),
                    'final_price' => $order->final_price !== null ? (float) $order->final_price : null,
                    'products' => $products->pluck('name')->values()->all(),
                    'updated_at' => $order->updated_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'stats' => [
                'active_projects' => $activeProjects,
                'pending_orders' => $pendingOrders,
                'proposals_received' => $proposalsReceived,
            ],
            'recent_orders' => $recentOrders,
        ];
    }
}

