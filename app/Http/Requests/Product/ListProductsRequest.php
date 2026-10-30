<?php

namespace App\Http\Requests\Product;

use App\Dto\Product\ListProductsDto;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListProductsRequest extends FormRequest
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
        return [];
    }

    public function toDto(): ListProductsDto
    {
        $user = $this->user('sanctum');

        return new ListProductsDto(
            user: $user instanceof User ? $user : null,
        );
    }
}

