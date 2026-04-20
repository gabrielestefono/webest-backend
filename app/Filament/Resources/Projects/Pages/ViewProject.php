<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\User;
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

    /**
     * @var array<int|string, array{impact_price?: string|int|float|null}>
     */
    public array $quoteForms = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->canManageProjects()) {
            $this->fillQuickData();
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
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

    public function canAnalyzeChangeRequests(): bool
    {
        $user = static::currentUser();

        return $user instanceof User
            && $user->can(Permission::ManageChangeRequests->value);
    }

    public function canManageProjectActions(): bool
    {
        return static::canManageProjects();
    }

    public function submitQuote(int $changeRequestId): void
    {
        abort_unless($this->canAnalyzeChangeRequests(), 403);

        $changeRequest = ChangeRequest::query()
            ->where('project_id', $this->record->id)
            ->whereKey($changeRequestId)
            ->first();

        if (! $changeRequest) {
            abort(404);
        }

        if ($changeRequest->status !== 'awaiting_quote' || ! $changeRequest->canTransitionTo('quoted')) {
            Notification::make()
                ->title('Ação inválida')
                ->body('Esta solicitação não está disponível para cotação.')
                ->danger()
                ->send();

            return;
        }

        $field = "quoteForms.{$changeRequestId}.impact_price";

        $validated = $this->validate([
            $field => ['required', 'numeric', 'min:0'],
        ]);

        $impactPrice = (float) data_get($validated, $field);

        $changeRequest->update([
            'impact_price' => $impactPrice,
            'status' => 'quoted',
        ]);

        unset($this->quoteForms[$changeRequestId]);

        $this->record->refresh();
        $this->record->loadMissing(['changeRequests.requester']);

        Notification::make()
            ->title('Cotação enviada')
            ->body('Solicitação analisada e movida para Orçada.')
            ->success()
            ->send();
    }

    public function approveChangeRequest(int $changeRequestId): void
    {
        abort_unless($this->canAnalyzeChangeRequests(), 403);

        $changeRequest = ChangeRequest::query()
            ->where('project_id', $this->record->id)
            ->whereKey($changeRequestId)
            ->first();

        if (! $changeRequest) {
            abort(404);
        }

        if ($changeRequest->status !== 'requested' || ! $changeRequest->canTransitionTo('awaiting_quote')) {
            Notification::make()
                ->title('Ação inválida')
                ->body('Esta solicitação não pode ser aprovada neste momento.')
                ->danger()
                ->send();

            return;
        }

        $changeRequest->update([
            'status' => 'awaiting_quote',
            'impact_price' => null,
        ]);

        $this->record->refresh();
        $this->record->loadMissing(['changeRequests.requester']);

        Notification::make()
            ->title('Solicitação aprovada')
            ->body('A solicitação agora está aguardando orçamento.')
            ->success()
            ->send();
    }

    public function rejectChangeRequest(int $changeRequestId): void
    {
        abort_unless($this->canAnalyzeChangeRequests(), 403);

        $changeRequest = ChangeRequest::query()
            ->where('project_id', $this->record->id)
            ->whereKey($changeRequestId)
            ->first();

        if (! $changeRequest) {
            abort(404);
        }

        if ($changeRequest->status !== 'requested' || ! $changeRequest->canTransitionTo('rejected')) {
            Notification::make()
                ->title('Ação inválida')
                ->body('Esta solicitação não pode ser recusada neste momento.')
                ->danger()
                ->send();

            return;
        }

        $changeRequest->update([
            'status' => 'rejected',
        ]);

        $this->record->refresh();
        $this->record->loadMissing(['changeRequests.requester']);

        Notification::make()
            ->title('Solicitação recusada')
            ->body('A solicitação foi marcada como recusada.')
            ->success()
            ->send();
    }

    public function saveProjectSettings(): void
    {
        abort_unless($this->canManageProjects(), 403);

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

    public function createStep(): void
    {
        abort_unless($this->canManageProjects(), 403);

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

    public function toggleStepCompletion(int $stepId): void
    {
        abort_unless($this->canManageProjects(), 403);

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

    public function deleteStep(int $stepId): void
    {
        abort_unless($this->canManageProjects(), 403);

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

    protected function fillQuickData(): void
    {
        $this->record->loadMissing(['order.user', 'order.product', 'steps', 'changeRequests.requester']);

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
        $user = static::currentUser();

        return $user instanceof User && $user->can(Permission::ManageProjects->value);
    }

    protected static function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
