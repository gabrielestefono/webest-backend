<?php

namespace App\Actions\Order;

use App\Dto\Order\StoreOrderDto;
use App\Exceptions\BadRequestException;
use App\Exceptions\UnauthorizedException;
use App\Models\Order;
use App\Models\OrderAttachment;
use App\Models\Product;
use App\Services\OrderTimelineService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreOrderAction
{
    public function __construct(
        private readonly OrderTimelineService $timelineService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(StoreOrderDto $dto): array
    {
        $user = $dto->user;
        if (! $user) {
            throw new UnauthorizedException('Usuário não autenticado.');
        }

        $activeProductIds = Product::query()
            ->whereIn('id', $dto->productIds)
            ->where('active', true)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($activeProductIds === []) {
            throw new BadRequestException('Nenhum produto ativo válido foi selecionado.');
        }

        $order = DB::transaction(function () use ($dto, $user, $activeProductIds): Order {
            $createdOrder = Order::query()->create([
                'user_id' => $user->id,
                'product_id' => $activeProductIds[0],
                'title' => trim($dto->title),
                'description' => trim($dto->description),
                'figma_url' => $dto->figmaUrl,
                'status' => 'under_review',
            ]);

            $createdOrder->products()->sync($activeProductIds);

            foreach ($dto->attachments as $uploadedFile) {
                $extension = strtolower($uploadedFile->getClientOriginalExtension() ?: 'bin');
                $safeName = Str::uuid()->toString().'.'.$extension;
                $directory = "orders/{$createdOrder->id}";

                $storedPath = Storage::disk('private')->putFileAs($directory, $uploadedFile, $safeName);
                if (! is_string($storedPath) || $storedPath === '') {
                    throw new BadRequestException('Falha ao salvar um dos anexos do projeto.');
                }

                OrderAttachment::query()->create([
                    'order_id' => $createdOrder->id,
                    'uploaded_by_id' => $user->id,
                    'file_path' => $storedPath,
                    'original_name' => $uploadedFile->getClientOriginalName(),
                    'mime_type' => $uploadedFile->getClientMimeType() ?: 'application/octet-stream',
                    'file_size' => (int) $uploadedFile->getSize(),
                    'disk' => 'private',
                ]);
            }

            $firstTurnContent = $dto->initialMessage ?? trim($dto->description);
            $this->timelineService->sendTurnMessage(
                order: $createdOrder,
                sender: $user,
                senderRole: 'client',
                content: $firstTurnContent,
            );

            $this->timelineService->recordEvent(
                order: $createdOrder,
                eventType: 'order_submitted',
                title: 'Pedido Recebido e em Análise',
                message: 'O seu pedido foi enviado e está em análise pela equipe técnica da WeBest. Enquanto nossa equipe ainda não respondeu, você pode editar sua mensagem inicial mantendo o histórico.',
                payload: [
                    'product_ids' => $activeProductIds,
                ],
                actorId: $user->id,
            );

            return $createdOrder;
        });

        return $this->timelineService->serializeOrderRoom($order);
    }
}

