<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
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
        /** @var User $user */
        $user = Auth::user();

        $lastProposal = $this->lastProposal();

        if (! $lastProposal) {
            return $user->hasRole('admin');
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

        $lastProposal = $this->lastProposal();

        if (! $lastProposal && ! $user->hasRole('admin')) {
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
}
