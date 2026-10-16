@extends('layouts.app')

@section('title', 'Edit room type')
@section('eyebrow', 'Property / Room types')
@section('heading', 'Edit ' . $roomType->name)

@section('actions')
    <a href="{{ route('room-types.show', $roomType) }}" class="btn btn-soft">
        <i class="bi bi-eye me-1"></i> View
    </a>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('room-types.update', $roomType) }}">
                        @csrf
                        @method('PUT')
                        @include('room-types._form', ['roomType' => $roomType])

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('room-types.show', $roomType) }}" class="btn btn-soft">Cancel</a>
                            <button class="btn btn-gold" type="submit"><i class="bi bi-check2 me-1"></i> Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
