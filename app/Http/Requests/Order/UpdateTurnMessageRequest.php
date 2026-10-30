<?php

namespace App\Http\Requests\Order;

use App\Dto\Order\UpdateTurnMessageDto;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTurnMessageRequest extends FormRequest
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
            'content' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required' => 'O texto editado não pode estar vazio.',
            'content.max' => 'A mensagem pode ter no máximo 5000 caracteres.',
        ];
    }

    public function toDto(): UpdateTurnMessageDto
    {
        $user = $this->user('sanctum');
        $routeOrder = $this->route('order');
        $routeMessage = $this->route('message');
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return new UpdateTurnMessageDto(
            user: $user instanceof User ? $user : null,
            orderId: is_numeric($routeOrder) ? (int) $routeOrder : 0,
            messageId: is_numeric($routeMessage) ? (int) $routeMessage : 0,
            content: (string) ($validated['content'] ?? ''),
        );
    }
}

