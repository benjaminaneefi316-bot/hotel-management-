<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-payments') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'method' => ['required', Rule::in(PaymentMethod::values())],
            'status' => ['required', Rule::in([PaymentStatus::Paid->value, PaymentStatus::Pending->value])],
            'provider' => ['nullable', 'string', 'max:60'],
            'provider_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
