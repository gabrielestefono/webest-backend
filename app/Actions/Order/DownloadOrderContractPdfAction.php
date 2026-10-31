<?php

namespace App\Actions\Order;

use App\Dto\Order\DownloadOrderContractPdfDto;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Models\Order;
use App\Policies\OrderPolicy;
use App\Services\OrderContractPdfService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadOrderContractPdfAction
{
    public function __construct(
        private readonly OrderPolicy $policy,
        private readonly OrderContractPdfService $pdfService,
    ) {}

    public function handle(DownloadOrderContractPdfDto $dto): StreamedResponse
    {
        $user = $dto->user;
        if (! $user) {
            throw new UnauthorizedException('Usuário não autenticado.');
        }

        $order = Order::query()->with('scope')->find($dto->orderId);
        if (! $order) {
            throw new NotFoundException('Pedido não encontrado.');
        }

        if (! $this->policy->view($user, $order)) {
            throw new ForbiddenException('Acesso negado ao contrato deste pedido.');
        }

        if (! $order->hasCompleteScope()) {
            throw new BadRequestException('O Escopo do Serviço deste pedido ainda não foi disponibilizado.');
        }

        return $this->pdfService->streamConsolidatedPdf($order);
    }
}

