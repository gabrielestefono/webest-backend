<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\Permission;
use App\Models\User;
use Filament\Actions\EditAction;
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

                if (! $user) {
                    return $query->whereKey(-1);
                }

                if ($user->can(Permission::ManageProjects->value)) {
                    return $query;
                }

                return $query->whereHas('order', function (Builder $orderQuery) use ($user): void {
                    $orderQuery->where('user_id', $user->id);
                });
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
                EditAction::make()
                    ->visible(fn (): bool => static::canManageProjects()),
            ]);
    }

    protected static function canManageProjects(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can(Permission::ManageProjects->value);
    }
}
