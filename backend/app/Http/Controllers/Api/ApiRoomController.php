<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Room types (public brochure) and live availability.
 */
final class ApiRoomController extends ApiController
{
    public function __construct(private readonly BookingService $bookings) {}

    /** Public: the room-type catalogue shown in the mobile app. */
    public function roomTypes(): JsonResponse
    {
        $types = RoomType::query()
            ->active()
            ->orderBy('base_price')
            ->get();

        return $this->ok([
            'data' => $types->map(fn (RoomType $type): array => $this->roomTypePayload($type))->all(),
        ]);
    }

    /** Public: which rooms are free for a window (optionally per room type). */
    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'room_type_id' => ['nullable', 'integer', 'exists:room_types,id'],
        ]);

        try {
            $rooms = $this->bookings->availableRooms(
                $data['check_in_date'],
                $data['check_out_date'],
                isset($data['room_type_id']) ? (int) $data['room_type_id'] : null,
            );
        } catch (BusinessRuleException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok([
            'data' => $rooms->map(fn (Room $room): array => $this->roomPayload($room))->values()->all(),
        ]);
    }

    /** Staff: the full room list with live status. */
    public function index(Request $request): JsonResponse
    {
        $rooms = Room::query()
            ->with('roomType')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('room_type_id'), fn ($query) => $query->where('room_type_id', $request->input('room_type_id')))
            ->orderBy('room_number')
            ->get();

        return $this->ok([
            'data' => $rooms->map(fn (Room $room): array => $this->roomPayload($room))->all(),
        ]);
    }
}
