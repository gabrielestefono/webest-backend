<?php

namespace App\Http\Requests\Auth;

use App\Dto\Auth\VerifyEmailDto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailRequest extends FormRequest
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
            'id' => ['required', 'integer'],
            'hash' => ['required', 'string'],
            'expires' => ['required', 'integer'],
            'signature' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id.required' => 'Identificador do usuário ausente.',
            'hash.required' => 'Hash de validação ausente.',
            'expires.required' => 'Prazo de expiração ausente.',
            'signature.required' => 'Assinatura digital ausente.',
        ];
    }

    public function toDto(): VerifyEmailDto
    {
        return new VerifyEmailDto(
            id: (int) $this->validated('id'),
            hash: (string) $this->validated('hash'),
            expires: (int) $this->validated('expires'),
            signature: (string) $this->validated('signature'),
        );
    }
}

