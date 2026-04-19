<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Description')
                    ->nullable()
                    ->maxLength(65535)
                    ->columnSpanFull(),
                TextInput::make('base_price')
                    ->label('Base Price')
                    ->numeric()
                    ->inputMode('decimal')
                    ->step('0.01')
                    ->minValue(0)
                    ->nullable(),
                Toggle::make('active')
                    ->label('Active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
