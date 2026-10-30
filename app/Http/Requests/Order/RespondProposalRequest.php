<?php

namespace App\Http\Requests\Order;

use App\Dto\Order\RespondProposalDto;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RespondProposalRequest extends FormRequest
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
            'decision' => ['required', 'string', 'in:accept,counter,cancel'],
            'counter_price' => ['required_if:decision,counter', 'nullable', 'numeric', 'min:1'],
            'counter_days' => ['required_if:decision,counter', 'nullable', 'integer', 'min:1', 'max:365'],
            'counter_content' => ['required_if:decision,counter', 'nullable', 'string', 'max:3000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Escolha uma ação válida para a proposta.',
            'decision.in' => 'Decisão inválida.',
            'counter_price.required_if' => 'Informe o valor da sua contra-proposta.',
            'counter_price.min' => 'O valor da contra-proposta deve ser maior que zero.',
            'counter_days.required_if' => 'Informe o prazo estimado em dias úteis.',
            'counter_content.required_if' => 'Adicione uma justificativa ou observação para a contra-proposta.',
        ];
    }

    public function toDto(): RespondProposalDto
    {
        $user = $this->user('sanctum');
        $routeOrder = $this->route('order');
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return new RespondProposalDto(
            user: $user instanceof User ? $user : null,
            orderId: is_numeric($routeOrder) ? (int) $routeOrder : 0,
            decision: (string) ($validated['decision'] ?? ''),
            counterPrice: isset($validated['counter_price']) && is_numeric($validated['counter_price'])
                ? (float) $validated['counter_price']
                : null,
            counterDays: isset($validated['counter_days']) && is_numeric($validated['counter_days'])
                ? (int) $validated['counter_days']
                : null,
            counterContent: isset($validated['counter_content']) && is_string($validated['counter_content'])
                ? trim($validated['counter_content'])
                : null,
        );
    }
}

