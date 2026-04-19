<?php

namespace App\Filament\Resources\Products;

use App\Enums\Permission;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return (bool) static::currentUser()?->can(Permission::ManageProducts->value);
    }

    public static function canView(Model $record): bool
    {
        return (bool) static::currentUser()?->can(Permission::ManageProducts->value);
    }

    public static function canCreate(): bool
    {
        return (bool) static::currentUser()?->can(Permission::ManageProducts->value);
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) static::currentUser()?->can(Permission::ManageProducts->value);
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) static::currentUser()?->can(Permission::ManageProducts->value);
    }

    public static function canDeleteAny(): bool
    {
        return (bool) static::currentUser()?->can(Permission::ManageProducts->value);
    }

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema)->columns(1);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    protected static function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
