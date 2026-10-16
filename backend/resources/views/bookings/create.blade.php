@extends('layouts.app')

@section('title', 'New booking')
@section('eyebrow', 'Reservations')
@section('heading', 'New booking')

@section('actions')
    <a href="{{ route('bookings.index') }}" class="btn btn-soft"><i class="bi bi-arrow-left me-1"></i> Back to bookings</a>
@endsection

@section('content')
    <form method="POST" action="{{ route('bookings.store') }}" data-booking-form data-date-pair>
        @csrf
        <input type="hidden" name="source" value="admin">

        @include('bookings._form', [
            'booking' => null,
            'guests' => $guests,
            'rooms' => $rooms,
            'statuses' => $statuses,
            'selectedGuestId' => $selectedGuestId,
            'selectedRoomId' => request('room_id') ? (int) request('room_id') : null,
        ])
    </form>
@endsection
