@extends('layouts.app')

@section('title', 'New room')
@section('eyebrow', 'Property')
@section('heading', 'Add a room')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('rooms.store') }}">
                        @csrf
                        @include('rooms._form', ['room' => null, 'roomTypes' => $roomTypes, 'statuses' => $statuses])

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('rooms.index') }}" class="btn btn-soft">Cancel</a>
                            <button class="btn btn-gold" type="submit"><i class="bi bi-check2 me-1"></i> Create room</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
