<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\RefundPaymentRequest;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $payments = Payment::query()
            ->with(['booking.guest', 'recordedBy'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->input('method')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = (string) $request->input('search');
                $query->where(function ($q) use ($term): void {
                    $q->where('provider_reference', 'like', "%{$term}%")
                        ->orWhereHas('booking', fn ($b) => $b->where('reference', 'like', "%{$term}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('payments.index', [
            'payments' => $payments,
            'statuses' => PaymentStatus::cases(),
            'methods' => PaymentMethod::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        $bookings = Booking::query()
            ->with('guest')
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value, BookingStatus::CheckedIn->value])
            ->orderByDesc('check_in_date')
            ->get();

        return view('payments.create', [
            'bookings' => $bookings,
            'methods' => PaymentMethod::cases(),
            'statuses' => PaymentStatus::cases(),
            'selectedBookingId' => $request->integer('booking_id') ?: null,
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $this->authorize('manage-payments');

        $data = $request->validated();
        $status = $data['status'];

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

        return redirect()->route('payments.show', $payment)->with('status', 'Payment recorded.');
    }

    public function show(Payment $payment): View
    {
        $payment->load(['booking.guest', 'booking.rooms', 'recordedBy']);

        return view('payments.show', ['payment' => $payment]);
    }

    public function refund(RefundPaymentRequest $request, Payment $payment): RedirectResponse
    {
        $this->authorize('manage-payments');

        if (! $payment->isPaid()) {
            throw new BusinessRuleException('Only a completed payment can be refunded.');
        }

        $payment->forceFill([
            'status' => PaymentStatus::Refunded,
            'refunded_at' => now(),
            'notes' => trim(($payment->notes ?? '')."\nRefunded: ".($request->validated()['reason'] ?? 'no reason given')),
        ])->save();

        return redirect()->route('payments.show', $payment)->with('status', 'Payment refunded.');
    }
}
