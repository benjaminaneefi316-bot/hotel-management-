<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RoomStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update-room-status') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(RoomStatus::values())],
        ];
    }
}
