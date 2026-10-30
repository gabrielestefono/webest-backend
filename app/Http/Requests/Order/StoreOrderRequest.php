<?php

namespace App\Http\Requests\Order;

use App\Dto\Order\StoreOrderDto;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
            'figma_url' => ['nullable', 'url', 'max:500'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'integer', 'exists:products,id'],
            'initial_message' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:8'],
            'attachments.*' => ['file', 'max:15360', 'mimes:png,jpg,jpeg,webp,pdf,zip'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Informe um título para o seu projeto.',
            'title.min' => 'O título deve ter pelo menos 3 caracteres.',
            'description.required' => 'Descreva o que você deseja no projeto.',
            'description.min' => 'A descrição do projeto deve ter pelo menos 10 caracteres.',
            'figma_url.url' => 'Informe um link válido para o Figma (iniciando com https://).',
            'product_ids.required' => 'Selecione pelo menos um produto ou serviço.',
            'product_ids.min' => 'Selecione pelo menos um produto ou serviço.',
            'product_ids.*.exists' => 'Um dos produtos selecionados não é válido.',
            'attachments.max' => 'Você pode anexar no máximo 8 arquivos por pedido.',
            'attachments.*.max' => 'Cada arquivo deve ter no máximo 15 MB.',
            'attachments.*.mimes' => 'Formatos permitidos: PNG, JPG, WEBP, PDF ou ZIP.',
        ];
    }

    public function toDto(): StoreOrderDto
    {
        $user = $this->user('sanctum');
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        /** @var list<int> $productIds */
        $productIds = [];
        if (isset($validated['product_ids']) && is_array($validated['product_ids'])) {
            foreach ($validated['product_ids'] as $id) {
                $productIds[] = (int) $id;
            }
        }

        /** @var list<UploadedFile> $files */
        $files = [];
        $rawFiles = $this->file('attachments');
        if (is_array($rawFiles)) {
            foreach ($rawFiles as $file) {
                if ($file instanceof UploadedFile) {
                    $files[] = $file;
                }
            }
        } elseif ($rawFiles instanceof UploadedFile) {
            $files[] = $rawFiles;
        }

        return new StoreOrderDto(
            user: $user instanceof User ? $user : null,
            title: (string) ($validated['title'] ?? ''),
            description: (string) ($validated['description'] ?? ''),
            figmaUrl: isset($validated['figma_url']) && is_string($validated['figma_url']) && trim($validated['figma_url']) !== ''
                ? trim($validated['figma_url'])
                : null,
            productIds: array_values(array_unique($productIds)),
            initialMessage: isset($validated['initial_message']) && is_string($validated['initial_message']) && trim($validated['initial_message']) !== ''
                ? trim($validated['initial_message'])
                : null,
            attachments: $files,
        );
    }
}

