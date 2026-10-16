@php
    /**
     * Shared booking form fields.
     *
     * Expected variables:
     *   $guests        Collection<int, \App\Models\Guest>
     *   $rooms         Collection<int, \App\Models\Room>
     *   $statuses      array<int, \App\Enums\BookingStatus>
     *   $booking       \App\Models\Booking|null  (present when editing)
     *   $selectedGuestId  int|null
     *   $selectedRoomId   int|null  (from ?room_id= on create)
     */
    $booking = $booking ?? null;
    $isEdit = $booking !== null;
    $selectedGuestId = old('guest_id', $selectedGuestId ?? $booking?->guest_id);
    $selectedRoomIds = array_map('intval', (array) old('room_ids', $isEdit ? $booking->rooms->pluck('id')->all() : array_filter([$selectedRoomId ?? null])));
    $currency = app(\App\Services\SettingsService::class)->get('hotel_currency', 'USD');
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-muted-soft mb-3" style="letter-spacing:.12em;">Reservation</h2>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="guest_id" class="form-label">Guest <span class="text-danger">*</span></label>
                        @if ($isEdit)
                            <input type="text" class="form-control" value="{{ $booking->guest?->fullName() ?? '—' }}" readonly>
                            <div class="form-text">The guest on a reservation cannot be changed.</div>
                        @else
                            <select id="guest_id" name="guest_id" class="form-select @error('guest_id') is-invalid @enderror" required>
                                <option value="">Select a guest…</option>
                                @foreach ($guests as $guest)
                                    <option value="{{ $guest->id }}" @selected((int) $selectedGuestId === $guest->id)>
                                        {{ $guest->fullName() }}{{ $guest->email ? ' · '.$guest->email : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('guest_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">
                                No guest yet? <a href="{{ route('guests.create') }}">Add a guest</a>.
                            </div>
                        @endif
                    </div>

                    @unless ($isEdit)
                        <div class="col-md-6">
                            <label for="status" class="form-label">Initial status</label>
                            <select id="status" name="status" class="form-select">
                                @foreach ($statuses as $statusOption)
                                    @if (in_array($statusOption->value, ['pending', 'confirmed'], true))
                                        <option value="{{ $statusOption->value }}" @selected(old('status', 'confirmed') === $statusOption->value)>
                                            {{ $statusOption->label() }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    @endunless

                    <div class="col-md-3">
                        <label for="check_in_date" class="form-label">Check-in <span class="text-danger">*</span></label>
                        <input type="date" id="check_in_date" name="check_in_date"
                               class="form-control @error('check_in_date') is-invalid @enderror"
                               value="{{ old('check_in_date', $booking?->check_in_date?->toDateString()) }}" required>
                        @error('check_in_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="check_out_date" class="form-label">Check-out <span class="text-danger">*</span></label>
                        <input type="date" id="check_out_date" name="check_out_date"
                               class="form-control @error('check_out_date') is-invalid @enderror"
                               value="{{ old('check_out_date', $booking?->check_out_date?->toDateString()) }}" required>
                        @error('check_out_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label for="adults" class="form-label">Adults</label>
                        <input type="number" id="adults" name="adults" min="1" max="20" class="form-control"
                               value="{{ old('adults', $booking?->adults ?? 1) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="children" class="form-label">Children</label>
                        <input type="number" id="children" name="children" min="0" max="20" class="form-control"
                               value="{{ old('children', $booking?->children ?? 0) }}">
                    </div>

                    <div class="col-12">
                        <label for="special_requests" class="form-label">Special requests</label>
                        <textarea id="special_requests" name="special_requests" rows="2" class="form-control @error('special_requests') is-invalid @enderror"
                                  placeholder="Late arrival, high floor, cot needed…">{{ old('special_requests', $booking?->special_requests) }}</textarea>
                        @error('special_requests')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="internal_notes" class="form-label">Internal notes <span class="text-muted-soft">(not shown to the guest)</span></label>
                        <textarea id="internal_notes" name="internal_notes" rows="2" class="form-control @error('internal_notes') is-invalid @enderror">{{ old('internal_notes', $booking?->internal_notes) }}</textarea>
                        @error('internal_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 text-uppercase text-muted-soft mb-0" style="letter-spacing:.12em;">Rooms</h2>
                    <span class="badge badge-soft badge-soft-gold" id="room-count">0</span>
                </div>

                <p class="small text-muted-soft">
                    Choose dates on the left and the list refreshes with rooms that are free for the whole stay.
                    Rates are per night in {{ $currency }}.
                </p>

                @error('room_ids')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror

                <div id="room-options" class="d-grid gap-2">
                    @forelse ($rooms as $room)
                        <label class="border rounded-3 p-2 d-flex gap-2 align-items-start room-option">
                            <input type="checkbox" class="form-check-input mt-1" name="room_ids[]" value="{{ $room->id }}"
                                   @checked(in_array($room->id, $selectedRoomIds, true))>
                            <span class="small">
                                <span class="fw-semibold d-block">Room {{ $room->room_number }}</span>
                                <span class="text-muted-soft">{{ $room->roomType?->name ?? 'Unassigned type' }}
                                    · sleeps {{ $room->roomType?->capacity ?? '—' }}</span>
                                <span class="text-gold d-block">@money($room->effectiveRate()) / night</span>
                            </span>
                        </label>
                    @empty
                        <p class="text-muted-soft small mb-0">No rooms are currently bookable. Check room statuses.</p>
                    @endforelse
                </div>

                <div id="room-empty" class="alert alert-warning py-2 small mt-3 d-none">
                    No rooms are available for the selected dates.
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-gold flex-fill">
                <i class="bi bi-check-lg me-1"></i> {{ $isEdit ? 'Save changes' : 'Create booking' }}
            </button>
            <a href="{{ $isEdit ? route('bookings.show', $booking) : route('bookings.index') }}" class="btn btn-soft">Cancel</a>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        'use strict';

        var form = document.querySelector('[data-booking-form]');
        if (!form) { return; }

        var checkIn = form.querySelector('[name="check_in_date"]');
        var checkOut = form.querySelector('[name="check_out_date"]');
        var typeField = form.querySelector('[name="room_type_id"]');
        var container = document.getElementById('room-options');
        var emptyBox = document.getElementById('room-empty');
        var countBadge = document.getElementById('room-count');
        var availabilityUrl = @json(route('bookings.availability'));

        function selectedIds() {
            return Array.prototype.map.call(
                container.querySelectorAll('input[name="room_ids[]"]:checked'),
                function (el) { return String(el.value); }
            );
        }

        function updateCount() {
            if (countBadge) { countBadge.textContent = String(selectedIds().length); }
        }

        function render(rooms, keep) {
            container.innerHTML = '';

            if (!rooms.length) {
                if (emptyBox) { emptyBox.classList.remove('d-none'); }
                updateCount();
                return;
            }

            if (emptyBox) { emptyBox.classList.add('d-none'); }

            rooms.forEach(function (room) {
                var label = document.createElement('label');
                label.className = 'border rounded-3 p-2 d-flex gap-2 align-items-start room-option';

                var input = document.createElement('input');
                input.type = 'checkbox';
                input.className = 'form-check-input mt-1';
                input.name = 'room_ids[]';
                input.value = room.id;
                if (keep.indexOf(String(room.id)) !== -1) { input.checked = true; }

                var span = document.createElement('span');
                span.className = 'small';
                span.innerHTML =
                    '<span class="fw-semibold d-block">Room ' + room.room_number + '</span>' +
                    '<span class="text-muted-soft">' + (room.room_type || 'Unassigned type') +
                    ' · sleeps ' + (room.capacity || '—') + '</span>' +
                    '<span class="text-gold d-block">' + Number(room.rate).toFixed(2) + ' / night</span>';

                label.appendChild(input);
                label.appendChild(span);
                container.appendChild(label);
            });

            updateCount();
        }

        form.addEventListener('change', function (event) {
            if (event.target && event.target.name === 'room_ids[]') { updateCount(); }
        });

        function refresh() {
            if (!checkIn.value || !checkOut.value || checkOut.value <= checkIn.value) {
                return;
            }

            var params = new URLSearchParams();
            params.set('check_in_date', checkIn.value);
            params.set('check_out_date', checkOut.value);
            if (typeField && typeField.value) { params.set('room_type_id', typeField.value); }

            var keep = selectedIds();
            container.style.opacity = '0.5';

            fetch(availabilityUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) { return response.ok ? response.json() : { rooms: [] }; })
                .then(function (data) { render(data.rooms || [], keep); })
                .catch(function () { /* keep the current list on failure */ })
                .finally(function () { container.style.opacity = '1'; });
        }

        checkIn.addEventListener('change', refresh);
        checkOut.addEventListener('change', refresh);
        if (typeField) { typeField.addEventListener('change', refresh); }

        updateCount();
    })();
</script>
@endpush
