@extends('layouts.app')

@section('title', 'Edit '.$booking->reference)
@section('eyebrow', 'Reservations')
@section('heading', 'Edit '.$booking->reference)

@section('actions')
    <a href="{{ route('bookings.show', $booking) }}" class="btn btn-soft"><i class="bi bi-arrow-left me-1"></i> Back to booking</a>
@endsection

@section('content')
    @if (! $booking->isCancellable() && $booking->status !== \App\Enums\BookingStatus::CheckedIn)
        <div class="alert alert-warning d-flex align-items-center">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <div>This reservation is <strong>{{ $booking->status->label() }}</strong> and can no longer be modified.</div>
        </div>
    @endif

    <form method="POST" action="{{ route('bookings.update', $booking) }}" data-booking-form data-date-pair>
        @csrf
        @method('PUT')

        @include('bookings._form', [
            'booking' => $booking,
            'guests' => $guests,
            'rooms' => $rooms,
            'statuses' => $statuses,
        ])
    </form>
@endsection
