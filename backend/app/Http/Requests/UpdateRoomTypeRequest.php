<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-room-types') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $typeId = $this->route('roomType')?->getKey();

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('room_types', 'code')->ignore($typeId)],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'bed_type' => ['required', 'string', 'max:40'],
            'capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'amenities' => ['nullable', 'string', 'max:1000'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
