<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NegotiationPage extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.resources.orders.pages.negotiation-page';

    public ?array $proposalFormData = [];

    public bool $showPaymentModal = false;

    public ?string $paymentMethod = null;

    public function getHeading(): string
    {
        return $this->record?->title ?? 'Negociação';
    }

    public function getSubheading(): ?string
    {
        return 'Chat de Negociação';
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

        $data = $this->proposalFormData;

        if (empty($data['content']) || empty($data['price'])) {
            Notification::make()
                ->title('Erro')
                ->body('Conteúdo e preço são obrigatórios.')
                ->danger()
                ->send();

            return;
        }

        /** @var User $user */
        $user = Auth::user();

        Proposal::create([
            'order_id' => $this->record->id,
            'sender_id' => $user->id,
            'content' => $data['content'],
            'price' => $data['price'],
            'is_accepted' => null,
        ]);

        $this->record->update([
            'status' => 'proposal_sent',
        ]);

        Notification::make()
            ->title('Sucesso')
            ->body('Proposta enviada com sucesso!')
            ->success()
            ->send();

        $this->proposalFormData = [
            'content' => '',
            'price' => '',
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

        DB::transaction(function () use ($proposal): void {
            $this->record->proposals()
                ->where('is_accepted', true)
                ->whereNull('superseded_at')
                ->whereKeyNot($proposal->id)
                ->update([
                    'superseded_at' => now(),
                ]);

            $proposal->update([
                'is_accepted' => true,
                'superseded_at' => null,
            ]);
        });

        $this->record->update(['status' => 'approved']);

        Notification::make()
            ->title('Sucesso')
            ->body('Proposta aceita!')
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

        $proposal->update(['is_accepted' => false]);

        Notification::make()
            ->title('Sucesso')
            ->body('Proposta rejeitada.')
            ->success()
            ->send();

        $this->record->refresh();
    }

    public function lastProposal(): ?Proposal
    {
        return $this->record->proposals()->latest()->first();
    }

    public function canSendProposal(): bool
    {
        if (! $this->canSubmitProposalByPermission()) {
            return false;
        }

        /** @var User $user */
        $user = Auth::user();

        $lastProposal = $this->lastProposal();

        if (! $lastProposal) {
            return $this->canManageOrders();
        }

        if ($lastProposal->is_accepted === true) {
            return true;
        }

        if ($lastProposal->is_accepted === null) {
            return false;
        }

        return $user->id !== $lastProposal->sender_id;
    }

    public function proposalActionLabel(): string
    {
        $lastProposal = $this->lastProposal();

        if (! $lastProposal) {
            return 'Enviar análise';
        }

        if ($lastProposal->is_accepted === false) {
            return 'Fazer uma contra proposta';
        }

        if ($lastProposal->is_accepted === true) {
            return 'Solicitar alteração (nova proposta)';
        }

        return 'Enviar proposta';
    }

    public function sendBlockedMessage(): string
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $this->canSubmitProposalByPermission()) {
            return 'Você não possui permissão para enviar propostas.';
        }

        $lastProposal = $this->lastProposal();

        if (! $lastProposal && ! $this->canManageOrders()) {
            return 'Aguardando a análise inicial do administrador.';
        }

        if ($lastProposal?->is_accepted === null) {
            if ($lastProposal->sender_id === $user->id) {
                return 'Aguardando a resposta da outra parte para a proposta atual.';
            }

            return 'Responda à proposta atual (aceitar ou rejeitar) antes de enviar outra.';
        }

        return 'Aguardando a contra proposta da outra parte.';
    }

    public function agreedProposal(): ?Proposal
    {
        return $this->record->proposals()
            ->where('is_accepted', true)
            ->whereNull('superseded_at')
            ->latest()
            ->first();
    }

    public function canGeneratePayment(): bool
    {
        return $this->isAdminSideUser() && $this->hasActiveAgreement();
    }

    public function canSeePaymentButton(): bool
    {
        return $this->isAdminSideUser() && $this->hasActiveAgreement();
    }

    public function paymentButtonMessage(): string
    {
        if (! $this->isAdminSideUser()) {
            return 'Somente admin pode gerar pagamento.';
        }

        return 'Gerar link de pagamento com o valor acordado.';
    }

    public function paymentLink(): ?string
    {
        return $this->paymentProject()?->payment_link;
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
        if (! $this->canGeneratePayment()) {
            Notification::make()
                ->title('Ação não permitida')
                ->body('Somente o admin pode gerar pagamento após um acordo ativo.')
                ->danger()
                ->send();

            return;
        }

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
            Notification::make()
                ->title('Ação não permitida')
                ->body('Não há acordo ativo para gerar pagamento.')
                ->danger()
                ->send();

            return;
        }

        if (! in_array($this->paymentMethod, ['pix', 'card', 'boleto'], true)) {
            Notification::make()
                ->title('Método inválido')
                ->body('Selecione Pix, Cartão ou Boleto.')
                ->danger()
                ->send();

            return;
        }

        $agreedProposal = $this->agreedProposal();

        if (! $agreedProposal) {
            Notification::make()
                ->title('Sem acordo ativo')
                ->body('Não foi possível localizar a proposta acordada.')
                ->danger()
                ->send();

            return;
        }

        $paymentLink = $this->generatePayment(
            $this->record,
            $this->paymentMethod,
            (float) $agreedProposal->price,
        );

        Project::query()->updateOrCreate(
            ['order_id' => $this->record->id],
            [
                'payment_link' => $paymentLink,
                'payment_status' => 'pending',
                'current_progress' => 0,
            ],
        );

        $this->record->update([
            'status' => 'awaiting_payment',
        ]);

        $this->closePaymentModal();

        Notification::make()
            ->title('Pagamento gerado')
            ->body('Link de pagamento criado com sucesso.')
            ->success()
            ->send();

        $this->record->refresh();
    }

    protected function generatePayment(Order $order, string $paymentMethod, float $amount): string
    {
        return '#';
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
