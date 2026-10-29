<?php

namespace App\Http\Requests\Auth;

use App\Dto\Auth\LogoutDto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LogoutRequest extends FormRequest
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
            'token' => ['nullable', 'string'],
        ];
    }

    /**
     * Convert validated request data to a strongly typed DTO.
     */
    public function toDto(): LogoutDto
    {
        $validated = $this->validated();
        $token = $this->bearerToken() ?: ($validated['token'] ?? null);

        return new LogoutDto(
            token: $token,
            user: $this->user('sanctum'),
        );
    }
}
