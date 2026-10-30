<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\Permission;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OrdersTable
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

                if ($user->can(Permission::ManageOrders->value)) {
                    return $query->with(['products', 'product', 'user'])->latest('updated_at');
                }

                return $query
                    ->with(['products', 'product', 'user'])
                    ->where('user_id', $user->id)
                    ->whereIn('status', Order::activeStatuses());
            })
            ->columns([
                TextColumn::make('id')
                    ->label('Código')
                    ->formatStateUsing(fn (int $state): string => sprintf('#ORD-%04d', $state))
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('products.name')
                    ->label('Produtos')
                    ->badge()
                    ->placeholder(fn (Order $record): string => $record->product?->name ?? '-'),
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(36)
                    ->wrap(),
                TextColumn::make('final_price')
                    ->label('Valor Acordado')
                    ->money('BRL')
                    ->placeholder('Em definição')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Order::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => Order::statusColor($state))
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(Order::statusOptions()),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Operar Pedido'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
