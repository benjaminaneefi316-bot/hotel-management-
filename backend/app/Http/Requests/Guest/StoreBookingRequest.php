<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('book-own-room') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:10'],
            'children' => ['nullable', 'integer', 'min:0', 'max:10'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'check_out_date.after' => 'The departure date must be after the arrival date.',
        ];
    }
}
