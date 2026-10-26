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
 * Payments from the mobile client (admin + receptionist only).
 *
 * As in the web app, no card data is ever accepted or stored - only a method,
 * an amount and an optional tokenised provider reference.
 */
final class ApiPaymentController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $method = (string) $request->query('method', '');
        $bookingId = $request->integer('booking_id');
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));

        $payments = Payment::query()
            ->with(['booking.guest', 'recordedBy'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($method !== '', fn ($q) => $q->where('method', $method))
            ->when($bookingId > 0, fn ($q) => $q->where('booking_id', $bookingId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('provider_reference', 'like', "%{$search}%")
                        ->orWhereHas('booking', fn ($b) => $b->where('reference', 'like', "%{$search}%"));
                });
            })
            ->latest('created_at')
            ->paginate($perPage);

        return $this->ok($this->paginated($payments, fn (Payment $payment): array => $this->paymentRow($payment)));
    }

    /** Totals for the payments dashboard header. */
    public function summary(): JsonResponse
    {
        $paid = (float) Payment::query()->where('status', PaymentStatus::Paid->value)->sum('amount');
        $pending = (float) Payment::query()->where('status', PaymentStatus::Pending->value)->sum('amount');
        $refunded = (float) Payment::query()->where('status', PaymentStatus::Refunded->value)->sum('amount');

        return $this->ok([
            'paid_total' => $paid,
            'pending_total' => $pending,
            'refunded_total' => $refunded,
            'currency' => (string) Payment::query()->value('currency') ?: 'USD',
            'counts' => [
                'paid' => Payment::query()->where('status', PaymentStatus::Paid->value)->count(),
                'pending' => Payment::query()->where('status', PaymentStatus::Pending->value)->count(),
                'refunded' => Payment::query()->where('status', PaymentStatus::Refunded->value)->count(),
            ],
            'methods' => array_map(static fn (PaymentMethod $m): array => [
                'value' => $m->value,
                'label' => $m->label(),
            ], PaymentMethod::cases()),
        ]);
    }

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

    /** Refund a completed payment (full refund; reason is appended to notes). */
    public function refund(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $payment->isPaid()) {
            return $this->fail('Only a completed payment can be refunded.', 422, 'not_refundable');
        }

        $payment->forceFill([
            'status' => PaymentStatus::Refunded,
            'refunded_at' => now(),
            'notes' => trim(($payment->notes ?? '')."\nRefunded: ".($data['reason'] ?? 'no reason given')),
        ])->save();

        $this->audit->success(
            action: 'payment.refund',
            eventType: 'data',
            description: sprintf('Refunded payment #%d (%s).', $payment->getKey(), $payment->amount),
            subject: $payment,
            newValues: ['status' => PaymentStatus::Refunded->value, 'reason' => $data['reason'] ?? null],
        );

        return $this->ok([
            'data' => $this->paymentRow($payment->fresh(['booking.guest', 'recordedBy'])),
            'message' => 'Payment refunded.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentRow(Payment $payment): array
    {
        $payment->loadMissing(['booking.guest', 'recordedBy']);

        return $this->paymentPayload($payment) + [
            'notes' => $payment->notes,
            'booking_reference' => $payment->booking?->reference,
            'guest_name' => $payment->booking?->guest?->fullName(),
            'recorded_by' => $payment->recordedBy?->name,
        ];
    }
}
