<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RoomStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-rooms') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'room_number' => ['required', 'string', 'max:20', 'unique:rooms,room_number'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'floor' => ['nullable', 'integer', 'min:0', 'max:200'],
            'status' => ['required', Rule::in(RoomStatus::values())],
            'nightly_rate' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'view' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
