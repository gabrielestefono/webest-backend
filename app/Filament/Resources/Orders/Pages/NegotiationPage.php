<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\Permission;
use App\Exceptions\AppException;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderMessage;
use App\Models\OrderMessageVersion;
use App\Models\Product;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use App\Services\OrderTimelineService;
use App\Services\StripeCheckoutService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Collection;
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
    ];

    public string $turnMessageContent = '';

    public string $stagingUrlInput = '';

    public string $repositoryUrlInput = '';

    public bool $showPaymentModal = false;

    public ?string $paymentMethod = null;

    public bool $showSpawnOrderModal = false;

    public ?int $selectedAdjustmentId = null;

    /**
     * @var array{product_ids: list<int>, title: string, provider_notes: string}
     */
    public array $spawnOrderForm = [
        'product_ids' => [],
        'title' => '',
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

        $order = $this->orderRecord();
        $this->stagingUrlInput = (string) ($order->staging_url ?? '');
        $this->repositoryUrlInput = (string) ($order->repository_url ?? '');
        $this->turnMessageContent = '';
    }

    public function getHeading(): string
    {
        $record = $this->record;

        return $record instanceof Order
            ? sprintf('#ORD-%04d — %s', $record->id, $record->title)
            : 'Operação do Pedido';
    }

    public function getSubheading(): ?string
    {
        return 'Central de Operação do Prestador: Escopo Obrigatório, Negociação Ping-Pong, Chat e Triagem de Ajustes';
    }

    public function hasMandatoryScope(): bool
    {
        return $this->orderRecord()->hasCompleteScope();
    }

    /**
     * @return Collection<int, Product>
     */
    public function availableCatalogProducts(): Collection
    {
        return Product::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();
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
        $contentInput = isset($data['content']) && is_string($data['content']) ? trim($data['content']) : '';
        $priceInput = isset($data['price']) && is_numeric($data['price']) ? (float) $data['price'] : 0.0;

        if ($contentInput === '' || $priceInput <= 0) {
            Notification::make()
                ->title('Dados obrigatórios')
                ->body('Informe a descrição comercial e um valor válido maior que zero.')
                ->danger()
                ->send();

            return;
        }

        $user = $this->currentAuthUser();
        $order = $this->orderRecord();
        $estimatedDaysRaw = isset($data['estimated_days']) && is_numeric($data['estimated_days'])
            ? (int) $data['estimated_days']
            : 7;
        $days = max(1, $estimatedDaysRaw);

        DB::transaction(function () use ($order, $user, $contentInput, $priceInput, $days): void {
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
                'content' => $contentInput,
                'price' => $priceInput,
                'estimated_days' => $days,
                'is_accepted' => null,
                'status' => 'pending',
            ]);

            $order->update([
                'status' => 'proposal_negotiation',
                'contract_url' => null,
            ]);

            $formatted = number_format($priceInput, 2, ',', '.');
            app(OrderTimelineService::class)->recordEvent(
                order: $order,
                eventType: 'proposal_sent',
                title: 'Oferta Comercial Enviada pelo Prestador',
                message: "O prestador fez uma oferta de R$ {$formatted} pelo serviço com prazo estimado de {$days} dias úteis. O Escopo do Serviço estruturado já está disponível para consulta em PDF.",
                payload: [
                    'proposal_id' => $proposal->id,
                    'price' => $priceInput,
                    'estimated_days' => $days,
                ],
                actorId: $user->id,
            );
        });

        Notification::make()
            ->title('Proposta enviada!')
            ->body('O cliente já pode consultar o Escopo em PDF, aceitar ou enviar contra-proposta na sala do pedido.')
            ->success()
            ->send();

        $this->proposalFormData = [
            'content' => '',
            'price' => '',
            'estimated_days' => $days,
        ];

        $order->refresh();
    }

    public function acceptProposal(Proposal $proposal): void
    {
        $order = $this->orderRecord();

        abort_unless($this->canRespondToProposalByPermission(), 403);
        abort_unless($proposal->order_id === $order->id, 403);
        abort_unless($proposal->is_accepted === null, 403);

        $user = $this->currentAuthUser();
        abort_unless($proposal->sender_id !== $user->id, 403);

        $lastProposal = $this->lastProposal();
        abort_unless($lastProposal?->id === $proposal->id, 403);

        if (! $order->hasCompleteScope()) {
            Notification::make()
                ->title('Escopo do Serviço obrigatório')
                ->body('Cadastre obrigatoriamente o Escopo do Serviço antes de aceitar a contra-proposta do cliente.')
                ->danger()
                ->send();

            return;
        }

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

            $order->update([
                'status' => 'awaiting_contract',
                'final_price' => $proposal->price,
                'estimated_days' => $proposal->estimated_days ?: 7,
                'contract_url' => null,
            ]);

            $formatted = number_format((float) $proposal->price, 2, ',', '.');
            app(OrderTimelineService::class)->recordEvent(
                order: $order,
                eventType: 'contract_available',
                title: 'Contra-proposta Aceita pelo Prestador!',
                message: "O prestador aceitou a contra-proposta de R$ {$formatted} ({$proposal->estimated_days} dias úteis)! O PDF consolidado com o Escopo do Serviço e histórico está disponível para download e assinatura digital do cliente.",
                payload: [
                    'proposal_id' => $proposal->id,
                    'price' => (float) $proposal->price,
                    'estimated_days' => $proposal->estimated_days,
                ],
                actorId: $user->id,
            );
        });

        Notification::make()
            ->title('Contra-proposta aceita!')
            ->body('Contrato consolidado liberado para assinatura digital do cliente.')
            ->success()
            ->send();

        $order->refresh();
    }

    public function rejectProposal(Proposal $proposal): void
    {
        $order = $this->orderRecord();

        abort_unless($this->canRespondToProposalByPermission(), 403);
        abort_unless($proposal->order_id === $order->id, 403);
        abort_unless($proposal->is_accepted === null, 403);

        $user = $this->currentAuthUser();
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

        $order->refresh();
    }

    public function cancelOrderAsProvider(): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        $order = $this->orderRecord();
        $user = $this->currentAuthUser();

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

        $order->refresh();
    }

    public function confirmPaymentManually(): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        $order = $this->orderRecord();
        $user = $this->currentAuthUser();

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

        $order->refresh();
    }

    public function startDevelopmentManually(): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        $order = $this->orderRecord();
        $user = $this->currentAuthUser();

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

        $order->refresh();
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

        $order = $this->orderRecord();
        $user = $this->currentAuthUser();
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

        $order->refresh();
    }

    public function confirmProviderCompletion(): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        $order = $this->orderRecord();
        $user = $this->currentAuthUser();

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

        $order->refresh();
    }

    public function sendProviderTurnMessage(): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        $content = trim($this->turnMessageContent);
        if ($content === '') {
            Notification::make()
                ->title('Mensagem vazia')
                ->body('Digite uma mensagem antes de enviar.')
                ->warning()
                ->send();

            return;
        }

        $order = $this->orderRecord();
        $user = $this->currentAuthUser();

        try {
            OrderMessage::query()
                ->where('order_id', $order->id)
                ->where('sender_role', 'provider')
                ->where('is_locked', false)
                ->update(['is_locked' => true]);

            app(OrderTimelineService::class)->sendTurnMessage(
                order: $order,
                sender: $user,
                senderRole: 'provider',
                content: $content,
            );

            Notification::make()
                ->title('Mensagem enviada!')
                ->body('Sua resposta foi enviada ao cliente com sucesso.')
                ->success()
                ->send();

            $this->turnMessageContent = '';
            $order->refresh();
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

        $order = $this->orderRecord();
        $user = $this->currentAuthUser();

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

        $order->refresh();
    }

    public function rejectAdjustmentOutOfScope(int $adjustmentId): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        $order = $this->orderRecord();
        $user = $this->currentAuthUser();

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

        if (! $adjustment || $adjustment->decision !== 'pending') {
            return;
        }

        DB::transaction(function () use ($adjustment, $order, $user, $reason): void {
            $adjustment->update([
                'decision' => 'rejected',
                'provider_notes' => $reason,
                'resolved_at' => now(),
            ]);

            $this->restoreOrderAfterAdjustmentTriage($order);

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

        $order->refresh();
    }

    public function openSpawnOrderModal(int $adjustmentId): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        $order = $this->orderRecord();
        $adjustment = OrderAdjustment::query()
            ->where('order_id', $order->id)
            ->whereKey($adjustmentId)
            ->first();

        if (! $adjustment || $adjustment->decision !== 'pending') {
            return;
        }

        $this->selectedAdjustmentId = $adjustmentId;
        $this->spawnOrderForm = [
            'product_ids' => [],
            'title' => "Serviço Adicional — {$order->title}",
            'provider_notes' => '',
        ];
        $this->showSpawnOrderModal = true;
    }

    public function closeSpawnOrderModal(): void
    {
        $this->showSpawnOrderModal = false;
        $this->selectedAdjustmentId = null;
    }

    public function toggleSpawnProduct(int $productId): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        /** @var list<int> $normalized */
        $normalized = [];
        foreach ($this->spawnOrderForm['product_ids'] as $rawId) {
            $id = (int) $rawId;
            if ($id > 0 && ! in_array($id, $normalized, true)) {
                $normalized[] = $id;
            }
        }

        if (in_array($productId, $normalized, true)) {
            $normalized = array_values(array_filter(
                $normalized,
                static fn (int $existingId): bool => $existingId !== $productId,
            ));
        } else {
            $normalized[] = $productId;
        }

        $this->spawnOrderForm['product_ids'] = $normalized;
    }

    public function createNewOrderFromAdjustment(): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        if ($this->selectedAdjustmentId === null) {
            return;
        }

        $order = $this->orderRecord();
        $user = $this->currentAuthUser();

        $adjustment = OrderAdjustment::query()
            ->where('order_id', $order->id)
            ->whereKey($this->selectedAdjustmentId)
            ->first();

        if (! $adjustment || $adjustment->decision !== 'pending') {
            return;
        }

        /** @var list<int> $productIds */
        $productIds = [];
        foreach ($this->spawnOrderForm['product_ids'] as $rawId) {
            $id = (int) $rawId;
            if ($id > 0 && ! in_array($id, $productIds, true)) {
                $productIds[] = $id;
            }
        }

        $title = trim($this->spawnOrderForm['title']);
        $providerNotes = trim($this->spawnOrderForm['provider_notes']);

        if ($productIds === []) {
            Notification::make()
                ->title('Selecione pelo menos 1 produto')
                ->body('Escolha o(s) produto(s) do catálogo que compõem o novo pedido fora do escopo (ex: Deploy / VPS).')
                ->danger()
                ->send();

            return;
        }

        if ($title === '' || $providerNotes === '') {
            Notification::make()
                ->title('Campos obrigatórios')
                ->body('Informe o título do novo pedido e a explicação técnica que será enviada ao cliente.')
                ->danger()
                ->send();

            return;
        }

        $selectedProducts = Product::query()
            ->where('active', true)
            ->whereIn('id', $productIds)
            ->get();

        if ($selectedProducts->isEmpty()) {
            Notification::make()
                ->title('Produto inválido')
                ->body('Nenhum produto ativo foi encontrado para a seleção informada.')
                ->danger()
                ->send();

            return;
        }

        $newOrder = DB::transaction(function () use ($order, $user, $adjustment, $selectedProducts, $title, $providerNotes): Order {
            $primaryProduct = $selectedProducts->first();
            $parentCode = sprintf('#ORD-%04d', $order->id);
            /** @var list<string> $productNamesList */
            $productNamesList = $selectedProducts->pluck('name')
                ->map(static fn (mixed $name): string => (string) $name)
                ->values()
                ->all();
            $productNames = implode(', ', $productNamesList);

            /** @var list<int> $selectedProductIds */
            $selectedProductIds = $selectedProducts->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all();

            $newOrderDescription = "Pedido criado a partir de solicitação fora do escopo no pedido {$parentCode} ({$order->title}).\n\n"
                ."Solicitação original do cliente:\n{$adjustment->client_description}\n\n"
                ."Explicação técnica da equipe:\n{$providerNotes}";

            $createdOrder = Order::query()->create([
                'user_id' => $order->user_id,
                'parent_order_id' => $order->id,
                'product_id' => $primaryProduct?->id,
                'title' => $title,
                'description' => $newOrderDescription,
                'figma_url' => $order->figma_url,
                'status' => 'under_review',
            ]);

            $createdOrder->products()->sync($selectedProductIds);

            $timeline = app(OrderTimelineService::class);
            $newOrderCode = sprintf('#ORD-%04d', $createdOrder->id);
            $newOrderPath = "/dashboard/pedidos/{$createdOrder->id}";

            $timeline->recordEvent(
                order: $createdOrder,
                eventType: 'order_submitted',
                title: "Novo Pedido Aberto a partir de {$parentCode}",
                message: "Este pedido foi aberto pela equipe técnica para atender a um item fora do escopo contratado em {$parentCode}. Produtos selecionados: {$productNames}. Status atual: Em análise.",
                payload: [
                    'parent_order_id' => $order->id,
                    'parent_order_code' => $parentCode,
                    'product_ids' => $selectedProductIds,
                ],
                actorId: $user->id,
            );

            $timeline->sendTurnMessage(
                order: $createdOrder,
                sender: $user,
                senderRole: 'provider',
                content: "Olá! Abrimos este novo pedido ({$productNames}) para tratar o item fora do escopo solicitado no pedido {$parentCode}.\n\nOrientação técnica:\n{$providerNotes}\n\nVamos definir o Escopo do Serviço e enviar a proposta comercial por aqui.",
            );

            $adjustment->update([
                'decision' => 'redirected_to_new_order',
                'spawned_order_id' => $createdOrder->id,
                'provider_notes' => $providerNotes,
                'resolved_at' => now(),
            ]);

            $this->restoreOrderAfterAdjustmentTriage($order);

            $timeline->recordEvent(
                order: $order,
                eventType: 'adjustment_redirected_to_order',
                title: "Fora do Escopo: Novo Pedido {$newOrderCode} Criado",
                message: "Identificamos que sua solicitação envolve item fora do Escopo do Serviço contratado ({$productNames}). Orientação da equipe: \"{$providerNotes}\". Para seguir o fluxo completo do zero com escopo e orçamento dedicados, abrimos o pedido {$newOrderCode} — {$createdOrder->title}.",
                payload: [
                    'adjustment_id' => $adjustment->id,
                    'new_order_id' => $createdOrder->id,
                    'new_order_code' => $newOrderCode,
                    'new_order_title' => $createdOrder->title,
                    'new_order_path' => $newOrderPath,
                    'product_names' => $productNamesList,
                ],
                actorId: $user->id,
            );

            return $createdOrder;
        });

        $this->closeSpawnOrderModal();

        Notification::make()
            ->title(sprintf('Novo pedido #ORD-%04d criado!', $newOrder->id))
            ->body('O cliente já recebeu a orientação e o link direto para o novo pedido no chat.')
            ->success()
            ->send();

        $order->refresh();
    }

    public function openMessageHistory(int $messageId): void
    {
        $order = $this->orderRecord();
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
            'versions' => $msg->versions->map(static fn (OrderMessageVersion $v): array => [
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
        $order = $this->orderRecord();
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
        $order = $this->orderRecord();
        $serialized = app(OrderTimelineService::class)->serializeOrderRoom($order);

        /** @var list<array<string, mixed>> $timeline */
        $timeline = $serialized['timeline'] ?? [];

        return $timeline;
    }

    public function lastProposal(): ?Proposal
    {
        return $this->orderRecord()->proposals()->latest('id')->first();
    }

    public function canSendProposal(): bool
    {
        if (! $this->canSubmitProposalByPermission()) {
            return false;
        }

        $order = $this->orderRecord();

        if (in_array($order->status, ['cancelled', 'rejected', 'done', 'in_progress', 'homologation', 'awaiting_development'], true)) {
            return false;
        }

        if (! $order->hasCompleteScope()) {
            return false;
        }

        $user = $this->currentAuthUser();
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
        $user = $this->currentAuthUser();

        if (! $this->canSubmitProposalByPermission()) {
            return 'Você não possui permissão para enviar propostas.';
        }

        if ($this->agreedProposal() !== null) {
            return 'Acordo comercial já fechado! Não é necessário enviar novas propostas.';
        }

        if (! $this->hasMandatoryScope()) {
            return 'Cadastre obrigatoriamente o Escopo do Serviço antes de enviar a proposta comercial.';
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
        return $this->orderRecord()->proposals()
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
        return $this->isAdminSideUser() && $this->hasActiveAgreement() && $this->orderRecord()->status === 'approved';
    }

    public function paymentLink(): ?string
    {
        return $this->orderRecord()->stripe_checkout_url ?? $this->paymentProject()?->payment_link;
    }

    public function canSeePaymentLink(): bool
    {
        $paymentLink = $this->paymentLink();
        if (! $paymentLink) {
            return false;
        }

        $user = $this->currentAuthUser();

        return $this->orderRecord()->user_id === $user->id && $user->hasRole('client');
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

        $order = $this->orderRecord();
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

        $order->refresh();
    }

    protected function syncEditableProviderTurnContent(): void
    {
        $editable = $this->editableProviderMessage();
        $this->turnMessageContent = $editable !== null ? $editable->current_content : '';
    }

    protected function isAdminSideUser(): bool
    {
        $user = $this->currentAuthUser();

        return $user->hasRole('admin') || $user->can(Permission::ManageOrders->value);
    }

    protected function hasActiveAgreement(): bool
    {
        return $this->agreedProposal() !== null;
    }

    protected function paymentProject(): ?Project
    {
        return Project::query()
            ->where('order_id', $this->orderRecord()->id)
            ->first();
    }

    protected function canManageOrders(): bool
    {
        return $this->currentAuthUser()->can(Permission::ManageOrders->value);
    }

    protected function canSubmitProposalByPermission(): bool
    {
        return $this->currentAuthUser()->canAny([
            Permission::ManageOrders->value,
            Permission::SubmitProposals->value,
        ]);
    }

    protected function canRespondToProposalByPermission(): bool
    {
        return $this->currentAuthUser()->canAny([
            Permission::ManageOrders->value,
            Permission::AcceptProposals->value,
        ]);
    }

    private function restoreOrderAfterAdjustmentTriage(Order $order): void
    {
        if ($order->warranty_until !== null && $order->provider_completed_at !== null) {
            $order->update([
                'status' => 'done',
                'client_completed_at' => $order->client_completed_at ?? now(),
            ]);

            return;
        }

        $order->update([
            'status' => 'homologation',
        ]);
    }

    private function orderRecord(): Order
    {
        $record = $this->record;
        assert($record instanceof Order);

        return $record;
    }

    private function currentAuthUser(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
