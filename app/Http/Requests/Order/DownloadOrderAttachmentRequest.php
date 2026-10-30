<?php

namespace App\Http\Requests\Order;

use App\Dto\Order\DownloadOrderAttachmentDto;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DownloadOrderAttachmentRequest extends FormRequest
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

    public function toDto(): DownloadOrderAttachmentDto
    {
        $user = $this->user('sanctum') ?? $this->user('web');
        $routeOrder = $this->route('order');
        $routeAttachment = $this->route('attachment');

        return new DownloadOrderAttachmentDto(
            user: $user instanceof User ? $user : null,
            orderId: is_numeric($routeOrder) ? (int) $routeOrder : 0,
            attachmentId: is_numeric($routeAttachment) ? (int) $routeAttachment : 0,
        );
    }
}

