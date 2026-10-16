@extends('layouts.public')

@section('title', 'Availability · Azure Bay Hotel')
@section('meta_description', 'Available rooms at Azure Bay Hotel for your dates.')

@section('content')
<div class="page-hero" style="background-image:linear-gradient(rgba(24,28,32,.6),rgba(24,28,32,.6)),url('{{ asset('assets/img/room-family.jpg') }}');">
    <div class="container">
        <div class="eyebrow text-gold mb-2">Availability</div>
        <h1 class="display-serif display-5 text-white mb-2">Your results</h1>
        <p class="text-white-50 mb-0">
            {{ \Carbon\CarbonImmutable::parse($criteria['check_in_date'])->format('D j M Y') }}
            <i class="bi bi-arrow-right mx-2"></i>
            {{ \Carbon\CarbonImmutable::parse($criteria['check_out_date'])->format('D j M Y') }}
            <span class="mx-2">·</span>
            {{ $nights }} {{ \Illuminate\Support\Str::plural('night', $nights) }}
            <span class="mx-2">·</span>
            {{ $guests }} {{ \Illuminate\Support\Str::plural('guest', $guests) }}
        </p>
    </div>
</div>

<section class="section">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card p-4 shadow-sm position-sticky" style="top:96px;">
                    <h2 class="h6 text-uppercase text-muted-soft mb-3">Refine search</h2>
                    <form method="POST" action="{{ route('public.booking.search') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Check-in</label>
                            <input type="date" name="check_in_date" class="form-control"
                                   min="{{ now()->format('Y-m-d') }}" value="{{ $criteria['check_in_date'] }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Check-out</label>
                            <input type="date" name="check_out_date" class="form-control"
                                   min="{{ now()->addDay()->format('Y-m-d') }}" value="{{ $criteria['check_out_date'] }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Guests</label>
                            <select name="guests" class="form-select" required>
                                @foreach (range(1, 6) as $n)
                                    <option value="{{ $n }}" @selected($guests === $n)>{{ $n }} {{ \Illuminate\Support\Str::plural('guest', $n) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Room type</label>
                            <select name="room_type_id" class="form-select">
                                <option value="">Any room type</option>
                                @foreach ($roomTypes as $type)
                                    <option value="{{ $type->id }}" @selected(($criteria['room_type_id'] ?? null) == $type->id)>{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn btn-charcoal w-100">Update search</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted-soft small">
                        <strong>{{ $rooms->count() }}</strong>
                        {{ \Illuminate\Support\Str::plural('room', $rooms->count()) }} available
                    </div>
                    <a href="{{ route('public.booking') }}" class="btn btn-sm btn-soft">
                        <i class="bi bi-arrow-left me-1"></i>New search
                    </a>
                </div>

                <div class="d-grid gap-4">
                    @forelse ($rooms as $room)
                        @php($rate = $room->effectiveRate())
                        @php($total = $rate * $nights)
                        <div class="card card-hover overflow-hidden">
                            <div class="row g-0">
                                <div class="col-md-5">
                                    <x-image :src="$room->image_url ?: $room->roomType?->image_url"
                                             :alt="($room->roomType?->name ?? 'Room').' '.$room->room_number"
                                             class="media-cover" style="height:100%;min-height:220px;width:100%;" />
                                </div>
                                <div class="col-md-7">
                                    <div class="p-4 d-flex flex-column h-100">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div>
                                                <div class="eyebrow text-gold">{{ $room->roomType?->name ?? 'Room' }}</div>
                                                <h3 class="h5 mb-1">Room {{ $room->room_number }}</h3>
                                                <div class="text-muted-soft small">
                                                    <i class="bi bi-people me-1"></i>{{ $room->roomType?->capacity ?? '—' }} guests
                                                    <span class="mx-1">·</span>
                                                    {{ $room->roomType?->bed_type }}
                                                    @if ($room->view)
                                                        <span class="mx-1">·</span><i class="bi bi-eye me-1"></i>{{ $room->view }}
                                                    @endif
                                                </div>
                                            </div>
                                            <span class="badge-soft badge-soft-success">Available</span>
                                        </div>

                                        <div class="d-flex flex-wrap gap-1 my-3">
                                            @foreach (array_slice($room->roomType?->amenities ?? [], 0, 4) as $amenity)
                                                <span class="badge-soft badge-soft-neutral">{{ $amenity }}</span>
                                            @endforeach
                                        </div>

                                        <div class="mt-auto d-flex justify-content-between align-items-end">
                                            <div>
                                                <div class="display-serif h4 text-gold mb-0">@money($total)</div>
                                                <div class="text-muted-soft small">
                                                    @money($rate) × {{ $nights }} {{ \Illuminate\Support\Str::plural('night', $nights) }}
                                                </div>
                                            </div>
                                            @auth
                                                <a href="{{ auth()->user()->isGuest() ? route('guest.bookings.index') : route('bookings.create', ['room_id' => $room->id]) }}"
                                                   class="btn btn-gold">
                                                    <i class="bi bi-bookmark-check me-1"></i>Reserve
                                                </a>
                                            @else
                                                <a href="{{ route('login') }}" class="btn btn-gold">
                                                    <i class="bi bi-box-arrow-in-right me-1"></i>Sign in to book
                                                </a>
                                            @endauth
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="card">
                            @include('partials.empty-state', [
                                'icon' => 'bi-calendar-x',
                                'title' => 'No rooms available',
                                'message' => 'We have nothing free for those dates and guest count. Try different dates or a broader room type.',
                                'action' => '<a href="'.route('public.booking').'" class="btn btn-outline-gold">Change dates</a>',
                            ])
                        </div>
                    @endforelse
                </div>

                @if ($rooms->isNotEmpty())
                    <div class="alert alert-light border mt-4 small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Rates shown are per stay and exclude extras. A member of our reservations team
                        will confirm your booking personally before it is guaranteed.
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
