<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class MyProjects extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = 'Meus Projetos';

    protected static ?string $title = 'Meus Projetos';

    protected string $view = 'filament.pages.my-projects';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = static::currentUser();

        return $user?->hasRole('client') && $user->can(Permission::ViewMyProjects->value);
    }

    /**
     * @return Collection<int, Project>
     */
    public function projects(): Collection
    {
        $user = static::currentUser();

        if (! $user) {
            return new Collection;
        }

        return Project::query()
            ->with(['order.user', 'order.product', 'steps', 'changeRequests'])
            ->whereHas('order', function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            })
            ->latest()
            ->get();
    }

    public function projectUrl(Project $project): string
    {
        return ProjectResource::getUrl('view', [
            'record' => $project,
        ]);
    }

    protected static function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
