<?php

namespace App\Http\Requests\Order;

use App\Dto\Order\StoreAdjustmentDto;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAdjustmentRequest extends FormRequest
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
            'description' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'description.required' => 'Descreva detalhadamente os pontos que deseja revisar.',
            'description.min' => 'A descrição do ajuste deve ter pelo menos 10 caracteres.',
        ];
    }

    public function toDto(): StoreAdjustmentDto
    {
        $user = $this->user('sanctum');
        $routeOrder = $this->route('order');
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return new StoreAdjustmentDto(
            user: $user instanceof User ? $user : null,
            orderId: is_numeric($routeOrder) ? (int) $routeOrder : 0,
            description: (string) ($validated['description'] ?? ''),
        );
    }
}

