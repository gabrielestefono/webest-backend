<?php

namespace App\Http\Requests\Dashboard;

use App\Dto\Dashboard\DashboardOverviewDto;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DashboardOverviewRequest extends FormRequest
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

    public function toDto(): DashboardOverviewDto
    {
        $user = $this->user('sanctum');

        return new DashboardOverviewDto(
            user: $user instanceof User ? $user : null,
        );
    }
}

