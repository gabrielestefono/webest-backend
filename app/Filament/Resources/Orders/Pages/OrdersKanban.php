<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class OrdersKanban extends Page
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.resources.orders.pages.orders-kanban';

    public function getHeading(): string
    {
        return 'Kanban de pedidos';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('list')
                ->label('Voltar para lista')
                ->url(OrderResource::getUrl('index')),
        ];
    }

    /**
     * @return array<int, array{status: string, label: string, color: string, orders: Collection<int, Order>}>
     */
    public function kanbanColumns(): array
    {
        $orders = $this->ordersQuery()
            ->with(['user', 'product'])
            ->latest()
            ->get();

        $columns = [];

        foreach (Order::statusOptions() as $status => $label) {
            $columns[] = [
                'status' => $status,
                'label' => $label,
                'color' => Order::statusColor($status),
                'orders' => $orders->where('status', $status)->values(),
            ];
        }

        return $columns;
    }

    /**
     * @return array<string, string>
     */
    public function manageableProgressStatuses(): array
    {
        return [
            'in_progress' => Order::statusOptions()['in_progress'],
            'review' => Order::statusOptions()['review'],
            'done' => Order::statusOptions()['done'],
        ];
    }

    public function updateOrderStatus(int $orderId, string $status): void
    {
        abort_unless($this->canManageOrders(), 403);

        if (! array_key_exists($status, $this->manageableProgressStatuses())) {
            Notification::make()
                ->title('Status inválido')
                ->body('Selecione um status válido de execução.')
                ->danger()
                ->send();

            return;
        }

        $order = Order::query()->whereKey($orderId)->first();

        if (! $order) {
            abort(404);
        }

        $order->update([
            'status' => $status,
        ]);

        Notification::make()
            ->title('Status atualizado')
            ->body('Pedido atualizado com sucesso.')
            ->success()
            ->send();
    }

    protected function ordersQuery(): Builder
    {
        $query = Order::query();

        $user = static::currentUser();

        if (! $user) {
            return $query->whereKey(-1);
        }

        if ($user->can(Permission::ManageOrders->value)) {
            return $query;
        }

        return $query
            ->where('user_id', $user->id)
            ->whereIn('status', Order::activeStatuses());
    }

    protected static function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    protected function canManageOrders(): bool
    {
        return (bool) static::currentUser()?->can(Permission::ManageOrders->value);
    }
}
