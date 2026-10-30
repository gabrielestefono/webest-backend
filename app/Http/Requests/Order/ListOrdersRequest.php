<?php

namespace App\Http\Requests\Order;

use App\Dto\Order\ListOrdersDto;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListOrdersRequest extends FormRequest
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
            'status' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function toDto(): ListOrdersDto
    {
        $user = $this->user('sanctum');
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return new ListOrdersDto(
            user: $user instanceof User ? $user : null,
            statusFilter: isset($validated['status']) && is_string($validated['status']) ? $validated['status'] : null,
        );
    }
}

