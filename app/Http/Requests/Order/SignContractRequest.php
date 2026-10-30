<?php

namespace App\Http\Requests\Order;

use App\Dto\Order\SignContractDto;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SignContractRequest extends FormRequest
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
            'accepted_terms' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accepted_terms.required' => 'Você precisa confirmar a leitura e aceite integral do contrato.',
            'accepted_terms.accepted' => 'Você precisa confirmar a leitura e aceite integral do contrato.',
        ];
    }

    public function toDto(): SignContractDto
    {
        $user = $this->user('sanctum');
        $routeOrder = $this->route('order');

        return new SignContractDto(
            user: $user instanceof User ? $user : null,
            orderId: is_numeric($routeOrder) ? (int) $routeOrder : 0,
            acceptedTerms: $this->boolean('accepted_terms'),
        );
    }
}

