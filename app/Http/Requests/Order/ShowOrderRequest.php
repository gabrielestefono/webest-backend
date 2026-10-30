<?php

namespace App\Http\Requests\Order;

use App\Dto\Order\ShowOrderDto;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowOrderRequest extends FormRequest
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

    public function toDto(): ShowOrderDto
    {
        $user = $this->user('sanctum');
        $routeOrder = $this->route('order');
        $orderId = is_numeric($routeOrder) ? (int) $routeOrder : 0;

        return new ShowOrderDto(
            user: $user instanceof User ? $user : null,
            orderId: $orderId,
        );
    }
}

