<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Record a payment against a booking (admin + receptionist only).
 *
 * As in the web app, no card data is ever accepted or stored - only a method,
 * an amount and an optional tokenised provider reference.
 */
final class ApiPaymentController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'method' => ['required', Rule::in(PaymentMethod::values())],
            'status' => ['nullable', Rule::in([PaymentStatus::Paid->value, PaymentStatus::Pending->value])],
            'provider' => ['nullable', 'string', 'max:60'],
            'provider_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $status = $data['status'] ?? PaymentStatus::Paid->value;

        $payment = Payment::query()->create([
            'booking_id' => $data['booking_id'],
            'user_id' => $request->user()->getKey(),
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'USD',
            'method' => $data['method'],
            'status' => $status,
            'provider' => $data['provider'] ?? null,
            'provider_reference' => $data['provider_reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'paid_at' => $status === PaymentStatus::Paid->value ? now() : null,
        ]);

        $this->audit->success(
            action: 'payment.create',
            eventType: 'data',
            description: sprintf('Recorded a %s payment of %s for booking #%d.', $data['method'], $data['amount'], $data['booking_id']),
            subject: $payment,
            newValues: ['amount' => $data['amount'], 'method' => $data['method'], 'status' => $status],
        );

        return $this->ok(['data' => $this->paymentPayload($payment)], 201);
    }
}
