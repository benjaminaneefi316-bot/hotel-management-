@extends('layouts.app')

@section('title', 'New guest')
@section('eyebrow', 'Reservations')
@section('heading', 'Create guest profile')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('guests.store') }}">
                        @csrf
                        @include('guests._form', ['guest' => null])

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('guests.index') }}" class="btn btn-soft">Cancel</a>
                            <button class="btn btn-gold" type="submit"><i class="bi bi-check2 me-1"></i> Create guest</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
