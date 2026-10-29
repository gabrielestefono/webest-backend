<?php

namespace App\Http\Requests\Auth;

use App\Dto\Auth\LoginDto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'O campo e-mail é obrigatório.',
            'email.email' => 'Informe um endereço de e-mail válido.',
            'password.required' => 'O campo senha é obrigatório.',
        ];
    }

    /**
     * Convert validated request data to a strongly typed DTO.
     */
    public function toDto(): LoginDto
    {
        $validated = $this->validated();

        return new LoginDto(
            email: $validated['email'],
            password: $validated['password'],
        );
    }
}
