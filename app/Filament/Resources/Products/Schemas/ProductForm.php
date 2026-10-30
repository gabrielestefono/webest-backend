<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome do Produto / Serviço')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Descrição')
                    ->nullable()
                    ->maxLength(65535)
                    ->columnSpanFull(),
                TextInput::make('base_price')
                    ->label('Preço Base (R$)')
                    ->numeric()
                    ->inputMode('decimal')
                    ->step('0.01')
                    ->minValue(0)
                    ->nullable(),
                Select::make('billing_type')
                    ->label('Tipo de Pagamento')
                    ->options(Product::BILLING_TYPES)
                    ->default('one_time')
                    ->required()
                    ->live(),
                Select::make('billing_interval')
                    ->label('Periodicidade da Assinatura')
                    ->options(Product::BILLING_INTERVALS)
                    ->nullable()
                    ->visible(fn (Get $get): bool => $get('billing_type') === 'recurring')
                    ->required(fn (Get $get): bool => $get('billing_type') === 'recurring'),
                TextInput::make('stripe_price_id')
                    ->label('Stripe Price ID (Opcional)')
                    ->placeholder('price_1Pxyz...')
                    ->nullable()
                    ->maxLength(255),
                Toggle::make('active')
                    ->label('Ativo no Catálogo')
                    ->default(true)
                    ->required(),
            ]);
    }
}
