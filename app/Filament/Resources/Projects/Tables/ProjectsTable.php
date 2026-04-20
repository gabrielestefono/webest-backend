<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\Permission;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                /** @var User|null $user */
                $user = Auth::user();

                $query->withCount([
                    'changeRequests as pending_change_requests_count' => static function (Builder $changeRequestsQuery): void {
                        $changeRequestsQuery->where('status', 'requested');
                    },
                ]);

                if (! $user) {
                    return $query->whereKey(-1);
                }

                if ($user->can(Permission::ManageProjects->value)) {
                    return $query
                        ->orderByDesc('pending_change_requests_count')
                        ->orderByDesc('updated_at');
                }

                return $query->whereHas('order', function (Builder $orderQuery) use ($user): void {
                    $orderQuery->where('user_id', $user->id);
                })
                    ->orderByDesc('pending_change_requests_count')
                    ->orderByDesc('updated_at');
            })
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('order.title')
                    ->label('Pedido')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.user.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pending_change_requests_count')
                    ->label('Pendências')
                    ->badge()
                    ->color(static fn (int|string|null $state): string => (int) $state > 0 ? 'warning' : 'gray')
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->label('Pagamento')
                    ->badge()
                    ->formatStateUsing(static fn (string $state): string => match ($state) {
                        'pending' => 'Pendente',
                        'approved' => 'Aprovado',
                        'rejected' => 'Rejeitado',
                        'paid' => 'Pago',
                        default => ucfirst($state),
                    })
                    ->color(static fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'paid' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('current_progress')
                    ->label('Progresso')
                    ->suffix('%')
                    ->badge()
                    ->color(static fn (string|int|null $state): string => (int) $state >= 100 ? 'success' : 'info')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    protected static function canManageProjects(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can(Permission::ManageProjects->value);
    }
}
