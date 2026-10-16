@extends('layouts.app')

@section('title', 'New room type')
@section('eyebrow', 'Property')
@section('heading', 'Create room type')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('room-types.store') }}">
                        @csrf
                        @include('room-types._form', ['roomType' => null])

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('room-types.index') }}" class="btn btn-soft">Cancel</a>
                            <button class="btn btn-gold" type="submit"><i class="bi bi-check2 me-1"></i> Create room type</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
