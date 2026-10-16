@extends('layouts.app')

@section('title', 'Edit guest')
@section('eyebrow', 'Reservations / Guests')
@section('heading', 'Edit ' . $guest->fullName())

@section('actions')
    <a href="{{ route('guests.show', $guest) }}" class="btn btn-soft">
        <i class="bi bi-eye me-1"></i> View
    </a>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('guests.update', $guest) }}">
                        @csrf
                        @method('PUT')
                        @include('guests._form', ['guest' => $guest])

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('guests.show', $guest) }}" class="btn btn-soft">Cancel</a>
                            <button class="btn btn-gold" type="submit"><i class="bi bi-check2 me-1"></i> Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
