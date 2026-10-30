<x-filament-panels::page>
    @php
        $lastProposal = $this->lastProposal();
        $products = $record->products->isNotEmpty()
            ? $record->products
            : ($record->product ? collect([$record->product]) : collect());
        $editableProviderMsg = $this->editableProviderMessage();
        $timeline = $this->roomTimeline();
        $adjustments = $record->adjustments()->latest('id')->get();
    @endphp

    <div style="display: flex; flex-direction: column; gap: 20px; color: #f8fafc;">
        {{-- 1. RESUMO DO BRIEFING & ANEXOS PRIVADOS --}}
        <div style="border: 1px solid #334155; border-radius: 12px; background: #0f172a; padding: 20px;">
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; border-bottom: 1px solid #1e293b; padding-bottom: 12px;">
                <div>
                    <span style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #a855f7; font-weight: 700;">
                        Briefing & Governança do Pedido
                    </span>
                    <h2 style="margin: 4px 0 0 0; font-size: 20px; font-weight: 700; color: #ffffff;">
                        #ORD-{{ str_pad((string) $record->id, 4, '0', STR_PAD_LEFT) }} — {{ $record->title }}
                    </h2>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="padding: 6px 14px; border-radius: 9999px; background: #581c87; border: 1px solid #9333ea; color: #f3e8ff; font-size: 13px; font-weight: 700;">
                        Status: {{ \App\Models\Order::statusOptions()[$record->status] ?? $record->status }}
                    </span>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px;">
                <div>
                    <div style="font-size: 12px; color: #94a3b8;">Cliente</div>
                    <div style="font-size: 14px; font-weight: 700; color: #f1f5f9;">{{ $record->user->name }} ({{ $record->user->email }})</div>
                </div>

                <div>
                    <div style="font-size: 12px; color: #94a3b8;">Valor Acordado / Prazo</div>
                    <div style="font-size: 14px; font-weight: 700; color: #4ade80;">
                        @if($record->final_price)
                            R$ {{ number_format((float) $record->final_price, 2, ',', '.') }} · {{ $record->estimated_days ?? '-' }} dias úteis
                        @else
                            Em negociação
                        @endif
                    </div>
                </div>

                <div>
                    <div style="font-size: 12px; color: #94a3b8;">Link do Figma</div>
                    <div style="font-size: 14px;">
                        @if($record->figma_url)
                            <a href="{{ $record->figma_url }}" target="_blank" rel="noopener noreferrer" style="color: #c084fc; text-decoration: underline;">
                                Abrir Protótipo no Figma ↗
                            </a>
                        @else
                            <span style="color: #64748b;">Não informado</span>
                        @endif
                    </div>
                </div>

                @if($record->warranty_until)
                    <div>
                        <div style="font-size: 12px; color: #94a3b8;">Garantia Contratual (30 dias)</div>
                        <div style="font-size: 14px; font-weight: 700; color: #38bdf8;">
                            Válida até {{ \Illuminate\Support\Carbon::parse($record->warranty_until)->format('d/m/Y') }}
                        </div>
                    </div>
                @endif
            </div>

            <div style="margin-bottom: 16px;">
                <div style="font-size: 12px; color: #94a3b8; margin-bottom: 6px;">Produtos Selecionados</div>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    @foreach($products as $prod)
                        <span style="padding: 4px 12px; border-radius: 8px; background: #1e293b; border: 1px solid #475569; font-size: 12px; color: #e2e8f0;">
                            <strong>{{ $prod->name }}</strong> · <span style="color: #c084fc;">{{ $prod->billingLabel() }}</span>
                        </span>
                    @endforeach
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <div style="font-size: 12px; color: #94a3b8; margin-bottom: 4px;">Descrição & Requisitos do Projeto</div>
                <div style="padding: 12px; border-radius: 8px; background: #1e293b; font-size: 14px; color: #e2e8f0; white-space: pre-wrap;">{{ $record->description }}</div>
            </div>

            <div>
                <div style="font-size: 12px; color: #94a3b8; margin-bottom: 6px;">Anexos Privados do Briefing (Storage Seguro `app/private`)</div>
                @if($record->attachments->isNotEmpty())
                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                        @foreach($record->attachments as $att)
                            <a
                                href="{{ route('filament.orders.attachments.download', ['order' => $record->id, 'attachment' => $att->id]) }}"
                                style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 8px; background: #3b0764; border: 1px solid #7e22ce; color: #f3e8ff; font-size: 12px; text-decoration: none;"
                            >
                                <span>🔒 {{ $att->original_name }}</span>
                                <span style="color: #d8b4fe;">({{ number_format($att->file_size / 1024, 1) }} KB)</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div style="font-size: 13px; color: #64748b;">Nenhum arquivo anexado neste pedido.</div>
                @endif
            </div>
        </div>

        {{-- 2. CONTROLE OPERACIONAL MANUAL DO PRESTADOR --}}
        <div style="border: 1px solid #6b21a8; border-radius: 12px; background: #170d2b; padding: 20px;">
            <h3 style="margin: 0 0 6px 0; font-size: 17px; font-weight: 700; color: #f3e8ff;">
                ⚙️ Controle Manual de Etapas (Exclusivo Prestador)
            </h3>
            <p style="margin: 0 0 16px 0; font-size: 13px; color: #cbd5e1;">
                Todas as ações abaixo são críticas e exigem confirmação explícita antes de alterar o estado do contrato e notificar o cliente no chat.
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; margin-bottom: 14px;">
                <div>
                    <label style="display: block; font-size: 12px; color: #cbd5e1; margin-bottom: 4px;">URL de Homologação / Staging (Teste)</label>
                    <input
                        type="url"
                        wire:model="stagingUrlInput"
                        placeholder="https://homologacao-cliente.webest.dev"
                        style="width: 100%; border: 1px solid #475569; border-radius: 8px; background: #0f172a; color: #ffffff; padding: 8px 12px; font-size: 13px;"
                    />
                </div>
                <div>
                    <label style="display: block; font-size: 12px; color: #cbd5e1; margin-bottom: 4px;">Repositório / Entrega de Código-Fonte (Opcional)</label>
                    <input
                        type="url"
                        wire:model="repositoryUrlInput"
                        placeholder="https://github.com/webest/projeto-cliente"
                        style="width: 100%; border: 1px solid #475569; border-radius: 8px; background: #0f172a; color: #ffffff; padding: 8px 12px; font-size: 13px;"
                    />
                </div>
            </div>

            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                @if($record->status === 'awaiting_payment')
                    <button
                        type="button"
                        wire:click="requestConfirmation('confirm_payment_manual', 'Confirmar Pagamento Manualmente?', 'Esta ação marcará o pagamento como quitado sem aguardar o webhook automático e moverá o pedido para Aguardando Desenvolvimento. Esta ação não pode ser desfeita.')"
                        style="border: none; border-radius: 8px; background: #0284c7; color: #ffffff; padding: 9px 16px; font-size: 13px; font-weight: 600; cursor: pointer;"
                    >
                        💳 Confirmar Pagamento Manual
                    </button>
                @endif

                @if(in_array($record->status, ['awaiting_development', 'paid'], true))
                    <button
                        type="button"
                        wire:click="requestConfirmation('start_development', 'Iniciar Desenvolvimento do Projeto?', 'Ao confirmar, o prazo contratual passará a contar, o status mudará para Em Desenvolvimento e o Bot notificará o cliente na sala.')"
                        style="border: none; border-radius: 8px; background: #7c3aed; color: #ffffff; padding: 9px 16px; font-size: 13px; font-weight: 600; cursor: pointer;"
                    >
                        🚀 Iniciar Desenvolvimento
                    </button>
                @endif

                @if(in_array($record->status, ['in_progress', 'in_revision', 'review', 'homologation'], true))
                    <button
                        type="button"
                        wire:click="requestConfirmation('release_homologation', 'Liberar Versão para Homologação?', 'Certifique-se de que a URL de Homologação está correta. O status mudará para Em Homologação e o cliente será convidado a testar e aprovar.')"
                        style="border: none; border-radius: 8px; background: #9333ea; color: #ffffff; padding: 9px 16px; font-size: 13px; font-weight: 600; cursor: pointer;"
                    >
                        🔍 Liberar para Homologação / Teste
                    </button>
                @endif

                @if($record->provider_completed_at === null && in_array($record->status, ['homologation', 'in_progress'], true))
                    <button
                        type="button"
                        wire:click="requestConfirmation('complete_order_provider', 'Confirmar Finalização pelo Prestador?', 'Esta é uma ação irreversível. Assim que cliente e prestador confirmarem, o pedido será encerrado como Concluído e a garantia contratual de 30 dias será ativada.')"
                        style="border: none; border-radius: 8px; background: #16a34a; color: #ffffff; padding: 9px 16px; font-size: 13px; font-weight: 600; cursor: pointer;"
                    >
                        ✅ Definir Projeto como Finalizado (Prestador)
                    </button>
                @endif

                @if(in_array($record->status, ['pending', 'under_review', 'proposal_sent', 'proposal_negotiation', 'awaiting_contract', 'awaiting_payment'], true))
                    <button
                        type="button"
                        wire:click="requestConfirmation('cancel_order', 'Cancelar Definitivamente o Pedido?', 'Esta ação encerrará todas as propostas pendentes e marcará o pedido como Cancelado de forma irreversível.')"
                        style="border: 1px solid #ef4444; border-radius: 8px; background: transparent; color: #fca5a5; padding: 9px 16px; font-size: 13px; font-weight: 600; cursor: pointer;"
                    >
                        ✖ Cancelar Pedido
                    </button>
                @endif
            </div>
        </div>

        {{-- 3. TRIAGEM CONTRATUAL DE AJUSTES & TERMO ADITIVO --}}
        @if($adjustments->isNotEmpty())
            <div style="border: 1px solid #f59e0b; border-radius: 12px; background: #0f172a; padding: 20px;">
                <h3 style="margin: 0 0 12px 0; font-size: 18px; font-weight: 700; color: #fcd34d;">
                    🛡️ Triagem Contratual de Ajustes & Termos Aditivos ({{ $adjustments->count() }})
                </h3>

                <div style="display: flex; flex-direction: column; gap: 14px;">
                    @foreach($adjustments as $adj)
                        <div style="border: 1px solid #334155; border-radius: 10px; background: #1e293b; padding: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 8px;">
                                <span style="font-size: 13px; font-weight: 700; color: #f8fafc;">
                                    Solicitação #{{ $adj->id }} — {{ $adj->created_at?->format('d/m/Y H:i') }}
                                </span>
                                <span style="padding: 3px 10px; border-radius: 9999px; background: #3b0764; color: #e9d5ff; font-size: 12px; font-weight: 600;">
                                    {{ $adj->decisionLabel() }}
                                </span>
                            </div>

                            <div style="font-size: 14px; color: #e2e8f0; white-space: pre-wrap; margin-bottom: 12px; padding: 10px; background: #0f172a; border-radius: 8px;">{{ $adj->client_description }}</div>

                            @if($adj->contract_reference)
                                <div style="margin-bottom: 10px; padding: 10px; border-radius: 8px; background: #2e1065; border: 1px solid #7e22ce; font-size: 13px; color: #f3e8ff;">
                                    <div><strong>{{ $adj->contract_reference }}</strong> — Valor: <strong>R$ {{ number_format((float) $adj->addendum_price, 2, ',', '.') }}</strong> (+{{ $adj->addendum_extra_days }} dias úteis) · Pagamento: <strong>{{ $adj->payment_status }}</strong></div>
                                    <div style="margin-top: 4px; color: #ddd6fe;">Escopo Extra: {{ $adj->addendum_scope }}</div>
                                    @if($adj->decision === 'addendum_countered' && $adj->counter_price)
                                        <div style="margin-top: 8px; padding: 8px; border-radius: 6px; background: #451a03; color: #fde68a;">
                                            ⚡ <strong>Contra-proposta do Cliente no Aditivo:</strong> R$ {{ number_format((float) $adj->counter_price, 2, ',', '.') }} — "{{ $adj->counter_notes }}"
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @if($adj->decision === 'pending')
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                        <button
                                            type="button"
                                            wire:click="requestConfirmation('approve_adjustment', 'Aprovar Ajuste Dentro do Contrato (Sem Custo)?', 'Confirma que os itens solicitados fazem parte do contrato original? O status mudará para Em Revisão sem cobrança extra.', {{ $adj->id }})"
                                            style="border: none; border-radius: 8px; background: #16a34a; color: #ffffff; padding: 8px 14px; font-size: 12px; font-weight: 600; cursor: pointer;"
                                        >
                                            ✓ Aprovar no Escopo (R$ 0)
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="openAddendumModal({{ $adj->id }})"
                                            style="border: none; border-radius: 8px; background: #9333ea; color: #ffffff; padding: 8px 14px; font-size: 12px; font-weight: 600; cursor: pointer;"
                                        >
                                            📄 Fora do Escopo: Emitir Termo Aditivo (Stripe)
                                        </button>
                                    </div>

                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <input
                                            type="text"
                                            wire:model="rejectionReasons.{{ $adj->id }}"
                                            placeholder="Motivo contratual para reprovar ajuste..."
                                            style="flex: 1; border: 1px solid #475569; border-radius: 8px; background: #0f172a; color: #ffffff; padding: 7px 10px; font-size: 12px;"
                                        />
                                        <button
                                            type="button"
                                            wire:click="requestConfirmation('reject_adjustment', 'Reprovar Solicitação de Ajuste?', 'A solicitação será recusada formalmente com a justificativa informada e o pedido retornará para Homologação.', {{ $adj->id }})"
                                            style="border: none; border-radius: 8px; background: #dc2626; color: #ffffff; padding: 8px 12px; font-size: 12px; font-weight: 600; cursor: pointer;"
                                        >
                                            ✗ Reprovar Ajuste
                                        </button>
                                    </div>
                                </div>
                            @elseif($adj->decision === 'addendum_countered')
                                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                    <button
                                        type="button"
                                        wire:click="requestConfirmation('accept_addendum_counter', 'Aceitar Contra-proposta do Aditivo?', 'O valor do Termo Aditivo será atualizado para o valor proposto pelo cliente e um novo link Stripe será liberado.', {{ $adj->id }})"
                                        style="border: none; border-radius: 8px; background: #16a34a; color: #ffffff; padding: 8px 14px; font-size: 12px; font-weight: 600; cursor: pointer;"
                                    >
                                        ✓ Aceitar Contra-proposta (R$ {{ number_format((float) $adj->counter_price, 2, ',', '.') }})
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="openAddendumModal({{ $adj->id }})"
                                        style="border: none; border-radius: 8px; background: #7c3aed; color: #ffffff; padding: 8px 14px; font-size: 12px; font-weight: 600; cursor: pointer;"
                                    >
                                        ↺ Enviar Nova Oferta no Aditivo
                                    </button>
                                </div>
                            @elseif($adj->decision === 'addendum_issued' && $adj->payment_status !== 'paid')
                                <button
                                    type="button"
                                    wire:click="requestConfirmation('confirm_addendum_paid_manual', 'Confirmar Pagamento do Termo Aditivo?', 'Confirma a quitação do aditivo? Os dias úteis extras serão somados automaticamente ao prazo e o projeto irá para Em Revisão.', {{ $adj->id }})"
                                    style="border: none; border-radius: 8px; background: #0284c7; color: #ffffff; padding: 7px 12px; font-size: 12px; font-weight: 600; cursor: pointer;"
                                >
                                    💳 Confirmar Pagamento do Aditivo Manualmente
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- 4. NEGOCIAÇÃO COMERCIAL EM PING-PONG --}}
        <div style="border: 1px solid #334155; border-radius: 12px; background: #0f172a; padding: 20px;">
            <h3 style="margin: 0 0 12px 0; font-size: 18px; font-weight: 700; color: #ffffff;">
                🤝 Negociação Comercial (Ping-Pong de Propostas)
            </h3>

            <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px;">
                @forelse($record->proposals()->with('sender')->orderBy('id', 'desc')->get() as $proposal)
                    @php
                        $isAdmin = $proposal->sender->hasRole('admin');
                        $userCanRespond = auth()->id() !== $proposal->sender_id && $lastProposal?->id === $proposal->id && $proposal->is_accepted === null;
                    @endphp
                    <div style="border: 1px solid {{ $isAdmin ? '#6b21a8' : '#334155' }}; border-radius: 10px; background: {{ $isAdmin ? '#1e1b4b' : '#1e293b' }}; padding: 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                            <div style="font-size: 13px; font-weight: 700; color: #f8fafc;">
                                {{ $proposal->sender->name }}
                                <span style="margin-left: 6px; font-size: 11px; padding: 2px 8px; border-radius: 9999px; background: {{ $isAdmin ? '#581c87' : '#0369a1' }}; color: #ffffff;">
                                    {{ $isAdmin ? 'Prestador' : 'Cliente' }}
                                </span>
                                <span style="margin-left: 8px; font-size: 12px; color: #94a3b8; font-weight: 400;">
                                    {{ $proposal->created_at?->format('d/m/Y H:i') }}
                                </span>
                            </div>
                            <div>
                                @if($proposal->is_accepted === true)
                                    <span style="padding: 3px 10px; border-radius: 9999px; background: #14532d; color: #86efac; font-size: 12px; font-weight: 700;">✓ Acordada</span>
                                @elseif($proposal->is_accepted === false)
                                    <span style="padding: 3px 10px; border-radius: 9999px; background: #7f1d1d; color: #fca5a5; font-size: 12px;">↺ Superada / Recusada</span>
                                @else
                                    <span style="padding: 3px 10px; border-radius: 9999px; background: #78350f; color: #fde68a; font-size: 12px;">⏳ Pendente</span>
                                @endif
                            </div>
                        </div>

                        <div style="margin-top: 8px; font-size: 14px; color: #e2e8f0; white-space: pre-wrap;">{{ $proposal->content }}</div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                            <div style="font-size: 15px; font-weight: 700; color: #4ade80;">
                                R$ {{ number_format((float) $proposal->price, 2, ',', '.') }}
                                <span style="font-size: 12px; color: #cbd5e1; font-weight: 400;">· Prazo: {{ $proposal->estimated_days ?? 7 }} dias úteis</span>
                            </div>

                            @if($userCanRespond)
                                <div style="display: flex; gap: 8px;">
                                    <button
                                        type="button"
                                        wire:click="requestConfirmation('accept_proposal', 'Aceitar Contra-proposta do Cliente?', 'Ao confirmar, o valor de R$ {{ number_format((float) $proposal->price, 2, ',', '.') }} será fixado definitivamente como acordo comercial e o contrato será liberado para assinatura.', {{ $proposal->id }})"
                                        style="border: none; border-radius: 8px; background: #16a34a; color: #ffffff; padding: 7px 14px; font-size: 12px; font-weight: 700; cursor: pointer;"
                                    >
                                        ✓ Aceitar Oferta
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="requestConfirmation('reject_proposal', 'Recusar Contra-proposta para Contra-ofertar?', 'A contra-proposta do cliente será marcada como recusada para que você possa enviar um novo valor ou encerrar.', {{ $proposal->id }})"
                                        style="border: none; border-radius: 8px; background: #dc2626; color: #ffffff; padding: 7px 14px; font-size: 12px; font-weight: 700; cursor: pointer;"
                                    >
                                        ✗ Recusar / Contra-propor
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div style="padding: 14px; border-radius: 8px; background: #1e293b; color: #94a3b8; font-size: 13px; text-align: center;">
                        Nenhuma proposta comercial enviada ainda. Formule a primeira proposta abaixo.
                    </div>
                @endforelse
            </div>

            @if($this->canSendProposal())
                <div style="border-top: 1px solid #1e293b; padding-top: 16px;">
                    <h4 style="margin: 0 0 10px 0; font-size: 15px; font-weight: 700; color: #e9d5ff;">{{ $this->proposalActionLabel() }}</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 12px; color: #cbd5e1; margin-bottom: 4px;">Valor Proposto (R$)</label>
                            <input
                                type="number"
                                step="0.01"
                                min="1"
                                wire:model="proposalFormData.price"
                                placeholder="500.00"
                                style="width: 100%; border: 1px solid #475569; border-radius: 8px; background: #1e293b; color: #ffffff; padding: 8px 10px; font-size: 13px;"
                            />
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; color: #cbd5e1; margin-bottom: 4px;">Prazo Estimado (Dias Úteis)</label>
                            <input
                                type="number"
                                min="1"
                                max="365"
                                wire:model="proposalFormData.estimated_days"
                                placeholder="7"
                                style="width: 100%; border: 1px solid #475569; border-radius: 8px; background: #1e293b; color: #ffffff; padding: 8px 10px; font-size: 13px;"
                            />
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; color: #cbd5e1; margin-bottom: 4px;">URL do Contrato (Opcional)</label>
                            <input
                                type="text"
                                wire:model="proposalFormData.contract_url"
                                placeholder="Gerado automaticamente se vazio"
                                style="width: 100%; border: 1px solid #475569; border-radius: 8px; background: #1e293b; color: #ffffff; padding: 8px 10px; font-size: 13px;"
                            />
                        </div>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 12px; color: #cbd5e1; margin-bottom: 4px;">Mensagem da Proposta</label>
                        <textarea
                            rows="3"
                            wire:model="proposalFormData.content"
                            placeholder="Ex: Olá! Analisei seu Figma e farei sua Landing Page responsiva conforme solicitado."
                            style="width: 100%; border: 1px solid #475569; border-radius: 8px; background: #1e293b; color: #ffffff; padding: 8px 10px; font-size: 13px;"
                        ></textarea>
                    </div>

                    <button
                        type="button"
                        wire:click="requestConfirmation('send_proposal', 'Confirmar Envio da Proposta Comercial?', 'O cliente será notificado imediatamente no chat com o valor e prazo propostos. Deseja prosseguir?')"
                        style="border: none; border-radius: 8px; background: #7c3aed; color: #ffffff; padding: 9px 18px; font-size: 13px; font-weight: 700; cursor: pointer;"
                    >
                        {{ $this->proposalActionLabel() }}
                    </button>
                </div>
            @else
                <div style="font-size: 13px; color: #94a3b8;">{{ $this->sendBlockedMessage() }}</div>
            @endif
        </div>

        {{-- 5. SALA DE CHAT POR TURNOS (WHATSAPP ROXO - VISÃO DO PRESTADOR) --}}
        <div style="border: 1px solid #581c87; border-radius: 12px; background: #090514; padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #e9d5ff;">
                    💬 Linha do Tempo & Chat por Turnos (Auditável)
                </h3>
                <span style="font-size: 12px; color: #a855f7;">
                    1 mensagem por turno · Edições liberadas até resposta da outra parte
                </span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 12px; max-height: 520px; overflow-y: auto; padding: 12px; border-radius: 10px; background: #0f0a1e; border: 1px solid #1e1b4b; margin-bottom: 16px;">
                @forelse($timeline as $item)
                    @if($item['item_type'] === 'bot_event')
                        <div style="margin: 4px auto; max-width: 85%; width: 100%; border-left: 4px solid #a855f7; border-radius: 8px; background: #1e1b4b; padding: 10px 14px;">
                            <div style="display: flex; justify-content: space-between; font-size: 11px; color: #c084fc; font-weight: 700; margin-bottom: 3px;">
                                <span>🤖 WeBest Bot · {{ $item['title'] }}</span>
                                <span>{{ \Illuminate\Support\Carbon::parse($item['created_at'])->format('d/m H:i') }}</span>
                            </div>
                            <div style="font-size: 13px; color: #e2e8f0;">{{ $item['message'] }}</div>
                        </div>
                    @else
                        @php
                            $isProviderMsg = $item['sender_role'] === 'provider';
                        @endphp
                        <div style="align-self: {{ $isProviderMsg ? 'flex-end' : 'flex-start' }}; max-width: 78%; min-width: 260px; border-radius: 12px; padding: 12px 14px; background: {{ $isProviderMsg ? '#4c1d95' : '#1e293b' }}; border: 1px solid {{ $isProviderMsg ? '#7c3aed' : '#334155' }};">
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 6px; font-size: 11px;">
                                <span style="font-weight: 700; color: {{ $isProviderMsg ? '#e9d5ff' : '#93c5fd' }};">
                                    Turno #{{ $item['turn_number'] }} · {{ $item['sender_name'] }}
                                </span>
                                <span style="color: #cbd5e1;">
                                    {{ $item['is_locked'] ? '🔒 Congelada' : '✏️ Turno Aberto' }}
                                </span>
                            </div>

                            <div style="font-size: 14px; color: #ffffff; white-space: pre-wrap;">{{ $item['current_content'] }}</div>

                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-top: 8px; font-size: 11px; color: #cbd5e1;">
                                <span>{{ \Illuminate\Support\Carbon::parse($item['created_at'])->format('d/m/Y H:i') }}</span>
                                @if($item['is_edited'])
                                    <button
                                        type="button"
                                        wire:click="openMessageHistory({{ $item['id'] }})"
                                        style="border: none; background: transparent; color: #f0abfc; text-decoration: underline; font-size: 11px; cursor: pointer; padding: 0;"
                                    >
                                        [editado — ver histórico ({{ count($item['versions']) }})]
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endif
                @empty
                    <div style="text-align: center; color: #64748b; padding: 24px;">Nenhuma mensagem registrada.</div>
                @endforelse
            </div>

            @if(!in_array($record->status, ['cancelled', 'rejected'], true))
                <div>
                    <div style="font-size: 12px; color: #c084fc; margin-bottom: 6px; font-weight: 600;">
                        @if($editableProviderMsg)
                            ✏️ Você já respondeu neste turno e o cliente ainda não falou. Editar abaixo atualizará sua mensagem atual (mantendo histórico):
                        @else
                            💬 Enviar resposta do seu turno (ao enviar, a mensagem anterior do cliente será congelada permanentemente):
                        @endif
                    </div>

                    <textarea
                        rows="3"
                        wire:model="turnMessageContent"
                        placeholder="Digite a mensagem documentada deste turno para o cliente..."
                        style="width: 100%; border: 1px solid #6b21a8; border-radius: 10px; background: #130b24; color: #ffffff; padding: 10px 12px; font-size: 14px; margin-bottom: 10px;"
                    ></textarea>

                    <button
                        type="button"
                        wire:click="requestConfirmation('send_provider_turn', '{{ $editableProviderMsg ? 'Salvar Edição do Turno Atual?' : 'Enviar Resposta e Congelar Turno do Cliente?' }}', '{{ $editableProviderMsg ? 'A versão anterior da sua mensagem ficará arquivada no histórico auditável e o cliente verá a versão atualizada.' : 'Ao enviar sua resposta, a mensagem anterior do cliente será congelada definitivamente e um novo turno será registrado.' }}')"
                        style="border: none; border-radius: 8px; background: #9333ea; color: #ffffff; padding: 9px 18px; font-size: 13px; font-weight: 700; cursor: pointer;"
                    >
                        {{ $editableProviderMsg ? '💾 Salvar Edição da Mensagem' : '📨 Enviar Mensagem do Turno' }}
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- MODAL: EMISSÃO DE TERMO ADITIVO (STRIPE) --}}
    @if($showAddendumModal)
        <div style="position: fixed; inset: 0; background: rgba(0, 0, 0, 0.7); z-index: 9990;" wire:click="closeAddendumModal"></div>
        <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 95%; max-width: 540px; background: #0f172a; border: 1px solid #7e22ce; border-radius: 14px; padding: 22px; z-index: 9991; color: #f8fafc;">
            <h3 style="margin: 0 0 6px 0; font-size: 18px; font-weight: 700; color: #f3e8ff;">
                📄 Emitir Termo Aditivo ao Contrato Principal
            </h3>
            <p style="margin: 0 0 14px 0; font-size: 12px; color: #94a3b8;">
                Vinculado automaticamente ao Contrato #ORD-{{ str_pad((string) $record->id, 4, '0', STR_PAD_LEFT) }} com cobrança avulsa via Stripe.
            </p>

            <div style="display: flex; flex-direction: column; gap: 12px;">
                <div>
                    <label style="display: block; font-size: 12px; color: #cbd5e1; margin-bottom: 4px;">Modelo de Precificação</label>
                    <select
                        wire:model.live="addendumForm.billing_mode"
                        style="width: 100%; border: 1px solid #475569; border-radius: 8px; background: #1e293b; color: #ffffff; padding: 8px 10px; font-size: 13px;"
                    >
                        <option value="fixed">Preço Fechado (Escopo Fixo)</option>
                        <option value="hourly">Estimativa por Horas (Horas × Valor/Hora)</option>
                    </select>
                </div>

                @if(($addendumForm['billing_mode'] ?? 'fixed') === 'hourly')
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label style="display: block; font-size: 12px; color: #cbd5e1; margin-bottom: 4px;">Horas Estimadas</label>
                            <input type="number" min="1" wire:model.live="addendumForm.estimated_hours" style="width: 100%; border: 1px solid #475569; border-radius: 8px; background: #1e293b; color: #ffffff; padding: 8px 10px; font-size: 13px;" />
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; color: #cbd5e1; margin-bottom: 4px;">Valor / Hora (R$)</label>
                            <input type="number" step="0.01" min="1" wire:model.live="addendumForm.hourly_rate" style="width: 100%; border: 1px solid #475569; border-radius: 8px; background: #1e293b; color: #ffffff; padding: 8px 10px; font-size: 13px;" />
                        </div>
                    </div>
                @else
                    <div>
                        <label style="display: block; font-size: 12px; color: #cbd5e1; margin-bottom: 4px;">Valor do Aditivo (R$)</label>
                        <input type="number" step="0.01" min="1" wire:model="addendumForm.addendum_price" placeholder="350.00" style="width: 100%; border: 1px solid #475569; border-radius: 8px; background: #1e293b; color: #ffffff; padding: 8px 10px; font-size: 13px;" />
                    </div>
                @endif

                <div>
                    <label style="display: block; font-size: 12px; color: #cbd5e1; margin-bottom: 4px;">Dias Úteis Adicionais ao Cronograma</label>
                    <input type="number" min="0" max="180" wire:model="addendumForm.addendum_extra_days" style="width: 100%; border: 1px solid #475569; border-radius: 8px; background: #1e293b; color: #ffffff; padding: 8px 10px; font-size: 13px;" />
                </div>

                <div>
                    <label style="display: block; font-size: 12px; color: #cbd5e1; margin-bottom: 4px;">Descrição Detalhada do Escopo Adicional</label>
                    <textarea rows="3" wire:model="addendumForm.addendum_scope" placeholder="Descreva exatamente o que será entregue neste aditivo..." style="width: 100%; border: 1px solid #475569; border-radius: 8px; background: #1e293b; color: #ffffff; padding: 8px 10px; font-size: 13px;"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 8px;">
                    <button type="button" wire:click="closeAddendumModal" style="border: 1px solid #475569; border-radius: 8px; background: transparent; color: #cbd5e1; padding: 8px 14px; font-size: 13px; cursor: pointer;">
                        Cancelar
                    </button>
                    <button
                        type="button"
                        wire:click="requestConfirmation('issue_addendum', 'Confirmar Emissão do Termo Aditivo?', 'O Termo Aditivo será gerado com checkout Stripe e publicado na sala do cliente. Confirma os valores e prazos informados?')"
                        style="border: none; border-radius: 8px; background: #9333ea; color: #ffffff; padding: 8px 16px; font-size: 13px; font-weight: 700; cursor: pointer;"
                    >
                        Emitir Termo Aditivo
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL: HISTÓRICO DE EDIÇÕES DO TURNO --}}
    @if($historyModalMessage)
        <div style="position: fixed; inset: 0; background: rgba(0, 0, 0, 0.7); z-index: 9992;" wire:click="closeMessageHistory"></div>
        <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 95%; max-width: 520px; background: #0f172a; border: 1px solid #6b21a8; border-radius: 14px; padding: 20px; z-index: 9993; color: #f8fafc;">
            <h3 style="margin: 0 0 12px 0; font-size: 17px; font-weight: 700; color: #e9d5ff;">
                🕓 Histórico de Edições — Turno #{{ $historyModalMessage['turn_number'] }} ({{ $historyModalMessage['sender_name'] }})
            </h3>

            <div style="display: flex; flex-direction: column; gap: 10px; max-height: 360px; overflow-y: auto;">
                @foreach($historyModalMessage['versions'] as $ver)
                    <div style="padding: 10px 12px; border-radius: 8px; background: {{ $ver['is_current'] ? '#2e1065' : '#1e293b' }}; border: 1px solid {{ $ver['is_current'] ? '#9333ea' : '#334155' }};">
                        <div style="display: flex; justify-content: space-between; font-size: 11px; color: #cbd5e1; margin-bottom: 4px;">
                            <strong>Versão v{{ $ver['version_number'] }} {{ $ver['is_current'] ? '[Atual]' : '[Arquivada]' }}</strong>
                            <span>{{ $ver['created_at'] }}</span>
                        </div>
                        <div style="font-size: 13px; color: #ffffff; white-space: pre-wrap;">{{ $ver['content'] }}</div>
                    </div>
                @endforeach
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 14px;">
                <button type="button" wire:click="closeMessageHistory" style="border: none; border-radius: 8px; background: #334155; color: #ffffff; padding: 7px 14px; font-size: 13px; cursor: pointer;">
                    Fechar
                </button>
            </div>
        </div>
    @endif

    {{-- MODAL GLOBAL DE CONFIRMAÇÃO DE AÇÃO CRÍTICA --}}
    @if($confirmationModal)
        <div style="position: fixed; inset: 0; background: rgba(0, 0, 0, 0.78); z-index: 9998;" wire:click="cancelConfirmation"></div>
        <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 95%; max-width: 460px; background: #0f172a; border: 2px solid #a855f7; border-radius: 14px; padding: 22px; z-index: 9999; color: #ffffff; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.8);">
            <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #f59e0b; margin-bottom: 6px;">
                ⚠️ Confirmação de Ação Crítica
            </div>
            <h3 style="margin: 0 0 10px 0; font-size: 18px; font-weight: 700; color: #ffffff;">
                {{ $confirmationModal['title'] }}
            </h3>
            <p style="margin: 0 0 18px 0; font-size: 14px; color: #cbd5e1; line-height: 1.5;">
                {{ $confirmationModal['description'] }}
            </p>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button
                    type="button"
                    wire:click="cancelConfirmation"
                    style="border: 1px solid #475569; border-radius: 8px; background: transparent; color: #e2e8f0; padding: 8px 14px; font-size: 13px; cursor: pointer;"
                >
                    Voltar
                </button>
                <button
                    type="button"
                    wire:click="executeConfirmedAction"
                    style="border: none; border-radius: 8px; background: #9333ea; color: #ffffff; padding: 8px 18px; font-size: 13px; font-weight: 700; cursor: pointer;"
                >
                    Sim, Confirmar Ação
                </button>
            </div>
        </div>
    @endif
</x-filament-panels::page>
