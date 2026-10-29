<?php

namespace App\Http\Requests\Auth;

use App\Dto\Auth\SendConfirmationEmailDto;
use App\Exceptions\UnauthorizedException;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SendConfirmationEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function toDto(): SendConfirmationEmailDto
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new UnauthorizedException('Usuário não autenticado.');
        }

        return new SendConfirmationEmailDto(
            user: $user,
        );
    }
}