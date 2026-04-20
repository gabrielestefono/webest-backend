<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ProjectManagementPage extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected string $view = 'filament.resources.projects.pages.project-management-page';

    /**
     * @var array<string, mixed>
     */
    public array $quickData = [];

    /**
     * @var array{title: string, weight: int}
     */
    public array $newStep = [
        'title' => '',
        'weight' => 1,
    ];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless(static::canManageProjects(), 403);

        $this->fillQuickData();
    }

    public function saveProjectSettings(): void
    {
        abort_unless(static::canManageProjects(), 403);

        $validated = $this->validate([
            'quickData.payment_status' => ['required', 'string', 'in:'.implode(',', array_keys(Project::PAYMENT_STATUSES))],
            'quickData.github_url' => ['nullable', 'url', 'max:255'],
            'quickData.deploy_url' => ['nullable', 'url', 'max:255'],
        ]);

        $this->record->update([
            'payment_status' => $validated['quickData']['payment_status'],
            'github_url' => $validated['quickData']['github_url'] ?? null,
            'deploy_url' => $validated['quickData']['deploy_url'] ?? null,
        ]);

        $this->record->syncOrderStatusFromPayment();

        Notification::make()
            ->title('Projeto atualizado')
            ->body('Dados administrativos salvos com sucesso.')
            ->success()
            ->send();

        $this->record->refresh();
        $this->fillQuickData();
    }

    public function toggleStepCompletion(int $stepId): void
    {
        abort_unless(static::canManageProjects(), 403);

        $step = $this->record->steps()->whereKey($stepId)->first();

        if (! $step) {
            abort(404);
        }

        $shouldComplete = ! $step->is_completed;

        $step->update([
            'is_completed' => $shouldComplete,
            'completed_at' => $shouldComplete ? now() : null,
        ]);

        $this->syncProgressFromSteps();

        $this->record->refresh();
        $this->record->load('steps');

        Notification::make()
            ->title('Etapa atualizada')
            ->body($shouldComplete ? 'Etapa marcada como concluída.' : 'Etapa marcada como pendente.')
            ->success()
            ->send();
    }

    public function createStep(): void
    {
        abort_unless(static::canManageProjects(), 403);

        $validated = $this->validate([
            'newStep.title' => ['required', 'string', 'min:3', 'max:120'],
            'newStep.weight' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        ProjectStep::query()->create([
            'project_id' => $this->record->id,
            'title' => $validated['newStep']['title'],
            'weight' => (int) $validated['newStep']['weight'],
            'is_completed' => false,
            'completed_at' => null,
        ]);

        $this->newStep = [
            'title' => '',
            'weight' => 1,
        ];

        $this->syncProgressFromSteps();

        $this->record->refresh();
        $this->record->load('steps');

        Notification::make()
            ->title('Etapa criada')
            ->body('Nova etapa adicionada com sucesso.')
            ->success()
            ->send();
    }

    public function deleteStep(int $stepId): void
    {
        abort_unless(static::canManageProjects(), 403);

        $step = $this->record->steps()->whereKey($stepId)->first();

        if (! $step) {
            abort(404);
        }

        $step->delete();

        $this->syncProgressFromSteps();

        $this->record->refresh();
        $this->record->load('steps');

        Notification::make()
            ->title('Etapa removida')
            ->body('A etapa foi removida com sucesso.')
            ->success()
            ->send();
    }

    public function progressPercentage(): int
    {
        return (int) $this->record->current_progress;
    }

    public function completedStepsCount(): int
    {
        return $this->record->steps->where('is_completed', true)->count();
    }

    public function totalStepsCount(): int
    {
        return $this->record->steps->count();
    }

    protected function fillQuickData(): void
    {
        $this->record->loadMissing(['order.user', 'order.product', 'steps']);

        $this->quickData = [
            'payment_status' => $this->record->payment_status,
            'github_url' => $this->record->github_url,
            'deploy_url' => $this->record->deploy_url,
        ];
    }

    protected function syncProgressFromSteps(): void
    {
        $steps = $this->record->steps()->get();

        $totalWeight = (int) $steps->sum('weight');

        if ($totalWeight <= 0) {
            $this->record->update(['current_progress' => 0]);

            return;
        }

        $completedWeight = (int) $steps
            ->where('is_completed', true)
            ->sum('weight');

        $progress = (int) round(($completedWeight / $totalWeight) * 100);

        $this->record->update([
            'current_progress' => max(0, min(100, $progress)),
        ]);
    }

    protected static function canManageProjects(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can(Permission::ManageProjects->value);
    }
}
