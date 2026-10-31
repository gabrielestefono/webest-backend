<?php

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Models\Order;
use App\Models\OrderScope;
use App\Models\Product;
use App\Models\Proposal;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderContractPdfService
{
    public function __construct(
        private readonly OrderTimelineService $timelineService,
    ) {}

    public function streamConsolidatedPdf(Order $order): StreamedResponse
    {
        $pdfBinary = $this->renderPdfBinary($order);
        $code = sprintf('ORD-%04d', $order->id);
        $filename = "Contrato-Escopo-{$code}.pdf";

        return response()->streamDownload(
            static function () use ($pdfBinary): void {
                echo $pdfBinary;
            },
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function renderPdfBinary(Order $order): string
    {
        $order->load([
            'user',
            'parentOrder',
            'product',
            'products',
            'attachments',
            'proposals.sender',
            'messages.sender',
            'events',
            'scope',
        ]);

        $scopeModel = $order->scope;
        if (! $scopeModel instanceof OrderScope || ! $scopeModel->isComplete()) {
            throw new BadRequestException('O Escopo do Serviço deste pedido ainda não foi cadastrado pelo prestador.');
        }

        $productsCollection = $order->products;
        if ($productsCollection->isEmpty() && $order->product instanceof Product) {
            $productsCollection = collect([$order->product]);
        }

        /** @var list<array{name: string, billing_label: string}> $products */
        $products = $productsCollection->map(static fn (Product $product): array => [
            'name' => $product->name,
            'billing_label' => $product->billingLabel(),
        ])->values()->all();

        /** @var list<string> $attachments */
        $attachments = $order->attachments
            ->map(static fn ($att): string => $att->original_name)
            ->values()
            ->all();

        $latestProposal = $order->proposals->sortByDesc('id')->first();
        $acceptedProposal = $order->proposals
            ->where('is_accepted', true)
            ->sortByDesc('id')
            ->first();

        $agreedPrice = $order->final_price !== null
            ? (float) $order->final_price
            : ($acceptedProposal instanceof Proposal
                ? (float) $acceptedProposal->price
                : ($latestProposal instanceof Proposal ? (float) $latestProposal->price : null));

        $agreedDays = $order->estimated_days
            ?? ($acceptedProposal instanceof Proposal
                ? $acceptedProposal->estimated_days
                : ($latestProposal instanceof Proposal ? $latestProposal->estimated_days : null));

        /** @var list<array{sender_name: string, role_label: string, created_at: string, status_label: string, price: float, estimated_days: int, content: string}> $proposals */
        $proposals = $order->proposals->sortBy('id')->map(static function (Proposal $prop) use ($order): array {
            $statusLabel = match ($prop->is_accepted) {
                true => 'Aceita / Acordada',
                false => 'Superada / Recusada',
                default => 'Pendente de resposta',
            };

            return [
                'sender_name' => $prop->sender?->name ?? 'Usuário',
                'role_label' => $prop->sender_id === $order->user_id ? 'Cliente' : 'Prestador WeBest',
                'created_at' => $prop->created_at?->format('d/m/Y H:i') ?? '-',
                'status_label' => $statusLabel,
                'price' => (float) $prop->price,
                'estimated_days' => (int) ($prop->estimated_days ?: 7),
                'content' => trim((string) $prop->content),
            ];
        })->values()->all();

        $serializedRoom = $this->timelineService->serializeOrderRoom($order);
        /** @var list<array<string, mixed>> $rawTimeline */
        $rawTimeline = is_array($serializedRoom['timeline'] ?? null) ? $serializedRoom['timeline'] : [];

        /** @var list<array{kind: string, author: string, timestamp: string, body: string}> $timeline */
        $timeline = [];
        foreach ($rawTimeline as $item) {
            $itemType = (string) ($item['item_type'] ?? '');
            $createdAtIso = is_string($item['created_at'] ?? null) ? $item['created_at'] : null;
            $formattedTs = $createdAtIso !== null
                ? \Illuminate\Support\Carbon::parse($createdAtIso)->format('d/m/Y H:i')
                : '-';

            if ($itemType === 'bot_event') {
                $title = (string) ($item['title'] ?? 'Evento');
                $msg = (string) ($item['message'] ?? '');
                $timeline[] = [
                    'kind' => 'bot',
                    'author' => "Sistema WeBest • {$title}",
                    'timestamp' => $formattedTs,
                    'body' => $msg,
                ];
            } else {
                $senderRole = (string) ($item['sender_role'] ?? 'client');
                $senderName = (string) ($item['sender_name'] ?? 'Participante');
                $roleTag = $senderRole === 'provider' ? 'Prestador' : 'Cliente';
                $timeline[] = [
                    'kind' => $senderRole === 'provider' ? 'provider' : 'client',
                    'author' => "{$senderName} ({$roleTag})",
                    'timestamp' => $formattedTs,
                    'body' => (string) ($item['current_content'] ?? ''),
                ];
            }
        }

        $html = view('pdf.order-contract', [
            'order' => $order,
            'code' => sprintf('#ORD-%04d', $order->id),
            'parentCode' => $order->parentOrder instanceof Order
                ? sprintf('#ORD-%04d', $order->parentOrder->id)
                : null,
            'generatedAt' => now()->format('d/m/Y H:i'),
            'statusLabel' => Order::statusLabel($order->status),
            'signedAt' => $order->contract_signed_at?->format('d/m/Y H:i'),
            'agreedPrice' => $agreedPrice,
            'agreedDays' => $agreedDays,
            'products' => $products,
            'attachments' => $attachments,
            'scope' => [
                'included_items' => $scopeModel->normalizedIncludedItems(),
                'excluded_items' => $scopeModel->normalizedExcludedItems(),
                'delivery_criteria' => $scopeModel->normalizedDeliveryCriteria(),
                'revision_rules' => $scopeModel->normalizedRevisionRules(),
                'client_responsibilities' => $scopeModel->normalizedClientResponsibilities(),
            ],
            'proposals' => $proposals,
            'timeline' => $timeline,
        ])->render();

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}

