<?php

namespace App\Http\Requests\Order;

use App\Dto\Order\CompleteOrderDto;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompleteOrderRequest extends FormRequest
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

    public function toDto(): CompleteOrderDto
    {
        $user = $this->user('sanctum');
        $routeOrder = $this->route('order');

        return new CompleteOrderDto(
            user: $user instanceof User ? $user : null,
            orderId: is_numeric($routeOrder) ? (int) $routeOrder : 0,
        );
    }
}

