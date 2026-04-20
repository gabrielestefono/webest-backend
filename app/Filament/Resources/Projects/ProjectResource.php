<?php

namespace App\Filament\Resources\Projects;

use App\Enums\Permission;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ProjectManagementPage;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\Schemas\ProjectForm;
use App\Filament\Resources\Projects\Tables\ProjectsTable;
use App\Models\Project;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $modelLabel = 'Projeto';

    protected static ?string $pluralModelLabel = 'Projetos';

    protected static ?string $navigationLabel = 'Projetos';

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) static::currentUser()?->can(Permission::ManageProjects->value);
    }

    public static function getNavigationBadge(): ?string
    {
        $user = static::currentUser();

        if (! $user || ! $user->can(Permission::ManageProjects->value)) {
            return null;
        }

        $pendingProjectsCount = Project::query()
            ->whereHas('changeRequests', static function (Builder $changeRequestsQuery): void {
                $changeRequestsQuery->where('status', 'requested');
            })
            ->count();

        return $pendingProjectsCount > 0 ? (string) $pendingProjectsCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canViewAny(): bool
    {
        $user = static::currentUser();

        if (! $user) {
            return false;
        }

        return $user->canAny([
            Permission::ManageProjects->value,
            Permission::ViewMyProjects->value,
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'order.user',
                'order.product',
                'steps',
                'changeRequests.requester',
            ]);

        $user = static::currentUser();

        if (! $user || $user->can(Permission::ManageProjects->value)) {
            return $query;
        }

        return $query->whereHas('order', function (Builder $orderQuery) use ($user): void {
            $orderQuery->where('user_id', $user->id);
        });
    }

    public static function canCreate(): bool
    {
        return (bool) static::currentUser()?->can(Permission::ManageProjects->value);
    }

    public static function canView(Model $record): bool
    {
        $user = static::currentUser();

        if (! $user || ! $record instanceof Project) {
            return false;
        }

        if ($user->can(Permission::ManageProjects->value)) {
            return true;
        }

        return $record->order?->user_id === $user->id;
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) static::currentUser()?->can(Permission::ManageProjects->value);
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) static::currentUser()?->can(Permission::ManageProjects->value);
    }

    public static function canDeleteAny(): bool
    {
        return (bool) static::currentUser()?->can(Permission::ManageProjects->value);
    }

    public static function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema)->columns(2);
    }

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'view' => ViewProject::route('/{record}'),
            'edit' => ProjectManagementPage::route('/{record}/manage'),
        ];
    }

    protected static function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
