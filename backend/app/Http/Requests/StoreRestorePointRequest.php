<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRestorePointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-restore-points') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'include_role_assignments' => ['sometimes', 'boolean'],
            'settings' => ['nullable', 'array'],
            'settings.*' => ['string'],
        ];
    }
}
