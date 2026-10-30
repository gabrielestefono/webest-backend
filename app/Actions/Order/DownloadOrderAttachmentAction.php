<?php

namespace App\Actions\Order;

use App\Dto\Order\DownloadOrderAttachmentDto;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Models\Order;
use App\Models\OrderAttachment;
use App\Policies\OrderPolicy;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadOrderAttachmentAction
{
    public function __construct(
        private readonly OrderPolicy $policy,
    ) {}

    public function handle(DownloadOrderAttachmentDto $dto): StreamedResponse
    {
        $user = $dto->user;
        if (! $user) {
            throw new UnauthorizedException('Usuário não autenticado.');
        }

        $order = Order::query()->find($dto->orderId);
        if (! $order) {
            throw new NotFoundException('Pedido não encontrado.');
        }

        if (! $this->policy->view($user, $order)) {
            throw new ForbiddenException('Acesso negado ao arquivo solicitado.');
        }

        $attachment = OrderAttachment::query()
            ->where('order_id', $order->id)
            ->whereKey($dto->attachmentId)
            ->first();

        if (! $attachment) {
            throw new NotFoundException('Anexo não encontrado.');
        }

        $disk = Storage::disk($attachment->disk ?: 'private');
        if (! $disk->exists($attachment->file_path)) {
            throw new NotFoundException('O arquivo físico não foi localizado no armazenamento seguro.');
        }

        return $disk->download(
            $attachment->file_path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}

