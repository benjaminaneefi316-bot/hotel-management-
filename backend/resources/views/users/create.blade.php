@extends('layouts.app')

@section('title', 'New user')
@section('eyebrow', 'Administration')
@section('heading', 'Create user account')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('users.store') }}">
                        @csrf
                        @include('users._form', ['user' => null, 'roles' => $roles])

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('users.index') }}" class="btn btn-soft">Cancel</a>
                            <button class="btn btn-gold" type="submit"><i class="bi bi-check2 me-1"></i> Create user</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
