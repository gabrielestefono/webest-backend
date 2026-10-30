<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\Permission;
use App\Exceptions\AppException;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderMessage;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use App\Services\OrderTimelineService;
use App\Services\StripeCheckoutService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NegotiationPage extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.resources.orders.pages.negotiation-page';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $proposalFormData = [
        'content' => '',
        'price' => '',
        'estimated_days' => 7,
        'contract_url' => '',
    ];

    public string $turnMessageContent = '';

    public string $stagingUrlInput = '';

    public string $repositoryUrlInput = '';

    public bool $showPaymentModal = false;

    public ?string $paymentMethod = null;

    public bool $showAddendumModal = false;

    public ?int $selectedAdjustmentId = null;

    /**
     * @var array<string, mixed>
     */
    public array $addendumForm = [
        'billing_mode' => 'fixed',
        'estimated_hours' => 4,
        'hourly_rate' => 80,
        'addendum_price' => '',
        'addendum_extra_days' => 3,
        'addendum_scope' => '',
        'provider_notes' => '',
    ];

    /**
     * @var array<int, string>
     */
    public array $rejectionReasons = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $historyModalMessage = null;

    /**
     * @var array{action: string, title: string, description: string, target_id: int|null}|null
     */
    public ?array $confirmationModal = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var Order $order */
        $order = $this->record;
        $this->stagingUrlInput = (string) ($order->staging_url ?? '');
        $this->repositoryUrlInput = (string) ($order->repository_url ?? '');
        $this->syncEditableProviderTurnContent();
    }

    public function getHeading(): string
    {
        /** @var Order|null $order */
        $order = $this->record;

        return $order ? sprintf('#ORD-%04d — %s', $order->id, $order->title) : 'Operação do Pedido';
    }

    public function getSubheading(): ?string
    {
        return 'Central de Operação do Prestador: Negociação Ping-Pong, Chat por Turnos, Status Manuais e Termos Aditivos';
    }

    public function requestConfirmation(string $action, string $title, string $description, ?int $targetId = null): void
    {
        $this->confirmationModal = [
            'action' => $action,
            'title' => $title,
            'description' => $description,
            'target_id' => $targetId,
        ];
    }

    public function cancelConfirmation(): void
    {
        $this->confirmationModal = null;
    }

    public function executeConfirmedAction(): void
    {
        if ($this->confirmationModal === null) {
            return;
        }

        $action = $this->confirmationModal['action'];
        $targetId = $this->confirmationModal['target_id'];
        $this->confirmationModal = null;

        match ($action) {
            'send_proposal' => $this->sendProposal(),
            'accept_proposal' => $targetId !== null && ($p = Proposal::find($targetId)) ? $this->acceptProposal($p) : null,
            'reject_proposal' => $targetId !== null && ($p = Proposal::find($targetId)) ? $this->rejectProposal($p) : null,
            'cancel_order' => $this->cancelOrderAsProvider(),
            'confirm_payment_manual' => $this->confirmPaymentManually(),
            'start_development' => $this->startDevelopmentManually(),
            'release_homologation' => $this->releaseForHomologation(),
            'complete_order_provider' => $this->confirmProviderCompletion(),
            'send_provider_turn' => $this->sendProviderTurnMessage(),
            'approve_adjustment' => $targetId !== null ? $this->approveAdjustmentInScope($targetId) : null,
            'reject_adjustment' => $targetId !== null ? $this->rejectAdjustmentOutOfScope($targetId) : null,
            'issue_addendum' => $this->issueTermoAddendum(),
            'accept_addendum_counter' => $targetId !== null ? $this->acceptAddendumCounterProposal($targetId) : null,
            'confirm_addendum_paid_manual' => $targetId !== null ? $this->confirmAddendumPaymentManually($targetId) : null,
            default => null,
        };
    }

    public function sendProposal(): void
    {
        abort_unless($this->canSubmitProposalByPermission(), 403);

        if (! $this->canSendProposal()) {
            Notification::make()
                ->title('Ação não permitida')
                ->body($this->sendBlockedMessage())
                ->danger()
                ->send();

            return;
        }

        $data = $this->proposalFormData ?? [];

        if (empty($data['content']) || empty($data['price']) || (float) $data['price'] <= 0) {
            Notification::make()
                ->title('Dados obrigatórios')
                ->body('Informe a descrição comercial e um valor válido maior que zero.')
                ->danger()
                ->send();

            return;
        }

        /** @var User $user */
        $user = Auth::user();
        /** @var Order $order */
        $order = $this->record;
        $price = (float) $data['price'];
        $days = max(1, (int) ($data['estimated_days'] ?? 7));
        $contractUrl = ! empty($data['contract_url']) && is_string($data['contract_url'])
            ? trim($data['contract_url'])
            : null;

        DB::transaction(function () use ($order, $user, $data, $price, $days, $contractUrl): void {
            $last = $this->lastProposal();
            if ($last !== null && $last->is_accepted === null && $last->sender_id !== $user->id) {
                $last->update([
                    'is_accepted' => false,
                    'status' => 'countered',
                ]);
            }

            $proposal = Proposal::query()->create([
                'order_id' => $order->id,
                'sender_id' => $user->id,
                'content' => trim((string) $data['content']),
                'price' => $price,
                'estimated_days' => $days,
                'is_accepted' => null,
                'status' => 'pending',
            ]);

            $order->update([
                'status' => 'proposal_negotiation',
                'contract_url' => $contractUrl ?? $order->contract_url,
            ]);

            $formatted = number_format($price, 2, ',', '.');
            app(OrderTimelineService::class)->recordEvent(
                order: $order,
                eventType: 'proposal_sent',
                title: 'Oferta Comercial Enviada pelo Prestador',
                message: "O prestador fez uma oferta de R$ {$formatted} pelo serviço com prazo estimado de {$days} dias úteis.",
                payload: [
                    'proposal_id' => $proposal->id,
                    'price' => $price,
                    'estimated_days' => $days,
                ],
                actorId: $user->id,
            );
        });

        Notification::make()
            ->title('Proposta enviada!')
            ->body('O cliente já pode aceitar ou enviar contra-proposta na sala do pedido.')
            ->success()
            ->send();

        $this->proposalFormData = [
            'content' => '',
            'price' => '',
            'estimated_days' => $days,
            'contract_url' => $contractUrl ?? '',
        ];

        $this->record->refresh();
    }

    public function acceptProposal(Proposal $proposal): void
    {
        abort_unless($this->canRespondToProposalByPermission(), 403);
        abort_unless($proposal->order_id === $this->record->id, 403);
        abort_unless($proposal->is_accepted === null, 403);

        /** @var User $user */
        $user = Auth::user();
        abort_unless($proposal->sender_id !== $user->id, 403);

        $lastProposal = $this->lastProposal();
        abort_unless($lastProposal?->id === $proposal->id, 403);

        /** @var Order $order */
        $order = $this->record;

        DB::transaction(function () use ($order, $proposal, $user): void {
            $order->proposals()
                ->where('is_accepted', true)
                ->whereNull('superseded_at')
                ->whereKeyNot($proposal->id)
                ->update(['superseded_at' => now()]);

            $proposal->update([
                'is_accepted' => true,
                'status' => 'accepted',
                'superseded_at' => null,
            ]);

            $contractUrl = $order->contract_url ?: "#contrato-ord-{$order->id}";

            $order->update([
                'status' => 'awaiting_contract',
                'final_price' => $proposal->price,
                'estimated_days' => $proposal->estimated_days ?: 7,
                'contract_url' => $contractUrl,
            ]);

            $formatted = number_format((float) $proposal->price, 2, ',', '.');
            app(OrderTimelineService::class)->recordEvent(
                order: $order,
                eventType: 'contract_available',
                title: 'Contra-proposta Aceita pelo Prestador!',
                message: "O prestador aceitou a contra-proposta de R$ {$formatted} ({$proposal->estimated_days} dias úteis)! O link do contrato está liberado para assinatura do cliente.",
                payload: [
                    'proposal_id' => $proposal->id,
                    'price' => (float) $proposal->price,
                    'estimated_days' => $proposal->estimated_days,
                    'contract_url' => $contractUrl,
                ],
                actorId: $user->id,
            );
        });

        Notification::make()
            ->title('Contra-proposta aceita!')
            ->body('Contrato liberado para assinatura do cliente.')
            ->success()
            ->send();

        $this->record->refresh();
    }

    public function rejectProposal(Proposal $proposal): void
    {
        abort_unless($this->canRespondToProposalByPermission(), 403);
        abort_unless($proposal->order_id === $this->record->id, 403);
        abort_unless($proposal->is_accepted === null, 403);

        /** @var User $user */
        $user = Auth::user();
        abort_unless($proposal->sender_id !== $user->id, 403);

        $lastProposal = $this->lastProposal();
        abort_unless($lastProposal?->id === $proposal->id, 403);

        $proposal->update([
            'is_accepted' => false,
            'status' => 'rejected',
        ]);

        Notification::make()
            ->title('Proposta recusada')
            ->body('Agora você pode enviar uma nova contra-oferta ou cancelar o pedido.')
            ->warning()
            ->send();

        $this->record->refresh();
    }

    public function cancelOrderAsProvider(): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        /** @var Order $order */
        $order = $this->record;
        /** @var User $user */
        $user = Auth::user();

        if (in_array($order->status, ['awaiting_development', 'in_progress', 'homologation', 'in_revision', 'done'], true)) {
            Notification::make()
                ->title('Ação bloqueada')
                ->body('Pedidos já pagos ou em execução não podem ser cancelados unilateralmente por aqui.')
                ->danger()
                ->send();

            return;
        }

        DB::transaction(function () use ($order, $user): void {
            $order->proposals()
                ->whereNull('is_accepted')
                ->update([
                    'is_accepted' => false,
                    'status' => 'cancelled',
                ]);

            $order->update(['status' => 'cancelled']);

            app(OrderTimelineService::class)->recordEvent(
                order: $order,
                eventType: 'order_cancelled',
                title: 'Pedido Cancelado pelo Prestador',
                message: 'O prestador encerrou a negociação e cancelou este pedido.',
                actorId: $user->id,
            );
        });

        Notification::make()
            ->title('Pedido cancelado')
            ->success()
            ->send();

        $this->record->refresh();
    }

    public function confirmPaymentManually(): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        /** @var Order $order */
        $order = $this->record;
        /** @var User $user */
        $user = Auth::user();

        DB::transaction(function () use ($order, $user): void {
            $project = Project::query()->firstOrCreate(
                ['order_id' => $order->id],
                [
                    'payment_link' => $order->stripe_checkout_url ?? '#',
                    'payment_status' => 'pending',
                    'current_progress' => 0,
                ]
            );

            $project->update(['payment_status' => 'paid']);

            $order->update(['status' => 'awaiting_development']);

            $days = $order->estimated_days ?? 7;

            app(OrderTimelineService::class)->recordEvent(
                order: $order,
                eventType: 'payment_confirmed',
                title: 'Pagamento Confirmado!',
                message: "O pagamento do pedido foi confirmado! Prazo estimado de entrega: {$days} dias úteis a partir do início técnico. Status: Aguardando Desenvolvimento.",
                payload: ['estimated_days' => $days],
                actorId: $user->id,
            );
        });

        Notification::make()
            ->title('Pagamento confirmado!')
            ->body('Status alterado para Aguardando Desenvolvimento.')
            ->success()
            ->send();

        $this->record->refresh();
    }

    public function startDevelopmentManually(): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        /** @var Order $order */
        $order = $this->record;
        /** @var User $user */
        $user = Auth::user();

        $days = $order->estimated_days ?? 7;
        $deadline = $order->deadline ?? now()->addWeekdays($days);

        $order->update([
            'status' => 'in_progress',
            'deadline' => $deadline,
        ]);

        app(OrderTimelineService::class)->recordEvent(
            order: $order,
            eventType: 'development_started',
            title: 'Desenvolvimento Iniciado!',
            message: 'O prestador iniciou o desenvolvimento do seu projeto! Por favor, entre periodicamente na plataforma para responder eventuais dúvidas pelo chat de forma documentada.',
            payload: [
                'deadline' => $deadline->toIso8601String(),
                'estimated_days' => $days,
            ],
            actorId: $user->id,
        );

        Notification::make()
            ->title('Desenvolvimento iniciado!')
            ->body('O cliente foi avisado no chat da sala.')
            ->success()
            ->send();

        $this->record->refresh();
    }

    public function releaseForHomologation(): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        $stagingUrl = trim($this->stagingUrlInput);
        if ($stagingUrl === '' || ! filter_var($stagingUrl, FILTER_VALIDATE_URL)) {
            Notification::make()
                ->title('Link de homologação inválido')
                ->body('Informe uma URL válida (https://...) do ambiente de testes antes de liberar para homologação.')
                ->danger()
                ->send();

            return;
        }

        /** @var Order $order */
        $order = $this->record;
        /** @var User $user */
        $user = Auth::user();
        $wasInRevision = in_array($order->status, ['in_revision', 'review'], true);

        $order->update([
            'staging_url' => $stagingUrl,
            'repository_url' => trim($this->repositoryUrlInput) !== '' ? trim($this->repositoryUrlInput) : $order->repository_url,
            'status' => 'homologation',
        ]);

        if ($order->project !== null) {
            $order->project->update([
                'deploy_url' => $stagingUrl,
                'github_url' => trim($this->repositoryUrlInput) !== '' ? trim($this->repositoryUrlInput) : $order->project->github_url,
            ]);
        }

        app(OrderTimelineService::class)->recordEvent(
            order: $order,
            eventType: $wasInRevision ? 'revision_completed' : 'homologation_available',
            title: $wasInRevision ? 'Projeto Revisado — Novo Teste Disponível' : 'Versão de Homologação Liberada para Testes',
            message: $wasInRevision
                ? 'O projeto foi revisado pelo prestador! Por favor, acesse o ambiente de homologação e valide novamente se está de acordo.'
                : 'O prestador disponibilizou o ambiente de testes para homologação. Por favor, verifique se tudo está de acordo com o escopo contratado.',
            payload: [
                'staging_url' => $stagingUrl,
            ],
            actorId: $user->id,
        );

        Notification::make()
            ->title('Liberado para homologação!')
            ->body('O cliente já pode testar e aprovar ou solicitar ajustes.')
            ->success()
            ->send();

        $this->record->refresh();
    }

    public function confirmProviderCompletion(): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        /** @var Order $order */
        $order = $this->record;
        /** @var User $user */
        $user = Auth::user();

        if ($order->provider_completed_at !== null) {
            Notification::make()
                ->title('Já confirmado')
                ->body('Você já registrou a finalização por parte do prestador.')
                ->warning()
                ->send();

            return;
        }

        DB::transaction(function () use ($order, $user): void {
            $order->update([
                'provider_completed_at' => now(),
                'repository_url' => trim($this->repositoryUrlInput) !== '' ? trim($this->repositoryUrlInput) : $order->repository_url,
            ]);

            $timeline = app(OrderTimelineService::class);
            $timeline->recordEvent(
                order: $order,
                eventType: 'provider_marked_done',
                title: 'Prestador Confirmou Finalização',
                message: 'O prestador definiu o projeto como finalizado.',
                actorId: $user->id,
            );

            $timeline->tryFinalizeOrderIfBothConfirmed($order, $user->id);
        });

        Notification::make()
            ->title('Finalização do prestador registrada!')
            ->success()
            ->send();

        $this->record->refresh();
    }

    public function sendProviderTurnMessage(): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        /** @var Order $order */
        $order = $this->record;
        /** @var User $user */
        $user = Auth::user();

        try {
            $editable = $this->editableProviderMessage();
            if ($editable !== null) {
                app(OrderTimelineService::class)->updateTurnMessage(
                    order: $order,
                    message: $editable,
                    editor: $user,
                    expectedRole: 'provider',
                    newContent: $this->turnMessageContent,
                );

                Notification::make()
                    ->title('Mensagem do turno editada!')
                    ->body('A versão anterior foi arquivada no histórico auditável.')
                    ->success()
                    ->send();
            } else {
                app(OrderTimelineService::class)->sendTurnMessage(
                    order: $order,
                    sender: $user,
                    senderRole: 'provider',
                    content: $this->turnMessageContent,
                );

                Notification::make()
                    ->title('Resposta enviada!')
                    ->body('A mensagem anterior do cliente foi congelada e seu turno está ativo.')
                    ->success()
                    ->send();
            }

            $this->record->refresh();
            $this->syncEditableProviderTurnContent();
        } catch (AppException $e) {
            Notification::make()
                ->title('Não foi possível enviar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function approveAdjustmentInScope(int $adjustmentId): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        /** @var Order $order */
        $order = $this->record;
        /** @var User $user */
        $user = Auth::user();

        $adjustment = OrderAdjustment::query()
            ->where('order_id', $order->id)
            ->whereKey($adjustmentId)
            ->first();

        if (! $adjustment || $adjustment->decision !== 'pending') {
            return;
        }

        DB::transaction(function () use ($adjustment, $order, $user): void {
            $adjustment->update([
                'decision' => 'approved_in_scope',
                'provider_notes' => 'Ajuste aprovado sem custo adicional (coberto pelo escopo contratual).',
                'resolved_at' => now(),
            ]);

            $order->update(['status' => 'in_revision']);

            app(OrderTimelineService::class)->recordEvent(
                order: $order,
                eventType: 'adjustment_approved',
                title: 'Ajuste Aprovado Dentro do Contrato',
                message: 'O prestador validou os apontamentos dentro do escopo contratado e iniciou a revisão sem custos extras.',
                payload: ['adjustment_id' => $adjustment->id],
                actorId: $user->id,
            );
        });

        Notification::make()
            ->title('Ajuste aprovado!')
            ->body('Status alterado para Em Revisão.')
            ->success()
            ->send();

        $this->record->refresh();
    }

    public function rejectAdjustmentOutOfScope(int $adjustmentId): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        /** @var Order $order */
        $order = $this->record;
        /** @var User $user */
        $user = Auth::user();

        $reason = trim((string) ($this->rejectionReasons[$adjustmentId] ?? ''));
        if ($reason === '') {
            Notification::make()
                ->title('Justificativa obrigatória')
                ->body('Explique ao cliente por que este item está fora do contrato antes de reprovar.')
                ->danger()
                ->send();

            return;
        }

        $adjustment = OrderAdjustment::query()
            ->where('order_id', $order->id)
            ->whereKey($adjustmentId)
            ->first();

        if (! $adjustment) {
            return;
        }

        DB::transaction(function () use ($adjustment, $order, $user, $reason): void {
            $adjustment->update([
                'decision' => 'rejected',
                'provider_notes' => $reason,
                'resolved_at' => now(),
            ]);

            $order->update(['status' => 'homologation']);

            app(OrderTimelineService::class)->recordEvent(
                order: $order,
                eventType: 'adjustment_rejected',
                title: 'Solicitação de Ajuste Reprovada (Fora do Contrato)',
                message: "O prestador reprovou o pedido de ajuste por não constar no escopo contratado. Motivo: {$reason}",
                payload: ['adjustment_id' => $adjustment->id],
                actorId: $user->id,
            );
        });

        unset($this->rejectionReasons[$adjustmentId]);

        Notification::make()
            ->title('Ajuste reprovado com justificativa')
            ->success()
            ->send();

        $this->record->refresh();
    }

    public function openAddendumModal(int $adjustmentId): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        $this->selectedAdjustmentId = $adjustmentId;
        $this->addendumForm = [
            'billing_mode' => 'fixed',
            'estimated_hours' => 4,
            'hourly_rate' => 80,
            'addendum_price' => '',
            'addendum_extra_days' => 3,
            'addendum_scope' => '',
            'provider_notes' => '',
        ];
        $this->showAddendumModal = true;
    }

    public function closeAddendumModal(): void
    {
        $this->showAddendumModal = false;
        $this->selectedAdjustmentId = null;
    }

    public function issueTermoAddendum(): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        if ($this->selectedAdjustmentId === null) {
            return;
        }

        /** @var Order $order */
        $order = $this->record;
        /** @var User $user */
        $user = Auth::user();

        $adjustment = OrderAdjustment::query()
            ->where('order_id', $order->id)
            ->whereKey($this->selectedAdjustmentId)
            ->first();

        if (! $adjustment) {
            return;
        }

        $billingMode = (string) ($this->addendumForm['billing_mode'] ?? 'fixed');
        $hours = max(1, (int) ($this->addendumForm['estimated_hours'] ?? 1));
        $hourlyRate = max(1.0, (float) ($this->addendumForm['hourly_rate'] ?? 80));
        $price = $billingMode === 'hourly'
            ? round($hours * $hourlyRate, 2)
            : (float) ($this->addendumForm['addendum_price'] ?? 0);

        $extraDays = max(0, (int) ($this->addendumForm['addendum_extra_days'] ?? 1));
        $scope = trim((string) ($this->addendumForm['addendum_scope'] ?? ''));

        if ($price <= 0 || $scope === '') {
            Notification::make()
                ->title('Preencha o escopo e o valor do aditivo')
                ->body('Informe detalhadamente o escopo adicional e um valor maior que zero.')
                ->danger()
                ->send();

            return;
        }

        $addendumCount = $order->adjustments()->whereNotNull('contract_reference')->count() + 1;
        $reference = $adjustment->contract_reference ?? sprintf('Termo Aditivo #%02d ao Contrato #ORD-%04d', $addendumCount, $order->id);

        $adjustment->contract_reference = $reference;
        $checkout = app(StripeCheckoutService::class)->createAddendumCheckout($order, $adjustment, $price);

        DB::transaction(function () use ($adjustment, $order, $user, $billingMode, $hours, $hourlyRate, $price, $extraDays, $scope, $reference, $checkout): void {
            $adjustment->update([
                'decision' => 'addendum_issued',
                'billing_mode' => $billingMode,
                'estimated_hours' => $billingMode === 'hourly' ? $hours : null,
                'hourly_rate' => $billingMode === 'hourly' ? $hourlyRate : null,
                'addendum_scope' => $scope,
                'addendum_price' => $price,
                'addendum_extra_days' => $extraDays,
                'provider_notes' => trim((string) ($this->addendumForm['provider_notes'] ?? '')) ?: null,
                'contract_reference' => $reference,
                'stripe_payment_link' => $checkout['url'],
                'stripe_session_id' => $checkout['session_id'],
                'payment_status' => 'pending',
            ]);

            $formatted = number_format($price, 2, ',', '.');
            app(OrderTimelineService::class)->recordEvent(
                order: $order,
                eventType: 'addendum_issued',
                title: "Termo Aditivo Emitido ({$reference})",
                message: "A solicitação excede o escopo original do contrato. Foi emitido o {$reference} no valor de R$ {$formatted} (+{$extraDays} dias úteis). O cliente pode pagar via Stripe, enviar contra-proposta ou recusar mantendo o escopo original.",
                payload: [
                    'adjustment_id' => $adjustment->id,
                    'reference' => $reference,
                    'price' => $price,
                    'extra_days' => $extraDays,
                    'payment_url' => $checkout['url'],
                ],
                actorId: $user->id,
            );
        });

        $this->closeAddendumModal();

        Notification::make()
            ->title('Termo Aditivo enviado ao cliente!')
            ->success()
            ->send();

        $this->record->refresh();
    }

    public function acceptAddendumCounterProposal(int $adjustmentId): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        /** @var Order $order */
        $order = $this->record;
        /** @var User $user */
        $user = Auth::user();

        $adjustment = OrderAdjustment::query()
            ->where('order_id', $order->id)
            ->whereKey($adjustmentId)
            ->first();

        if (! $adjustment || $adjustment->decision !== 'addendum_countered' || $adjustment->counter_price === null) {
            return;
        }

        $agreedPrice = (float) $adjustment->counter_price;
        $checkout = app(StripeCheckoutService::class)->createAddendumCheckout($order, $adjustment, $agreedPrice);

        DB::transaction(function () use ($adjustment, $order, $user, $agreedPrice, $checkout): void {
            $adjustment->update([
                'decision' => 'addendum_issued',
                'addendum_price' => $agreedPrice,
                'stripe_payment_link' => $checkout['url'],
                'stripe_session_id' => $checkout['session_id'],
                'payment_status' => 'pending',
            ]);

            $formatted = number_format($agreedPrice, 2, ',', '.');
            app(OrderTimelineService::class)->recordEvent(
                order: $order,
                eventType: 'addendum_issued',
                title: 'Contra-proposta do Termo Aditivo Aceita!',
                message: "O prestador aceitou o valor de R$ {$formatted} para o {$adjustment->contract_reference}. O novo link Stripe está liberado para pagamento.",
                payload: [
                    'adjustment_id' => $adjustment->id,
                    'price' => $agreedPrice,
                    'payment_url' => $checkout['url'],
                ],
                actorId: $user->id,
            );
        });

        Notification::make()
            ->title('Contra-proposta do aditivo aceita!')
            ->success()
            ->send();

        $this->record->refresh();
    }

    public function confirmAddendumPaymentManually(int $adjustmentId): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        /** @var Order $order */
        $order = $this->record;
        /** @var User $user */
        $user = Auth::user();

        $adjustment = OrderAdjustment::query()
            ->where('order_id', $order->id)
            ->whereKey($adjustmentId)
            ->first();

        if (! $adjustment || $adjustment->payment_status === 'paid') {
            return;
        }

        DB::transaction(function () use ($order, $adjustment, $user): void {
            $extraDays = (int) ($adjustment->addendum_extra_days ?? 0);
            $extraPrice = (float) ($adjustment->addendum_price ?? 0);

            $adjustment->update([
                'decision' => 'addendum_accepted',
                'payment_status' => 'paid',
                'resolved_at' => now(),
            ]);

            $newDeadline = $order->deadline !== null
                ? Carbon::parse($order->deadline)->addWeekdays($extraDays)
                : null;

            $order->update([
                'final_price' => ((float) ($order->final_price ?? 0)) + $extraPrice,
                'estimated_days' => ((int) ($order->estimated_days ?? 0)) + $extraDays,
                'deadline' => $newDeadline,
                'status' => 'in_revision',
            ]);

            app(OrderTimelineService::class)->recordEvent(
                order: $order,
                eventType: 'addendum_paid',
                title: 'Termo Aditivo Pago! Prazo Estendido',
                message: "Pagamento do {$adjustment->contract_reference} confirmado! Foram somados +{$extraDays} dias úteis ao prazo e o projeto entrou em revisão.",
                payload: [
                    'adjustment_id' => $adjustment->id,
                    'extra_days' => $extraDays,
                    'extra_price' => $extraPrice,
                ],
                actorId: $user->id,
            );
        });

        Notification::make()
            ->title('Pagamento do aditivo confirmado!')
            ->body('Prazo estendido e status movido para Em Revisão.')
            ->success()
            ->send();

        $this->record->refresh();
    }

    public function openMessageHistory(int $messageId): void
    {
        /** @var Order $order */
        $order = $this->record;
        $msg = OrderMessage::query()
            ->with(['versions.editedBy', 'sender'])
            ->where('order_id', $order->id)
            ->whereKey($messageId)
            ->first();

        if (! $msg) {
            return;
        }

        $maxV = (int) $msg->versions->max('version_number');

        $this->historyModalMessage = [
            'id' => $msg->id,
            'turn_number' => $msg->turn_number,
            'sender_name' => $msg->sender?->name ?? 'Usuário',
            'versions' => $msg->versions->map(static fn ($v): array => [
                'version_number' => $v->version_number,
                'content' => $v->content,
                'created_at' => $v->created_at?->format('d/m/Y H:i:s'),
                'is_current' => $v->version_number === $maxV,
            ])->values()->all(),
        ];
    }

    public function closeMessageHistory(): void
    {
        $this->historyModalMessage = null;
    }

    public function editableProviderMessage(): ?OrderMessage
    {
        /** @var Order $order */
        $order = $this->record;
        $latest = OrderMessage::query()
            ->where('order_id', $order->id)
            ->whereIn('sender_role', ['client', 'provider'])
            ->orderByDesc('id')
            ->first();

        if ($latest !== null && $latest->sender_role === 'provider' && ! $latest->is_locked) {
            return $latest;
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function roomTimeline(): array
    {
        /** @var Order $order */
        $order = $this->record;
        $serialized = app(OrderTimelineService::class)->serializeOrderRoom($order);

        /** @var list<array<string, mixed>> $timeline */
        $timeline = $serialized['timeline'] ?? [];

        return $timeline;
    }

    public function lastProposal(): ?Proposal
    {
        return $this->record->proposals()->latest('id')->first();
    }

    public function canSendProposal(): bool
    {
        if (! $this->canSubmitProposalByPermission()) {
            return false;
        }

        if (in_array($this->record->status, ['cancelled', 'rejected', 'done', 'in_progress', 'homologation', 'awaiting_development'], true)) {
            return false;
        }

        /** @var User $user */
        $user = Auth::user();
        $lastProposal = $this->lastProposal();

        if (! $lastProposal) {
            return $this->canManageOrders();
        }

        if ($lastProposal->is_accepted === true) {
            return false;
        }

        if ($lastProposal->is_accepted === null) {
            return false;
        }

        return $user->id !== $lastProposal->sender_id || $this->canManageOrders();
    }

    public function proposalActionLabel(): string
    {
        $lastProposal = $this->lastProposal();

        if (! $lastProposal) {
            return 'Enviar Proposta Inicial';
        }

        if ($lastProposal->is_accepted === false) {
            return 'Enviar Nova Contra-Oferta';
        }

        return 'Enviar Proposta';
    }

    public function sendBlockedMessage(): string
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $this->canSubmitProposalByPermission()) {
            return 'Você não possui permissão para enviar propostas.';
        }

        if ($this->agreedProposal() !== null) {
            return 'Acordo comercial já fechado! Não é necessário enviar novas propostas.';
        }

        $lastProposal = $this->lastProposal();

        if ($lastProposal?->is_accepted === null) {
            if ($lastProposal->sender_id === $user->id) {
                return 'Aguardando a decisão do cliente sobre a proposta enviada.';
            }

            return 'Responda à contra-proposta atual do cliente (Aceitar ou Recusar) antes de enviar nova oferta.';
        }

        return 'Negociação bloqueada no status atual do pedido.';
    }

    public function agreedProposal(): ?Proposal
    {
        return $this->record->proposals()
            ->where('is_accepted', true)
            ->whereNull('superseded_at')
            ->latest('id')
            ->first();
    }

    public function canGeneratePayment(): bool
    {
        return $this->isAdminSideUser() && $this->hasActiveAgreement();
    }

    public function canSeePaymentButton(): bool
    {
        return $this->isAdminSideUser() && $this->hasActiveAgreement() && $this->record->status === 'approved';
    }

    public function paymentLink(): ?string
    {
        return $this->record->stripe_checkout_url ?? $this->paymentProject()?->payment_link;
    }

    public function canSeePaymentLink(): bool
    {
        $paymentLink = $this->paymentLink();
        if (! $paymentLink) {
            return false;
        }

        /** @var User $user */
        $user = Auth::user();

        return $this->record->user_id === $user->id && $user->hasRole('client');
    }

    public function openPaymentModal(): void
    {
        $this->paymentMethod = null;
        $this->showPaymentModal = true;
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
    }

    public function confirmGeneratePayment(): void
    {
        if (! $this->canGeneratePayment()) {
            return;
        }

        $agreedProposal = $this->agreedProposal();
        if (! $agreedProposal) {
            return;
        }

        /** @var Order $order */
        $order = $this->record;
        $checkout = app(StripeCheckoutService::class)->createOrderCheckout($order, (float) $agreedProposal->price);

        Project::query()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'payment_link' => $checkout['url'],
                'payment_status' => 'pending',
                'current_progress' => 0,
            ],
        );

        $order->update([
            'stripe_checkout_url' => $checkout['url'],
            'stripe_session_id' => $checkout['session_id'],
            'status' => 'awaiting_payment',
        ]);

        $this->closePaymentModal();

        Notification::make()
            ->title('Link Stripe gerado com sucesso!')
            ->success()
            ->send();

        $this->record->refresh();
    }

    protected function syncEditableProviderTurnContent(): void
    {
        $editable = $this->editableProviderMessage();
        $this->turnMessageContent = $editable !== null ? $editable->current_content : '';
    }

    protected function isAdminSideUser(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->hasRole('admin') || $user->can(Permission::ManageOrders->value);
    }

    protected function hasActiveAgreement(): bool
    {
        return $this->agreedProposal() !== null;
    }

    protected function paymentProject(): ?Project
    {
        return Project::query()
            ->where('order_id', $this->record->id)
            ->first();
    }

    protected function canManageOrders(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->can(Permission::ManageOrders->value);
    }

    protected function canSubmitProposalByPermission(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->canAny([
            Permission::ManageOrders->value,
            Permission::SubmitProposals->value,
        ]);
    }

    protected function canRespondToProposalByPermission(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->canAny([
            Permission::ManageOrders->value,
            Permission::AcceptProposals->value,
        ]);
    }
}
