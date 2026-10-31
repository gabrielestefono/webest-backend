<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderScope;
use App\Models\User;
use App\Services\OrderTimelineService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderScopePage extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.resources.orders.pages.order-scope-page';

    /**
     * @var array{
     *     included_items: list<string>,
     *     excluded_items: list<string>,
     *     delivery_criteria: list<string>,
     *     revision_rules: list<string>,
     *     client_responsibilities: list<string>
     * }
     */
    public array $scopeForm = [
        'included_items' => [''],
        'excluded_items' => [''],
        'delivery_criteria' => [''],
        'revision_rules' => [''],
        'client_responsibilities' => [''],
    ];

    /**
     * @return array<string, array{label: string, subtitle: string, placeholder: string, accent: string}>
     */
    public static function sectionDefinitions(): array
    {
        return [
            'included_items' => [
                'label' => 'O que será feito',
                'subtitle' => 'Entregáveis, páginas, funcionalidades e integrações contempladas neste serviço.',
                'placeholder' => 'Ex: Desenvolvimento de uma landing page responsiva (desktop e mobile).',
                'accent' => '#22c55e',
            ],
            'excluded_items' => [
                'label' => 'O que NÃO será feito',
                'subtitle' => 'Itens explicitamente fora do escopo para evitar ambiguidades.',
                'placeholder' => 'Ex: Desenvolvimento de painel administrativo ou criação de identidade visual.',
                'accent' => '#ef4444',
            ],
            'delivery_criteria' => [
                'label' => 'Critérios de entrega',
                'subtitle' => 'Condições objetivas para validar que o serviço foi entregue conforme combinado.',
                'placeholder' => 'Ex: Página acessível por URL pública com formulário enviando corretamente.',
                'accent' => '#38bdf8',
            ],
            'revision_rules' => [
                'label' => 'Revisões',
                'subtitle' => 'Quantidade de rodadas e regras de ajustes incluídas no escopo.',
                'placeholder' => 'Ex: Até 2 rodadas de ajustes respeitando o escopo contratado.',
                'accent' => '#f59e0b',
            ],
            'client_responsibilities' => [
                'label' => 'Responsabilidades do cliente',
                'subtitle' => 'Insumos, acessos e validações sob responsabilidade do contratante.',
                'placeholder' => 'Ex: Fornecer textos, imagens finais e validar a homologação.',
                'accent' => '#a855f7',
            ],
        ];
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless($this->isAdminSideUser(), 403);

        $order = $this->orderRecord();
        $scope = $order->scope()->first();

        if ($scope instanceof OrderScope) {
            $this->scopeForm = [
                'included_items' => $this->ensureAtLeastOneInput($scope->normalizedIncludedItems()),
                'excluded_items' => $this->ensureAtLeastOneInput($scope->normalizedExcludedItems()),
                'delivery_criteria' => $this->ensureAtLeastOneInput($scope->normalizedDeliveryCriteria()),
                'revision_rules' => $this->ensureAtLeastOneInput($scope->normalizedRevisionRules()),
                'client_responsibilities' => $this->ensureAtLeastOneInput($scope->normalizedClientResponsibilities()),
            ];
        }
    }

    public function getHeading(): string
    {
        return 'Escopo do Serviço';
    }

    public function getSubheading(): ?string
    {
        $order = $this->orderRecord();

        return sprintf('Pedido #ORD-%04d • %s', $order->id, $order->title);
    }

    public function isScopeLocked(): bool
    {
        $order = $this->orderRecord();
        if ($order->contract_signed_at !== null) {
            return true;
        }

        $scope = $order->scope()->first();

        return $scope instanceof OrderScope && $scope->locked_at !== null;
    }

    public function addItem(string $section): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        if ($this->isScopeLocked() || ! array_key_exists($section, self::sectionDefinitions())) {
            return;
        }

        /** @var list<string> $current */
        $current = array_values($this->scopeForm[$section]);
        $current[] = '';
        $this->scopeForm[$section] = $current;
    }

    public function removeItem(string $section, int $index): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        if ($this->isScopeLocked() || ! array_key_exists($section, self::sectionDefinitions())) {
            return;
        }

        /** @var list<string> $current */
        $current = array_values($this->scopeForm[$section]);
        if (! array_key_exists($index, $current)) {
            return;
        }

        unset($current[$index]);
        $reindexed = array_values($current);

        $this->scopeForm[$section] = $reindexed !== [] ? $reindexed : [''];
    }

    public function saveScope(bool $redirectToNegotiation = false): void
    {
        abort_unless($this->isAdminSideUser(), 403);

        if ($this->isScopeLocked()) {
            Notification::make()
                ->title('Escopo congelado')
                ->body('O contrato deste pedido já foi assinado digitalmente pelo cliente e o escopo não pode mais ser alterado.')
                ->danger()
                ->send();

            return;
        }

        $definitions = self::sectionDefinitions();
        $normalized = [
            'included_items' => OrderScope::normalizeItems($this->scopeForm['included_items']),
            'excluded_items' => OrderScope::normalizeItems($this->scopeForm['excluded_items']),
            'delivery_criteria' => OrderScope::normalizeItems($this->scopeForm['delivery_criteria']),
            'revision_rules' => OrderScope::normalizeItems($this->scopeForm['revision_rules']),
            'client_responsibilities' => OrderScope::normalizeItems($this->scopeForm['client_responsibilities']),
        ];

        /** @var list<string> $missingLabels */
        $missingLabels = [];
        foreach ($definitions as $key => $meta) {
            if ($normalized[$key] === []) {
                $missingLabels[] = $meta['label'];
            }
        }

        if ($missingLabels !== []) {
            Notification::make()
                ->title('Preencha todas as seções obrigatórias')
                ->body('Adicione pelo menos 1 item válido em: '.implode(', ', $missingLabels).'.')
                ->danger()
                ->send();

            return;
        }

        $order = $this->orderRecord();
        $user = $this->currentAuthUser();
        $hadCompleteScopeBefore = $order->hasCompleteScope();

        DB::transaction(function () use ($order, $user, $normalized, $hadCompleteScopeBefore): void {
            OrderScope::query()->updateOrCreate(
                ['order_id' => $order->id],
                [
                    'included_items' => $normalized['included_items'],
                    'excluded_items' => $normalized['excluded_items'],
                    'delivery_criteria' => $normalized['delivery_criteria'],
                    'revision_rules' => $normalized['revision_rules'],
                    'client_responsibilities' => $normalized['client_responsibilities'],
                    'updated_by_id' => $user->id,
                ]
            );

            app(OrderTimelineService::class)->recordEvent(
                order: $order,
                eventType: $hadCompleteScopeBefore ? 'scope_updated' : 'scope_defined',
                title: $hadCompleteScopeBefore ? 'Escopo do Serviço Atualizado' : 'Escopo do Serviço Definido',
                message: $hadCompleteScopeBefore
                    ? 'O prestador atualizou os itens estruturados do Escopo do Serviço deste pedido.'
                    : 'O prestador cadastrou o Escopo do Serviço oficial deste pedido (o que será feito, o que não será feito, critérios de entrega, revisões e responsabilidades).',
                actorId: $user->id,
            );
        });

        $this->scopeForm = [
            'included_items' => $normalized['included_items'],
            'excluded_items' => $normalized['excluded_items'],
            'delivery_criteria' => $normalized['delivery_criteria'],
            'revision_rules' => $normalized['revision_rules'],
            'client_responsibilities' => $normalized['client_responsibilities'],
        ];

        $order->unsetRelation('scope');
        $order->refresh();

        Notification::make()
            ->title('Escopo do Serviço salvo com sucesso!')
            ->body('A proposta comercial já pode ser enviada ao cliente.')
            ->success()
            ->send();

        if ($redirectToNegotiation) {
            $this->redirect(OrderResource::getUrl('edit', ['record' => $order]));
        }
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private function ensureAtLeastOneInput(array $items): array
    {
        return $items !== [] ? $items : [''];
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

    private function isAdminSideUser(): bool
    {
        $user = $this->currentAuthUser();

        return $user->hasRole('admin') || $user->can(Permission::ManageOrders->value);
    }
}

