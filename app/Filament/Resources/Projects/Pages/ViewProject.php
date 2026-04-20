<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected string $view = 'filament.resources.projects.pages.view-project';

    /**
     * @var array{description: string}
     */
    public array $newChangeRequest = [
        'description' => '',
    ];

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

    public function canCreateChangeRequest(): bool
    {
        $user = static::currentUser();

        if (! $user || ! $this->record instanceof Project) {
            return false;
        }

        if (! $user->hasRole('client')) {
            return false;
        }

        if (! $user->can(Permission::SubmitChangeRequests->value)) {
            return false;
        }

        return (int) $this->record->order?->user_id === (int) $user->id;
    }

    public function submitChangeRequest(): void
    {
        abort_unless($this->canCreateChangeRequest(), 403);

        $validated = $this->validate([
            'newChangeRequest.description' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $user = static::currentUser();

        if (! $user) {
            abort(403);
        }

        ChangeRequest::query()->create([
            'project_id' => (int) $this->record->id,
            'requester_id' => (int) $user->id,
            'description' => $validated['newChangeRequest']['description'],
            'status' => 'requested',
            'impact_price' => null,
            'payment_link' => null,
        ]);

        $this->newChangeRequest = [
            'description' => '',
        ];

        $this->record->refresh();
        $this->record->loadMissing(['changeRequests.requester']);

        Notification::make()
            ->title('Solicitação enviada')
            ->body('Seu pedido de alteração foi criado com sucesso.')
            ->success()
            ->send();
    }

    protected static function canManageProjects(): bool
    {
        $user = static::currentUser();

        return $user instanceof User && $user->can(Permission::ManageProjects->value);
    }

    protected static function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
