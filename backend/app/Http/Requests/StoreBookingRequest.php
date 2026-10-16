<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BookingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-bookings') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'guest_id' => ['required', 'integer', 'exists:guests,id'],
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'room_ids' => ['required', 'array', 'min:1'],
            'room_ids.*' => ['integer', 'exists:rooms,id'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'internal_notes' => ['nullable', 'string', 'max:1000'],
            'source' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', Rule::in(BookingStatus::values())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'room_ids.required' => 'Select at least one room.',
            'check_out_date.after' => 'The check-out date must be after the check-in date.',
        ];
    }
}
