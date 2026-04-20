<x-filament-panels::page>
    @php
        $columns = $this->kanbanColumns();

        $colorMap = [
            'gray' => ['bg' => '#f3f4f6', 'text' => '#374151', 'badge' => '#6b7280'],
            'info' => ['bg' => '#eff6ff', 'text' => '#1e3a8a', 'badge' => '#2563eb'],
            'warning' => ['bg' => '#fffbeb', 'text' => '#92400e', 'badge' => '#d97706'],
            'success' => ['bg' => '#ecfdf5', 'text' => '#065f46', 'badge' => '#10b981'],
            'danger' => ['bg' => '#fef2f2', 'text' => '#991b1b', 'badge' => '#ef4444'],
            'primary' => ['bg' => '#eef2ff', 'text' => '#3730a3', 'badge' => '#4f46e5'],
        ];
    @endphp

    <div style="display: flex; flex-direction: column; gap: 16px;">
        <div style="display: grid; grid-template-columns: repeat(1, minmax(0, 1fr)); gap: 12px;">
            <div style="border: 1px solid #e5e7eb; border-radius: 14px; background: #ffffff; padding: 14px 16px;">
                <p style="margin: 0; font-size: 13px; color: #6b7280;">
                    Visualize todos os pedidos por status para acompanhar o funil comercial.
                </p>
            </div>
        </div>

        <div style="display: flex; gap: 14px; overflow-x: auto; padding-bottom: 4px;">
            @foreach($columns as $column)
                @php
                    $colors = $colorMap[$column['color']] ?? $colorMap['gray'];
                    $orders = $column['orders'];
                @endphp

                <div style="min-width: 290px; max-width: 320px; width: 320px; border: 1px solid #e5e7eb; border-radius: 14px; background: #ffffff; display: flex; flex-direction: column; max-height: calc(100vh - 260px);">
                    <div style="position: sticky; top: 0; z-index: 1; border-bottom: 1px solid #e5e7eb; border-top-left-radius: 14px; border-top-right-radius: 14px; padding: 12px; background: <?php echo e($colors['bg']); ?>;">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                            <strong style="font-size: 14px; color: <?php echo e($colors['text']); ?>;">{{ $column['label'] }}</strong>
                            <span style="display: inline-flex; align-items: center; justify-content: center; min-width: 26px; height: 22px; border-radius: 9999px; background: <?php echo e($colors['badge']); ?>; color: #ffffff; font-size: 12px; font-weight: 700; padding: 0 8px;">
                                {{ $orders->count() }}
                            </span>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 10px; padding: 10px; overflow-y: auto;">
                        @forelse($orders as $order)
                            <div
                                style="display: flex; flex-direction: column; gap: 6px; border: 1px solid #e5e7eb; border-radius: 10px; background: #ffffff; padding: 10px;"
                            >
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                                    <span style="font-size: 12px; color: #6b7280;">Pedido #{{ $order->id }}</span>
                                    <span style="font-size: 11px; color: #6b7280;">{{ $order->created_at?->format('d/m/Y') }}</span>
                                </div>

                                <div style="font-size: 14px; font-weight: 700; color: #111827; line-height: 1.25;">
                                    {{ $order->title }}
                                </div>

                                <div style="font-size: 12px; color: #4b5563;">
                                    Cliente: {{ $order->user->name }}
                                </div>

                                <div style="font-size: 12px; color: #4b5563;">
                                    Produto: {{ $order->product->name }}
                                </div>

                                @can(\App\Enums\Permission::ManageOrders->value)
                                    <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px;">
                                        <button
                                            type="button"
                                            wire:click="updateOrderStatus({{ $order->id }}, 'in_progress')"
                                            style="border: 1px solid #d1d5db; border-radius: 6px; background: #eef2ff; color: #3730a3; padding: 4px 8px; font-size: 11px; cursor: pointer;"
                                        >
                                            Em andamento
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="updateOrderStatus({{ $order->id }}, 'review')"
                                            style="border: 1px solid #d1d5db; border-radius: 6px; background: #eff6ff; color: #1d4ed8; padding: 4px 8px; font-size: 11px; cursor: pointer;"
                                        >
                                            Em revisão
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="updateOrderStatus({{ $order->id }}, 'done')"
                                            style="border: 1px solid #d1d5db; border-radius: 6px; background: #ecfdf5; color: #047857; padding: 4px 8px; font-size: 11px; cursor: pointer;"
                                        >
                                            Concluído
                                        </button>
                                    </div>
                                @endcan

                                <a
                                    href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('edit', ['record' => $order]) }}"
                                    style="display: inline-flex; align-items: center; justify-content: center; margin-top: 6px; border: 1px solid #d1d5db; border-radius: 6px; background: #ffffff; color: #111827; padding: 6px 8px; font-size: 12px; text-decoration: none;"
                                >
                                    Abrir pedido
                                </a>
                            </div>
                        @empty
                            <div style="border: 1px dashed #d1d5db; border-radius: 10px; padding: 12px; font-size: 13px; color: #6b7280; text-align: center;">
                                Sem pedidos neste status.
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
