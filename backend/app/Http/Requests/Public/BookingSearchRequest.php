<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class BookingSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'room_type_id' => ['nullable', 'integer', 'exists:room_types,id'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'check_in.after_or_equal' => 'The arrival date cannot be in the past.',
            'check_out.after' => 'The departure date must be after the arrival date.',
        ];
    }
}
