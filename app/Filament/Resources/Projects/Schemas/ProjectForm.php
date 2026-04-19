<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\Permission;
use App\Models\Project;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_id')
                    ->label('Pedido')
                    ->relationship('order', 'title')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled(fn (): bool => ! static::canManageProjects())
                    ->dehydrated(fn (): bool => static::canManageProjects()),
                TextInput::make('payment_link')
                    ->label('Link de pagamento')
                    ->url()
                    ->maxLength(255)
                    ->required(),
                Select::make('payment_status')
                    ->label('Status do pagamento')
                    ->options(Project::PAYMENT_STATUSES)
                    ->required(),
                TextInput::make('github_url')
                    ->label('GitHub')
                    ->url()
                    ->maxLength(255)
                    ->nullable(),
                TextInput::make('deploy_url')
                    ->label('Deploy')
                    ->url()
                    ->maxLength(255)
                    ->nullable(),
                TextInput::make('current_progress')
                    ->label('Progresso (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(0)
                    ->required(),
            ]);
    }

    protected static function canManageProjects(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can(Permission::ManageProjects->value);
    }
}
