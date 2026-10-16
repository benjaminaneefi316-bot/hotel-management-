<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Physical rooms.
 *
 * Rooms with booking history are never hard-deleted (that would break the
 * calendar and reporting); they are deactivated instead.
 */
final class RoomController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $roomTypeId = (string) $request->query('room_type_id', '');
        $status = (string) $request->query('status', '');

        $rooms = Room::query()
            ->with('roomType')
            ->when($search !== '', fn ($q) => $q->where('room_number', 'like', "%{$search}%"))
            ->when($roomTypeId !== '', fn ($q) => $q->where('room_type_id', $roomTypeId))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderBy('room_number')
            ->paginate(20)
            ->withQueryString();

        return view('rooms.index', [
            'rooms' => $rooms,
            'roomTypes' => RoomType::query()->orderBy('name')->get(),
            'statuses' => RoomStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return view('rooms.create', [
            'roomTypes' => RoomType::query()->orderBy('name')->get(),
            'statuses' => RoomStatus::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $room = Room::query()->create($data);

        $this->audit->success(
            action: 'room.create',
            eventType: 'data',
            description: sprintf('Created room %s.', $room->room_number),
            subject: $room,
            newValues: ['room_number' => $room->room_number, 'status' => $room->status->value],
        );

        return redirect()->route('rooms.show', $room)->with('status', 'Room '.$room->room_number.' created.');
    }

    public function show(Room $room): View
    {
        $room->load(['roomType', 'activeStay.guest']);

        $upcoming = Booking::query()
            ->with('guest')
            ->whereHas('rooms', fn ($q) => $q->where('rooms.id', $room->getKey()))
            ->orderByDesc('check_in_date')
            ->limit(10)
            ->get();

        return view('rooms.show', [
            'room' => $room,
            'upcoming' => $upcoming,
            'statuses' => RoomStatus::cases(),
        ]);
    }

    public function edit(Room $room): View
    {
        return view('rooms.edit', [
            'room' => $room,
            'roomTypes' => RoomType::query()->orderBy('name')->get(),
            'statuses' => RoomStatus::cases(),
        ]);
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $data = $this->validated($request, $room);

        $room->update($data);

        $this->audit->success(
            action: 'room.update',
            eventType: 'data',
            description: sprintf('Updated room %s.', $room->room_number),
            subject: $room,
            newValues: ['room_number' => $room->room_number, 'status' => $room->status->value],
        );

        return redirect()->route('rooms.show', $room)->with('status', 'Room updated.');
    }

    public function destroy(Room $room): RedirectResponse
    {
        if ($room->bookings()->exists() || $room->stays()->exists()) {
            return back()->with('error', 'This room has booking history and cannot be deleted. Deactivate it instead.');
        }

        $number = $room->room_number;
        $room->delete();

        $this->audit->success(
            action: 'room.delete',
            eventType: 'data',
            description: sprintf('Deleted room %s.', $number),
        );

        return redirect()->route('rooms.index')->with('status', 'Room '.$number.' deleted.');
    }

    public function updateStatus(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(RoomStatus::values())],
        ]);

        $newStatus = RoomStatus::from($data['status']);

        // A room with a guest in it must stay occupied until checked out.
        if ($room->activeStay()->exists() && $newStatus !== RoomStatus::Occupied) {
            return back()->with('error', 'This room has a guest checked in. Check them out before changing its status.');
        }

        $old = $room->status;
        $room->forceFill(['status' => $newStatus])->save();

        $this->audit->success(
            action: 'room.status',
            eventType: 'data',
            description: sprintf('Room %s status changed from %s to %s.', $room->room_number, $old->label(), $newStatus->label()),
            subject: $room,
            oldValues: ['status' => $old->value],
            newValues: ['status' => $newStatus->value],
        );

        return back()->with('status', 'Room '.$room->room_number.' set to '.$newStatus->label().'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Room $room = null): array
    {
        $data = $request->validate([
            'room_number' => [
                'required', 'string', 'max:20',
                Rule::unique('rooms', 'room_number')->ignore($room?->getKey()),
            ],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'floor' => ['nullable', 'integer', 'min:0', 'max:200'],
            'nightly_rate' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'status' => ['required', Rule::in(RoomStatus::values())],
            'view' => ['nullable', 'string', 'max:60'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['image_url'] = $data['image_url'] ?? null;

        return $data;
    }
}
