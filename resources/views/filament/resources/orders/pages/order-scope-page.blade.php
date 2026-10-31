<x-filament-panels::page>
    @php
        $sections = \App\Filament\Resources\Orders\Pages\OrderScopePage::sectionDefinitions();
        $isLocked = $this->isScopeLocked();
        $hasCompleteScope = $record->hasCompleteScope();
        $negotiationUrl = \App\Filament\Resources\Orders\OrderResource::getUrl('edit', ['record' => $record]);
        $pdfUrl = route('filament.orders.contract.pdf', ['order' => $record->id]);
    @endphp

    <div style="display: flex; flex-direction: column; gap: 20px; color: #f8fafc;">
        {{-- CABEÇALHO & AÇÕES RÁPIDAS --}}
        <div style="border: 1px solid #334155; border-radius: 12px; background: #0f172a; padding: 20px;">
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px;">
                <div>
                    <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #a855f7; font-weight: 700;">
                        Etapa Obrigatória Antes da Proposta Comercial
                    </div>
                    <h2 style="margin: 4px 0 4px 0; font-size: 22px; font-weight: 800; color: #ffffff;">
                        Escopo do Serviço
                    </h2>
                    <div style="font-size: 14px; color: #cbd5e1;">
                        Pedido <strong>#ORD-{{ str_pad((string) $record->id, 4, '0', STR_PAD_LEFT) }}</strong> • {{ $record->title }}
                    </div>
                </div>

                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
                    <a
                        href="{{ $negotiationUrl }}"
                        style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid #475569; background: #1e293b; color: #f1f5f9; font-size: 13px; font-weight: 600; text-decoration: none;"
                    >
                        ← Voltar à Sala do Pedido
                    </a>

                    @if($hasCompleteScope)
                        <a
                            href="{{ $pdfUrl }}"
                            target="_blank"
                            style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid #7e22ce; background: #3b0764; color: #f3e8ff; font-size: 13px; font-weight: 700; text-decoration: none;"
                        >
                            ⬇ Baixar PDF Consolidado
                        </a>
                    @endif
                </div>
            </div>

            @if($isLocked)
                <div style="margin-top: 16px; padding: 12px 14px; border-radius: 10px; background: #14532d; border: 1px solid #22c55e; color: #dcfce7; font-size: 13px;">
                    🔒 <strong>Escopo Congelado:</strong> O contrato já foi assinado digitalmente pelo cliente. Os itens abaixo estão bloqueados para preservar a integridade jurídica do documento.
                </div>
            @else
                <div style="margin-top: 16px; padding: 12px 14px; border-radius: 10px; background: #1e1b4b; border: 1px solid #6b21a8; color: #e9d5ff; font-size: 13px;">
                    Defina pelo menos <strong>1 item em cada uma das 5 seções abaixo</strong>. Esses itens integrarão o PDF consolidado do contrato disponibilizado ao cliente.
                </div>
            @endif
        </div>

        {{-- 5 SEÇÕES DINÂMICAS DO ESCOPO --}}
        @foreach($sections as $sectionKey => $meta)
            @php
                $items = $scopeForm[$sectionKey] ?? [''];
            @endphp
            <div style="border: 1px solid #334155; border-left: 4px solid {{ $meta['accent'] }}; border-radius: 12px; background: #0f172a; padding: 20px;">
                <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px;">
                    <div>
                        <h3 style="margin: 0; font-size: 17px; font-weight: 700; color: #ffffff;">
                            {{ $meta['label'] }}
                        </h3>
                        <p style="margin: 3px 0 0 0; font-size: 12px; color: #94a3b8;">
                            {{ $meta['subtitle'] }}
                        </p>
                    </div>

                    @if(! $isLocked)
                        <button
                            type="button"
                            wire:click="addItem('{{ $sectionKey }}')"
                            style="border: 1px solid #7e22ce; border-radius: 8px; background: #2e1065; color: #f3e8ff; padding: 7px 12px; font-size: 12px; font-weight: 700; cursor: pointer;"
                        >
                            + Adicionar item
                        </button>
                    @endif
                </div>

                <div style="display: flex; flex-direction: column; gap: 10px;">
                    @foreach($items as $index => $itemValue)
                        <div wire:key="scope-{{ $sectionKey }}-{{ $index }}" style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 13px; font-weight: 700; color: {{ $meta['accent'] }}; min-width: 18px; text-align: center;">•</span>

                            <input
                                type="text"
                                wire:model="scopeForm.{{ $sectionKey }}.{{ $index }}"
                                placeholder="{{ $meta['placeholder'] }}"
                                @if($isLocked) disabled @endif
                                style="flex: 1; border: 1px solid #475569; border-radius: 8px; background: {{ $isLocked ? '#0b1120' : '#1e293b' }}; color: #ffffff; padding: 9px 12px; font-size: 13px;"
                            />

                            @if(! $isLocked)
                                <button
                                    type="button"
                                    wire:click="removeItem('{{ $sectionKey }}', {{ $index }})"
                                    title="Remover item"
                                    style="border: 1px solid #475569; border-radius: 8px; background: #1e293b; color: #fca5a5; padding: 8px 11px; font-size: 12px; font-weight: 700; cursor: pointer;"
                                >
                                    ✕
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- BARRA DE SALVAMENTO --}}
        @if(! $isLocked)
            <div style="display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 12px; border: 1px solid #581c87; border-radius: 12px; background: #170d2b; padding: 18px 20px;">
                <button
                    type="button"
                    wire:click="saveScope(false)"
                    wire:loading.attr="disabled"
                    style="border: 1px solid #9333ea; border-radius: 8px; background: #2e1065; color: #f3e8ff; padding: 10px 18px; font-size: 13px; font-weight: 700; cursor: pointer;"
                >
                    💾 Salvar Escopo
                </button>

                <button
                    type="button"
                    wire:click="saveScope(true)"
                    wire:loading.attr="disabled"
                    style="border: none; border-radius: 8px; background: #7c3aed; color: #ffffff; padding: 10px 20px; font-size: 13px; font-weight: 700; cursor: pointer;"
                >
                    ✓ Salvar Escopo e Ir para Proposta →
                </button>
            </div>
        @endif
    </div>
</x-filament-panels::page>

