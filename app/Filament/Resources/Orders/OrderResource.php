<?php

namespace App\Filament\Resources\Orders;

use App\Enums\Permission;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\NegotiationPage;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'Pedido';

    protected static ?string $pluralModelLabel = 'Pedidos';

    protected static ?string $navigationLabel = 'Pedidos';

    protected static ?string $recordTitleAttribute = 'title';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        $user = static::currentUser();

        if (! $user) {
            return false;
        }

        return $user->canAny([
            Permission::ManageOrders->value,
            Permission::SubmitProposals->value,
            Permission::AcceptProposals->value,
        ]);
    }

    public static function canCreate(): bool
    {
        $user = static::currentUser();

        if (! $user) {
            return false;
        }

        if ($user->can(Permission::ManageOrders->value) || $user->hasRole('admin')) {
            return false;
        }

        return $user->hasRole('client');
    }

    public static function canView(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function canEdit(Model $record): bool
    {
        $user = static::currentUser();

        if (! $user || ! $record instanceof Order) {
            return false;
        }

        if ($user->can(Permission::ManageOrders->value)) {
            return true;
        }

        return $record->user_id === $user->id
            && $user->canAny([
                Permission::SubmitProposals->value,
                Permission::AcceptProposals->value,
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) static::currentUser()?->can(Permission::ManageOrders->value);
    }

    public static function canDeleteAny(): bool
    {
        return (bool) static::currentUser()?->can(Permission::ManageOrders->value);
    }

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema)->columns(1);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
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
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit' => NegotiationPage::route('/{record}'),
        ];
    }

    protected static function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
