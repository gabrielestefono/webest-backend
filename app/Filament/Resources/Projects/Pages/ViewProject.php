<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected string $view = 'filament.resources.projects.pages.view-project';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => static::canManageProjects()),
        ];
    }

    public function progressPercentage(): int
    {
        return (int) $this->record->current_progress;
    }

    public function isCompleted(): bool
    {
        return $this->progressPercentage() >= 100;
    }

    public function completedStepsCount(): int
    {
        return $this->record->steps->where('is_completed', true)->count();
    }

    public function totalStepsCount(): int
    {
        return $this->record->steps->count();
    }

    public function completedWeight(): int
    {
        return (int) $this->record->steps->where('is_completed', true)->sum('weight');
    }

    public function totalWeight(): int
    {
        return (int) $this->record->steps->sum('weight');
    }

    protected static function canManageProjects(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can(Permission::ManageProjects->value);
    }
}
