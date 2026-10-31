<x-filament-panels::page>
    @php
        $lastProposal = $this->lastProposal();
        $products = $record->products->isNotEmpty()
            ? $record->products
            : ($record->product ? collect([$record->product]) : collect());
        $editableProviderMsg = $this->editableProviderMessage();
        $timeline = $this->roomTimeline();
        $adjustments = $record->adjustments()->with('spawnedOrder')->latest('id')->get();
        $hasCompleteScope = $record->hasCompleteScope();
        $scopePageUrl = \App\Filament\Resources\Orders\OrderResource::getUrl('scope', ['record' => $record]);
        $pdfDownloadUrl = route('filament.orders.contract.pdf', ['order' => $record->id]);
        $catalogProducts = $this->availableCatalogProducts();
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
                    @if($record->parentOrder)
                        <div style="margin-top: 6px; font-size: 12px; color: #c084fc;">
                            ↳ Originado de item fora do escopo do pedido
                            <a
                                href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('edit', ['record' => $record->parentOrder->id]) }}"
                                style="color: #f0abfc; font-weight: 700; text-decoration: underline;"
                            >
                                #ORD-{{ str_pad((string) $record->parentOrder->id, 4, '0', STR_PAD_LEFT) }} ({{ $record->parentOrder->title }})
                            </a>
                        </div>
                    @endif
                </div>
                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
                    <a
                        href="{{ $scopePageUrl }}"
                        style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 13px; border-radius: 9999px; background: {{ $hasCompleteScope ? '#14532d' : '#78350f' }}; border: 1px solid {{ $hasCompleteScope ? '#22c55e' : '#f59e0b' }}; color: {{ $hasCompleteScope ? '#dcfce7' : '#fef3c7' }}; font-size: 12px; font-weight: 700; text-decoration: none;"
                    >
                        📋 {{ $hasCompleteScope ? 'Escopo Definido (Ver / Editar)' : 'Escopo Obrigatório Pendente' }}
                    </a>

                    @if($hasCompleteScope)
                        <a
                            href="{{ $pdfDownloadUrl }}"
                            target="_blank"
                            style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 13px; border-radius: 9999px; background: #2e1065; border: 1px solid #9333ea; color: #f3e8ff; font-size: 12px; font-weight: 700; text-decoration: none;"
                        >
                            ⬇ PDF Consolidado
                        </a>
                    @endif

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

        {{-- 3. TRIAGEM CONTRATUAL DE AJUSTES --}}
        @if($adjustments->isNotEmpty())
            <div style="border: 1px solid #f59e0b; border-radius: 12px; background: #0f172a; padding: 20px;">
                <h3 style="margin: 0 0 12px 0; font-size: 18px; font-weight: 700; color: #fcd34d;">
                    🛡️ Triagem Contratual de Ajustes ({{ $adjustments->count() }})
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

                            @if($adj->spawned_order_id)
                                <div style="margin-bottom: 10px; padding: 12px; border-radius: 8px; background: #2e1065; border: 1px solid #7e22ce; font-size: 13px; color: #f3e8ff;">
                                    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px;">
                                        <div>
                                            <strong>↪ Direcionado para Novo Pedido #ORD-{{ str_pad((string) $adj->spawned_order_id, 4, '0', STR_PAD_LEFT) }}</strong>
                                            @if($adj->spawnedOrder)
                                                — {{ $adj->spawnedOrder->title }}
                                            @endif
                                        </div>
                                        <a
                                            href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('edit', ['record' => $adj->spawned_order_id]) }}"
                                            style="color: #f0abfc; font-weight: 700; text-decoration: underline;"
                                        >
                                            Abrir Novo Pedido no Painel →
                                        </a>
                                    </div>
                                    @if($adj->provider_notes)
                                        <div style="margin-top: 6px; color: #ddd6fe;">Orientação enviada ao cliente: "{{ $adj->provider_notes }}"</div>
                                    @endif
                                </div>
                            @elseif($adj->decision === 'rejected' && $adj->provider_notes)
                                <div style="margin-bottom: 10px; padding: 10px; border-radius: 8px; background: #450a0a; border: 1px solid #991b1b; font-size: 13px; color: #fecaca;">
                                    <strong>Motivo da reprovação:</strong> {{ $adj->provider_notes }}
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
                                            wire:click="openSpawnOrderModal({{ $adj->id }})"
                                            style="border: none; border-radius: 8px; background: #9333ea; color: #ffffff; padding: 8px 14px; font-size: 12px; font-weight: 600; cursor: pointer;"
                                        >
                                            ➕ Fora do Escopo: Criar Novo Pedido
                                        </button>
                                    </div>

                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <input
                                            type="text"
                                            wire:model="rejectionReasons.{{ $adj->id }}"
                                            placeholder="Motivo contratual para apenas reprovar o ajuste..."
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
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- 4. NEGOCIAÇÃO COMERCIAL EM PING-PONG --}}
        <div style="border: 1px solid #334155; border-radius: 12px; background: #0f172a; padding: 20px;">
            <h3 style="margin: 0 0 12px 0; font-size: 18px; font-weight: 700; color: #ffffff;">
                🤝 Negociação Comercial & Escopo do Serviço
            </h3>

            {{-- BARRA OBRIGATÓRIA DE ESCOPO DO SERVIÇO --}}
            @if(! $hasCompleteScope)
                <div style="margin-bottom: 16px; padding: 16px; border-radius: 10px; background: #2e1065; border: 1px solid #a855f7; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;">
                    <div style="max-width: 680px;">
                        <div style="font-size: 14px; font-weight: 700; color: #f3e8ff; margin-bottom: 4px;">
                            ⚠️ Etapa Obrigatória: Cadastre o Escopo do Serviço antes de enviar ou aceitar propostas
                        </div>
                        <div style="font-size: 12px; color: #ddd6fe;">
                            Preencha estruturadamente o que será feito, o que NÃO será feito, critérios de entrega, revisões e responsabilidades do cliente. O sistema gerará automaticamente o PDF consolidado do pedido.
                        </div>
                    </div>
                    <a
                        href="{{ $scopePageUrl }}"
                        style="display: inline-flex; align-items: center; gap: 6px; border-radius: 8px; background: #9333ea; color: #ffffff; padding: 10px 16px; font-size: 13px; font-weight: 700; text-decoration: none;"
                    >
                        📋 Cadastrar Escopo do Serviço →
                    </a>
                </div>
            @else
                <div style="margin-bottom: 16px; padding: 12px 16px; border-radius: 10px; background: #052e16; border: 1px solid #16a34a; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;">
                    <div style="font-size: 13px; color: #dcfce7;">
                        ✓ <strong>Escopo do Serviço estruturado!</strong>
                        @if($record->contract_signed_at)
                            <span>Assinado e congelado em {{ $record->contract_signed_at->format('d/m/Y H:i') }}.</span>
                        @else
                            <span>Pronto para envio de proposta e geração do PDF consolidado.</span>
                        @endif
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <a
                            href="{{ $scopePageUrl }}"
                            style="padding: 6px 12px; border-radius: 8px; background: #14532d; border: 1px solid #22c55e; color: #ffffff; font-size: 12px; font-weight: 700; text-decoration: none;"
                        >
                            {{ $record->contract_signed_at ? 'Ver Escopo Congelado' : 'Editar Escopo' }}
                        </a>
                        <a
                            href="{{ $pdfDownloadUrl }}"
                            target="_blank"
                            style="padding: 6px 12px; border-radius: 8px; background: #3b0764; border: 1px solid #9333ea; color: #f3e8ff; font-size: 12px; font-weight: 700; text-decoration: none;"
                        >
                            ⬇ Baixar PDF Consolidado
                        </a>
                    </div>
                </div>
            @endif

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
                                        wire:click="requestConfirmation('accept_proposal', 'Aceitar Contra-proposta do Cliente?', 'Ao confirmar, o valor de R$ {{ number_format((float) $proposal->price, 2, ',', '.') }} será fixado definitivamente como acordo comercial e o contrato consolidado será liberado para assinatura.', {{ $proposal->id }})"
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
                        Nenhuma proposta comercial enviada ainda.
                    </div>
                @endforelse
            </div>

            @if($this->canSendProposal())
                <div style="border-top: 1px solid #1e293b; padding-top: 16px;">
                    <h4 style="margin: 0 0 10px 0; font-size: 15px; font-weight: 700; color: #e9d5ff;">{{ $this->proposalActionLabel() }}</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px;">
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
                    </div>

                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 12px; color: #cbd5e1; margin-bottom: 4px;">Mensagem da Proposta</label>
                        <textarea
                            rows="3"
                            wire:model="proposalFormData.content"
                            placeholder="Ex: Olá! Estruturei o Escopo do Serviço e farei sua Landing Page responsiva conforme solicitado."
                            style="width: 100%; border: 1px solid #475569; border-radius: 8px; background: #1e293b; color: #ffffff; padding: 8px 10px; font-size: 13px;"
                        ></textarea>
                    </div>

                    <button
                        type="button"
                        wire:click="requestConfirmation('send_proposal', 'Confirmar Envio da Proposta Comercial?', 'O cliente será notificado imediatamente no chat com o valor, prazo e acesso ao PDF do Escopo do Serviço. Deseja prosseguir?')"
                        style="border: none; border-radius: 8px; background: #7c3aed; color: #ffffff; padding: 9px 18px; font-size: 13px; font-weight: 700; cursor: pointer;"
                    >
                        {{ $this->proposalActionLabel() }}
                    </button>
                </div>
            @else
                <div style="font-size: 13px; color: #94a3b8;">{{ $this->sendBlockedMessage() }}</div>
            @endif
        </div>

        {{-- 5. SALA DE CHAT (VISÃO DO PRESTADOR) --}}
        <div style="border: 1px solid #581c87; border-radius: 12px; background: #090514; padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #e9d5ff;">
                    💬 Linha do Tempo & Chat da Sala (Auditável)
                </h3>
                <span style="font-size: 12px; color: #a855f7;">
                    Histórico registrado integralmente no PDF consolidado
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
                            @if(! empty($item['payload']['new_order_id']))
                                <div style="margin-top: 8px;">
                                    <a
                                        href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('edit', ['record' => $item['payload']['new_order_id']]) }}"
                                        style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 6px; background: #581c87; color: #f3e8ff; font-size: 12px; font-weight: 700; text-decoration: none;"
                                    >
                                        ↪ Abrir {{ $item['payload']['new_order_code'] ?? ('#ORD-'.$item['payload']['new_order_id']) }} no Painel →
                                    </a>
                                </div>
                            @endif
                        </div>
                    @else
                        @php
                            $isProviderMsg = $item['sender_role'] === 'provider';
                        @endphp
                        <div style="align-self: {{ $isProviderMsg ? 'flex-end' : 'flex-start' }}; max-width: 78%; min-width: 260px; border-radius: 12px; padding: 12px 14px; background: {{ $isProviderMsg ? '#4c1d95' : '#1e293b' }}; border: 1px solid {{ $isProviderMsg ? '#7c3aed' : '#334155' }};">
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 6px; font-size: 11px;">
                                <span style="font-weight: 700; color: {{ $isProviderMsg ? '#e9d5ff' : '#93c5fd' }};">
                                    {{ $item['sender_name'] }}
                                </span>
                                <span style="color: #cbd5e1;">
                                    {{ $item['is_locked'] ? '🔒 Registrada' : '✏️ Editável pelo autor' }}
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
                        💬 Enviar resposta ao cliente:
                    </div>

                    <textarea
                        rows="3"
                        wire:model="turnMessageContent"
                        placeholder="Digite sua mensagem para o cliente..."
                        style="width: 100%; border: 1px solid #6b21a8; border-radius: 10px; background: #130b24; color: #ffffff; padding: 10px 12px; font-size: 14px; margin-bottom: 10px;"
                    ></textarea>

                    <button
                        type="button"
                        wire:click="sendProviderTurnMessage"
                        wire:loading.attr="disabled"
                        style="border: none; border-radius: 8px; background: #9333ea; color: #ffffff; padding: 9px 18px; font-size: 13px; font-weight: 700; cursor: pointer;"
                    >
                        📨 Enviar Mensagem
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- MODAL: FORA DO ESCOPO -> CRIAR NOVO PEDIDO COM SELEÇÃO DE PRODUTOS --}}
    @if($showSpawnOrderModal)
        <div style="position: fixed; inset: 0; background: rgba(0, 0, 0, 0.72); z-index: 9990;" wire:click="closeSpawnOrderModal"></div>
        <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 95%; max-width: 600px; max-height: 90vh; overflow-y: auto; background: #0f172a; border: 1px solid #9333ea; border-radius: 14px; padding: 22px; z-index: 9991; color: #f8fafc;">
            <h3 style="margin: 0 0 6px 0; font-size: 18px; font-weight: 700; color: #f3e8ff;">
                ➕ Item Fora do Escopo — Abrir Novo Pedido do Zero
            </h3>
            <p style="margin: 0 0 16px 0; font-size: 12px; color: #94a3b8;">
                O pedido atual (#ORD-{{ str_pad((string) $record->id, 4, '0', STR_PAD_LEFT) }}) voltará para Homologação e o Bot enviará no chat a sua explicação técnica junto ao link clicável do novo pedido.
            </p>

            <div style="display: flex; flex-direction: column; gap: 14px;">
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #cbd5e1; margin-bottom: 6px;">
                        1. Selecione o(s) Produto(s) do Novo Pedido
                    </label>
                    <div style="display: flex; flex-direction: column; gap: 8px; max-height: 200px; overflow-y: auto; padding: 10px; border-radius: 8px; background: #090d16; border: 1px solid #334155;">
                        @forelse($catalogProducts as $catalogProd)
                            @php
                                $isSelected = in_array($catalogProd->id, $spawnOrderForm['product_ids'] ?? [], true);
                            @endphp
                            <button
                                type="button"
                                wire:click="toggleSpawnProduct({{ $catalogProd->id }})"
                                style="display: flex; justify-content: space-between; align-items: center; gap: 10px; text-align: left; width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid {{ $isSelected ? '#a855f7' : '#334155' }}; background: {{ $isSelected ? '#2e1065' : '#1e293b' }}; color: #f8fafc; cursor: pointer;"
                            >
                                <div>
                                    <div style="font-size: 13px; font-weight: 700;">
                                        {{ $isSelected ? '☑' : '☐' }} {{ $catalogProd->name }}
                                    </div>
                                    <div style="font-size: 11px; color: #94a3b8;">{{ $catalogProd->billingLabel() }}</div>
                                </div>
                                @if($catalogProd->base_price)
                                    <span style="font-size: 12px; font-weight: 700; color: #c084fc;">
                                        Base: R$ {{ number_format((float) $catalogProd->base_price, 2, ',', '.') }}
                                    </span>
                                @endif
                            </button>
                        @empty
                            <div style="font-size: 12px; color: #94a3b8;">Nenhum produto ativo encontrado no catálogo.</div>
                        @endforelse
                    </div>
                </div>

                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #cbd5e1; margin-bottom: 4px;">
                        2. Título do Novo Pedido
                    </label>
                    <input
                        type="text"
                        wire:model="spawnOrderForm.title"
                        placeholder="Ex: Deploy e Configuração de Servidor VPS"
                        style="width: 100%; border: 1px solid #475569; border-radius: 8px; background: #1e293b; color: #ffffff; padding: 8px 10px; font-size: 13px;"
                    />
                </div>

                <div>
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #cbd5e1; margin-bottom: 4px;">
                        3. Explicação Técnica ao Cliente (será enviada pelo Bot no chat)
                    </label>
                    <textarea
                        rows="3"
                        wire:model="spawnOrderForm.provider_notes"
                        placeholder="Ex: O escopo aprovado contemplava o desenvolvimento da Landing Page, mas não o serviço de Deploy em servidor. Criamos este novo pedido de Deploy para seguirmos com escopo e orçamento dedicados."
                        style="width: 100%; border: 1px solid #475569; border-radius: 8px; background: #1e293b; color: #ffffff; padding: 8px 10px; font-size: 13px;"
                    ></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 6px;">
                    <button
                        type="button"
                        wire:click="closeSpawnOrderModal"
                        style="border: 1px solid #475569; border-radius: 8px; background: transparent; color: #cbd5e1; padding: 8px 14px; font-size: 13px; cursor: pointer;"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        wire:click="createNewOrderFromAdjustment"
                        wire:loading.attr="disabled"
                        style="border: none; border-radius: 8px; background: #9333ea; color: #ffffff; padding: 9px 18px; font-size: 13px; font-weight: 700; cursor: pointer;"
                    >
                        ➕ Criar Novo Pedido & Notificar Cliente
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL: HISTÓRICO DE EDIÇÕES --}}
    @if($historyModalMessage)
        <div style="position: fixed; inset: 0; background: rgba(0, 0, 0, 0.7); z-index: 9992;" wire:click="closeMessageHistory"></div>
        <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 95%; max-width: 520px; background: #0f172a; border: 1px solid #6b21a8; border-radius: 14px; padding: 20px; z-index: 9993; color: #f8fafc;">
            <h3 style="margin: 0 0 12px 0; font-size: 17px; font-weight: 700; color: #e9d5ff;">
                🕓 Histórico de Edições ({{ $historyModalMessage['sender_name'] }})
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
