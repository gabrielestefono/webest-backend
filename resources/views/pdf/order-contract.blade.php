<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Escopo e Contrato — {{ $code }}</title>
    <style>
        @page {
            margin: 28px 34px;
        }
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: #1e293b;
        }
        .header-box {
            background-color: #1e1038;
            color: #f8fafc;
            padding: 18px 20px;
            border-radius: 6px;
            margin-bottom: 16px;
        }
        .brand {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #c084fc;
            font-weight: bold;
        }
        .doc-title {
            font-size: 18px;
            font-weight: bold;
            margin: 4px 0 2px 0;
            color: #ffffff;
        }
        .doc-subtitle {
            font-size: 12px;
            color: #e9d5ff;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .meta-table td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            vertical-align: top;
            width: 50%;
        }
        .meta-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .meta-value {
            font-size: 11px;
            color: #0f172a;
            font-weight: bold;
        }
        .section {
            margin-bottom: 16px;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #3b0764;
            border-bottom: 2px solid #9333ea;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .scope-block {
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            border-radius: 5px;
            padding: 10px 12px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .scope-block-excluded {
            border-color: #fecaca;
            background-color: #fef2f2;
        }
        .scope-heading {
            font-size: 11.5px;
            font-weight: bold;
            color: #1e293b;
            margin: 0 0 5px 0;
        }
        .scope-heading-excluded {
            color: #991b1b;
        }
        ul.scope-list {
            margin: 0;
            padding-left: 18px;
        }
        ul.scope-list li {
            margin-bottom: 3px;
        }
        .box-plain {
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 10px 12px;
            background-color: #ffffff;
            white-space: pre-wrap;
        }
        .proposal-item {
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 8px 10px;
            margin-bottom: 6px;
            page-break-inside: avoid;
        }
        .chat-item {
            border-left: 3px solid #7c3aed;
            background-color: #f8fafc;
            padding: 7px 10px;
            margin-bottom: 6px;
            page-break-inside: avoid;
        }
        .chat-item-client {
            border-left-color: #0284c7;
        }
        .chat-item-bot {
            border-left-color: #64748b;
            background-color: #f1f5f9;
        }
        .chat-meta {
            font-size: 9.5px;
            color: #475569;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .signature-box {
            border: 2px solid #7c3aed;
            background-color: #faf5ff;
            border-radius: 6px;
            padding: 12px 14px;
            margin-top: 16px;
            page-break-inside: avoid;
        }
        .badge {
            display: inline-block;
            padding: 2px 7px;
            font-size: 9.5px;
            border-radius: 4px;
            background-color: #ede9fe;
            color: #5b21b6;
            font-weight: bold;
            margin-right: 4px;
            margin-bottom: 3px;
        }
        .muted {
            color: #64748b;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header-box">
        <div class="brand">WeBest Coding • Documento Contratual &amp; Escopo do Serviço</div>
        <div class="doc-title">Escopo do Serviço</div>
        <div class="doc-subtitle">
            Pedido {{ $code }} • {{ $order->title }}
            @if($parentCode)
                (Originado de {{ $parentCode }})
            @endif
        </div>
    </div>

    <table class="meta-table">
        <tr>
            <td>
                <div class="meta-label">Contratante (Cliente)</div>
                <div class="meta-value">{{ $order->user?->name ?? 'Cliente' }}</div>
                <div class="muted">{{ $order->user?->email ?? '' }}</div>
            </td>
            <td>
                <div class="meta-label">Contratada (Prestador)</div>
                <div class="meta-value">WeBest Coding</div>
                <div class="muted">Emitido em {{ $generatedAt }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="meta-label">Condição Comercial &amp; Prazo</div>
                <div class="meta-value">
                    @if($agreedPrice !== null)
                        R$ {{ number_format($agreedPrice, 2, ',', '.') }} • {{ $agreedDays ?? 7 }} dias úteis
                    @else
                        Em definição na negociação
                    @endif
                </div>
                <div class="muted">Garantia técnica de 30 dias após homologação final</div>
            </td>
            <td>
                <div class="meta-label">Status no Momento da Emissão</div>
                <div class="meta-value">{{ $statusLabel }}</div>
                <div class="muted">
                    @if($signedAt)
                        Assinado eletronicamente em {{ $signedAt }}
                    @else
                        Aguardando aceite digital do cliente
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">1. Produtos Vinculados &amp; Briefing Inicial</div>
        <div style="margin-bottom: 8px;">
            @foreach($products as $product)
                <span class="badge">{{ $product['name'] }} ({{ $product['billing_label'] }})</span>
            @endforeach
        </div>
        <div class="box-plain">{{ $order->description }}</div>
        @if($order->figma_url)
            <div class="muted" style="margin-top: 5px;"><strong>Protótipo / Figma informado:</strong> {{ $order->figma_url }}</div>
        @endif
        @if(count($attachments) > 0)
            <div class="muted" style="margin-top: 4px;">
                <strong>Anexos enviados no briefing:</strong>
                {{ implode(', ', $attachments) }}
            </div>
        @endif
    </div>

    <div class="section">
        <div class="section-title">2. Escopo Oficial do Serviço</div>

        <div class="scope-block">
            <div class="scope-heading">O que será feito</div>
            <ul class="scope-list">
                @foreach($scope['included_items'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>

        <div class="scope-block scope-block-excluded">
            <div class="scope-heading scope-heading-excluded">O que NÃO será feito</div>
            <ul class="scope-list">
                @foreach($scope['excluded_items'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>

        <div class="scope-block">
            <div class="scope-heading">Critérios de entrega</div>
            <ul class="scope-list">
                @foreach($scope['delivery_criteria'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>

        <div class="scope-block">
            <div class="scope-heading">Revisões</div>
            <ul class="scope-list">
                @foreach($scope['revision_rules'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>

        <div class="scope-block">
            <div class="scope-heading">Responsabilidades do cliente</div>
            <ul class="scope-list">
                @foreach($scope['client_responsibilities'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    </div>

    @if(count($proposals) > 0)
        <div class="section">
            <div class="section-title">3. Histórico de Propostas Comerciais</div>
            @foreach($proposals as $prop)
                <div class="proposal-item">
                    <div class="chat-meta">
                        {{ $prop['sender_name'] }} ({{ $prop['role_label'] }}) • {{ $prop['created_at'] }} • Status: {{ $prop['status_label'] }}
                    </div>
                    <div>
                        <strong>Valor:</strong> R$ {{ number_format($prop['price'], 2, ',', '.') }}
                        &nbsp;|&nbsp;
                        <strong>Prazo estimado:</strong> {{ $prop['estimated_days'] }} dias úteis
                    </div>
                    @if($prop['content'] !== '')
                        <div style="margin-top: 4px; white-space: pre-wrap;">{{ $prop['content'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="section">
        <div class="section-title">4. Registro Integral da Conversa &amp; Linha do Tempo até o Momento</div>
        @forelse($timeline as $entry)
            <div class="chat-item {{ $entry['kind'] === 'client' ? 'chat-item-client' : ($entry['kind'] === 'bot' ? 'chat-item-bot' : '') }}">
                <div class="chat-meta">{{ $entry['author'] }} • {{ $entry['timestamp'] }}</div>
                <div style="white-space: pre-wrap;">{{ $entry['body'] }}</div>
            </div>
        @empty
            <div class="muted">Nenhuma mensagem registrada até o momento.</div>
        @endforelse
    </div>

    <div class="signature-box">
        <div style="font-weight: bold; font-size: 12px; color: #3b0764; margin-bottom: 4px;">
            Cláusula de Concordância, Garantia de 30 Dias e Aceite Digital
        </div>
        <div style="font-size: 10.5px; margin-bottom: 8px;">
            As partes reconhecem que este documento consolida o escopo técnico, limites de atuação, critérios de homologação, histórico comercial e comunicações registradas na plataforma WeBest Coding para o pedido {{ $code }}. Solicitações fora da seção "O que será feito" ou listadas em "O que NÃO será feito" constituem novo escopo e deverão tramitar em novo pedido independente. Após a aprovação final da entrega, aplica-se garantia técnica de 30 (trinta) dias corridos exclusivamente sobre o escopo contratado, desde que o código-fonte não seja modificado por terceiros.
        </div>
        @if($signedAt)
            <div style="font-weight: bold; color: #15803d;">
                ✓ ACEITO E ASSINADO DIGITALMENTE POR {{ mb_strtoupper($order->user?->name ?? 'CLIENTE') }} ({{ $order->user?->email ?? '' }}) EM {{ $signedAt }}.
            </div>
        @else
            <div style="font-weight: bold; color: #92400e;">
                ⏳ DOCUMENTO CONSOLIDADO PARA CONFERÊNCIA — AGUARDANDO ASSINATURA DIGITAL NA PLATAFORMA.
            </div>
        @endif
    </div>
</body>
</html>

