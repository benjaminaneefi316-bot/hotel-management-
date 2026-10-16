<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * Shared plumbing for the REST API consumed by the Flutter application.
 *
 * Every endpoint answers with JSON. Error bodies always carry a human-readable
 * `message` (and often a machine-readable `reason`) so the mobile client can
 * show something sensible without inventing its own copy.
 */
abstract class ApiController extends Controller
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function ok(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }

    protected function fail(string $message, int $status = 422, ?string $reason = null): JsonResponse
    {
        $payload = ['message' => $message];

        if ($reason !== null) {
            $payload['reason'] = $reason;
        }

        return response()->json($payload, $status);
    }

    /**
     * Wrap a paginator as `{ data: [...], meta: {...} }`.
     *
     * @param  callable(mixed): array<string, mixed>  $transform
     * @return array{data: array<int, mixed>, meta: array<string, mixed>}
     */
    protected function paginated(LengthAwarePaginator $paginator, callable $transform): array
    {
        return [
            'data' => collect($paginator->items())->map($transform)->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status->value,
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
            'roles' => $user->roleNames(),
            'is_staff' => $user->isAdmin() || $user->isReceptionist(),
            'guest_profile_id' => $user->guestProfiles()->value('id'),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function roomTypePayload(RoomType $type): array
    {
        return [
            'id' => $type->getKey(),
            'code' => $type->code,
            'name' => $type->name,
            'slug' => $type->slug,
            'description' => $type->description,
            'bed_type' => $type->bed_type,
            'capacity' => (int) $type->capacity,
            'base_price' => (float) $type->base_price,
            'amenities' => $type->amenities ?? [],
            'image_url' => $type->image_url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function roomPayload(Room $room): array
    {
        return [
            'id' => $room->getKey(),
            'room_number' => $room->room_number,
            'floor' => $room->floor,
            'status' => $room->status->value,
            'status_label' => $room->status->label(),
            'effective_rate' => (float) $room->effectiveRate(),
            'room_type' => $room->roomType instanceof RoomType
                ? [
                    'id' => $room->roomType->getKey(),
                    'name' => $room->roomType->name,
                    'capacity' => (int) $room->roomType->capacity,
                    'bed_type' => (string) $room->roomType->bed_type,
                    'image_url' => $room->roomType->image_url,
                ]
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function guestPayload(Guest $guest): array
    {
        return [
            'id' => $guest->getKey(),
            'first_name' => $guest->first_name,
            'last_name' => $guest->last_name,
            'full_name' => $guest->fullName(),
            'email' => $guest->email,
            'phone' => $guest->phone,
            'nationality' => $guest->nationality,
            'city' => $guest->city,
            'country' => $guest->country,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function paymentPayload(Payment $payment): array
    {
        return [
            'id' => $payment->getKey(),
            'booking_id' => $payment->booking_id,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'method' => $payment->method?->value,
            'method_label' => $payment->method?->label(),
            'status' => $payment->status?->value,
            'status_label' => $payment->status?->label(),
            'provider' => $payment->provider,
            'provider_reference' => $payment->provider_reference,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'refunded_at' => $payment->refunded_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function stayPayload(CheckIn $stay): array
    {
        return [
            'id' => $stay->getKey(),
            'booking_id' => $stay->booking_id,
            'room_id' => $stay->room_id,
            'room_number' => $stay->room?->room_number,
            'status' => $stay->status->value,
            'status_label' => $stay->status->label(),
            'checked_in_at' => $stay->checked_in_at?->toIso8601String(),
            'checked_out_at' => $stay->checked_out_at?->toIso8601String(),
            'actual_nights' => $stay->actual_nights,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function bookingPayload(Booking $booking, User $viewer): array
    {
        $booking->loadMissing(['guest', 'rooms.roomType', 'payments']);

        return [
            'id' => $booking->getKey(),
            'reference' => $booking->reference,
            'status' => $booking->status->value,
            'status_label' => $booking->status->label(),
            'check_in_date' => $booking->check_in_date?->toDateString(),
            'check_out_date' => $booking->check_out_date?->toDateString(),
            'nights' => $booking->nights(),
            'adults' => (int) $booking->adults,
            'children' => (int) $booking->children,
            'total_amount' => (float) $booking->total_amount,
            'paid_amount' => (float) $booking->paidAmount(),
            'balance' => (float) $booking->balance(),
            'special_requests' => $booking->special_requests,
            'can_cancel' => $booking->isCancellable(),
            'can_check_in' => $booking->isCheckInnable(),
            'guest' => $booking->guest instanceof Guest ? $this->guestPayload($booking->guest) : null,
            'rooms' => $booking->rooms->map(fn (Room $room): array => [
                'id' => $room->getKey(),
                'room_number' => $room->room_number,
                'room_type' => $room->roomType?->name,
                'rate' => (float) $room->effectiveRate(),
            ])->values()->all(),
            'payments' => $booking->payments->map(fn (Payment $p): array => $this->paymentPayload($p))->values()->all(),
            'created_at' => $booking->created_at?->toIso8601String(),
        ];
    }
}
