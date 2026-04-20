<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kanban')
                ->label('Kanban')
                ->url(OrderResource::getUrl('kanban'))
                ->visible(fn (): bool => OrderResource::canViewAny()),
            CreateAction::make()
                ->visible(fn (): bool => OrderResource::canCreate()),
        ];
    }
}
