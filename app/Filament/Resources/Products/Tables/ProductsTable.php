<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Produto')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('billing_type')
                    ->label('Cobrança')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, Product $record): string => $record->billingLabel())
                    ->color(fn (?string $state): string => $state === 'recurring' ? 'info' : 'success'),
                TextColumn::make('base_price')
                    ->label('Preço Base')
                    ->money('BRL')
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('active')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('billing_type')
                    ->label('Tipo de Cobrança')
                    ->options(Product::BILLING_TYPES),
                SelectFilter::make('active')
                    ->label('Status')
                    ->options([
                        1 => 'Ativos',
                        0 => 'Inativos',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
