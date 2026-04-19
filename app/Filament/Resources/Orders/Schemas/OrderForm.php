<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\Permission;
use App\Filament\Forms\Components\ProductCards;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Título')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Descrição do pedido')
                    ->required()
                    ->rows(6)
                    ->columnSpanFull(),
                ProductCards::make('product_id')
                    ->label('Produto')
                    ->products(fn () => Product::query()
                        ->where('active', true)
                        ->orderBy('name')
                        ->get(['id', 'name', 'description', 'base_price']))
                    ->required()
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('Status')
                    ->options(Order::statusOptions())
                    ->default('pending')
                    ->required()
                    ->visible(function () {
                        /**
                         * @var Factory|Guard $auth
                         */
                        $auth = auth();

                        /**
                         * @var User|null $user
                         */
                        $user = $auth->user();

                        return $user?->can(Permission::ManageOrders->value) ?? false;
                    }),
            ]);
    }
}
