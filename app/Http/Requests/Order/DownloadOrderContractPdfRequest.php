<?php

namespace App\Http\Requests\Order;

use App\Dto\Order\DownloadOrderContractPdfDto;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DownloadOrderContractPdfRequest extends FormRequest
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

    public function toDto(): DownloadOrderContractPdfDto
    {
        $user = $this->user('sanctum') ?? $this->user('web');
        $routeOrder = $this->route('order');

        return new DownloadOrderContractPdfDto(
            user: $user instanceof User ? $user : null,
            orderId: is_numeric($routeOrder) ? (int) $routeOrder : 0,
        );
    }
}

