<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-bookings') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'room_ids' => ['nullable', 'array'],
            'room_ids.*' => ['integer', 'exists:rooms,id'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'internal_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'check_out_date.after' => 'The check-out date must be after the check-in date.',
        ];
    }
}
