<?php

namespace App\Http\Requests\Order;

use App\Dto\Order\RespondAddendumDto;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RespondAddendumRequest extends FormRequest
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
            'decision' => ['required', 'string', 'in:accept_and_pay,counter,decline'],
            'counter_price' => ['required_if:decision,counter', 'nullable', 'numeric', 'min:1'],
            'counter_notes' => ['required_if:decision,counter', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Escolha uma decisão para o Termo Aditivo.',
            'decision.in' => 'Decisão inválida.',
            'counter_price.required_if' => 'Informe o valor sugerido na contra-proposta do aditivo.',
            'counter_notes.required_if' => 'Justifique sua contra-proposta para o Termo Aditivo.',
        ];
    }

    public function toDto(): RespondAddendumDto
    {
        $user = $this->user('sanctum');
        $routeOrder = $this->route('order');
        $routeAdjustment = $this->route('adjustment');
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return new RespondAddendumDto(
            user: $user instanceof User ? $user : null,
            orderId: is_numeric($routeOrder) ? (int) $routeOrder : 0,
            adjustmentId: is_numeric($routeAdjustment) ? (int) $routeAdjustment : 0,
            decision: (string) ($validated['decision'] ?? ''),
            counterPrice: isset($validated['counter_price']) && is_numeric($validated['counter_price'])
                ? (float) $validated['counter_price']
                : null,
            counterNotes: isset($validated['counter_notes']) && is_string($validated['counter_notes'])
                ? trim($validated['counter_notes'])
                : null,
        );
    }
}

