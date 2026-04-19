<x-filament-panels::page>
    @php
        $lastProposal = $this->lastProposal();
    @endphp

    <div style="display: flex; flex-direction: column; gap: 16px;">
        <div style="border: 1px solid #d1d5db; border-radius: 8px; background: #ffffff; padding: 16px;">
            <h2 style="margin: 0 0 12px 0; font-size: 18px; font-weight: 700; color: #111827;">Informações do Pedido</h2>

            <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">
                <div>
                    <div style="font-size: 12px; color: #6b7280;">Título</div>
                    <div style="font-size: 14px; font-weight: 700; color: #111827;">{{ $record->title }}</div>
                </div>

                <div>
                    <div style="font-size: 12px; color: #6b7280;">Status</div>
                    <div style="font-size: 14px; font-weight: 700; color: #111827;">
                        {{ \App\Models\Order::statusOptions()[$record->status] ?? $record->status }}
                    </div>
                </div>

                <div>
                    <div style="font-size: 12px; color: #6b7280;">Cliente</div>
                    <div style="font-size: 14px; font-weight: 700; color: #111827;">{{ $record->user->name }}</div>
                </div>

                <div>
                    <div style="font-size: 12px; color: #6b7280;">Produto</div>
                    <div style="font-size: 14px; font-weight: 700; color: #111827;">{{ $record->product->name }}</div>
                </div>

                <div style="grid-column: 1 / -1;">
                    <div style="font-size: 12px; color: #6b7280;">Descrição</div>
                    <div style="font-size: 14px; color: #374151; white-space: pre-wrap;">{{ $record->description }}</div>
                </div>
            </div>
        </div>

        <div style="margin-top: 8px;">
            <h2 style="margin: 0 0 12px 0; font-size: 22px; font-weight: 700; color: #111827;">Histórico de Negociação</h2>

            @forelse($record->proposals()->with('sender')->orderBy('created_at', 'desc')->get() as $proposal)
                @php
                    $isCurrentUser = $proposal->sender_id === auth()->id();
                    $isAdmin = $proposal->sender->hasRole('admin');
                @endphp

                @if($isCurrentUser)
                    <div style="border: 1px solid #d1d5db; border-radius: 8px; background: #ffffff; padding: 12px; margin: 0 0 10px 48px;">
                @else
                    <div style="border: 1px solid #d1d5db; border-radius: 8px; background: #ffffff; padding: 12px; margin: 0 48px 10px 0;">
                @endif
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            @if($isAdmin)
                                <div style="height: 40px; width: 40px; border-radius: 9999px; display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 12px; font-weight: 700; background: #dc2626;">
                            @else
                                <div style="height: 40px; width: 40px; border-radius: 9999px; display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 12px; font-weight: 700; background: #2563eb;">
                            @endif
                                {{ strtoupper(substr($proposal->sender->name, 0, 1)) }}
                            </div>

                            <div>
                                <div style="font-size: 13px; font-weight: 700; color: #111827;">
                                    {{ $proposal->sender->name }}
                                    @if($isAdmin)
                                        <span style="margin-left: 6px; padding: 2px 8px; border-radius: 9999px; background: #fee2e2; color: #991b1b; font-size: 11px;">Admin</span>
                                    @endif
                                </div>
                                <div style="font-size: 12px; color: #6b7280;">{{ $proposal->created_at->format('d/m/Y H:i') }}</div>
                            </div>
                        </div>

                        <div>
                            @if($proposal->is_accepted === true && $proposal->superseded_at)
                                <span style="padding: 2px 8px; border-radius: 9999px; background: #fef3c7; color: #92400e; font-size: 11px;">↺ Alterada</span>
                            @elseif($proposal->is_accepted === true)
                                <span style="padding: 2px 8px; border-radius: 9999px; background: #dcfce7; color: #166534; font-size: 11px;">✓ Aceita</span>
                            @elseif($proposal->is_accepted === false)
                                <span style="padding: 2px 8px; border-radius: 9999px; background: #fee2e2; color: #991b1b; font-size: 11px;">✗ Rejeitada</span>
                            @endif
                        </div>
                    </div>

                    <div style="margin-top: 8px; font-size: 14px; color: #374151; white-space: pre-wrap;">
                        {{ $proposal->content }}
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px;">
                        <div style="font-size: 14px; font-weight: 700; color: #111827;">
                            R$ {{ number_format($proposal->price, 2, ',', '.') }}
                        </div>

                        @if($proposal->is_accepted === null)
                            @php
                                $userCanRespond = auth()->id() !== $proposal->sender_id && $lastProposal?->id === $proposal->id;
                            @endphp

                            @if($userCanRespond)
                                <div style="display: flex; gap: 8px;">
                                    <button
                                        type="button"
                                        wire:click="acceptProposal({{ $proposal->id }})"
                                        style="border: none; border-radius: 6px; background: #16a34a; color: #ffffff; padding: 6px 12px; font-size: 12px; cursor: pointer;"
                                    >
                                        Aceitar
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="rejectProposal({{ $proposal->id }})"
                                        style="border: none; border-radius: 6px; background: #dc2626; color: #ffffff; padding: 6px 12px; font-size: 12px; cursor: pointer;"
                                    >
                                        Rejeitar
                                    </button>
                                </div>
                            @elseif($lastProposal?->id === $proposal->id)
                                <span style="font-size: 12px; color: #6b7280;">Aguardando resposta da outra parte.</span>
                            @endif
                        @endif
                    </div>
                </div>
            @empty
                <div style="border: 1px solid #d1d5db; border-radius: 8px; background: #f9fafb; padding: 16px; text-align: center; color: #6b7280;">
                    Nenhuma proposta ainda. Envie a primeira proposta abaixo para iniciar a negociação.
                </div>
            @endforelse
        </div>

        <div style="border: 1px solid #d1d5db; border-radius: 8px; background: #ffffff; padding: 16px; margin-top: 8px;">
            @if($this->canSendProposal())
                <h2 style="margin: 0 0 12px 0; font-size: 18px; font-weight: 700; color: #111827;">{{ $this->proposalActionLabel() }}</h2>

                <form wire:submit="sendProposal" style="display: flex; flex-direction: column; gap: 10px;">
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-size: 13px; color: #374151;">Descrição da Proposta</label>
                        <textarea
                            wire:model="proposalFormData.content"
                            rows="4"
                            placeholder="Descreva sua proposta..."
                            style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 10px; font-size: 14px;"
                        ></textarea>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 6px; font-size: 13px; color: #374151;">Valor (R$)</label>
                        <input
                            type="number"
                            wire:model="proposalFormData.price"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 10px; font-size: 14px;"
                        />
                    </div>

                    <div>
                        <button
                            type="submit"
                            style="border: none; border-radius: 6px; background: #2563eb; color: #ffffff; padding: 8px 14px; font-size: 13px; cursor: pointer;"
                        >
                            {{ $this->proposalActionLabel() }}
                        </button>
                    </div>
                </form>
            @else
                <h2 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 700; color: #111827;">Nova Proposta</h2>
                <p style="margin: 0; font-size: 14px; color: #6b7280;">{{ $this->sendBlockedMessage() }}</p>
            @endif
        </div>
    </div>
</x-filament-panels::page>
