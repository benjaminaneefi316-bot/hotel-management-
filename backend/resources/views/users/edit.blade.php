@extends('layouts.app')

@section('title', 'Edit user')
@section('eyebrow', 'Administration')
@section('heading', 'Edit user account')

@section('actions')
    <a href="{{ route('users.show', $user) }}" class="btn btn-soft">
        <i class="bi bi-eye me-1"></i> View
    </a>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('users.update', $user) }}">
                        @csrf
                        @method('PUT')
                        @include('users._form', ['user' => $user, 'roles' => $roles])

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('users.show', $user) }}" class="btn btn-soft">Cancel</a>
                            <button class="btn btn-gold" type="submit"><i class="bi bi-check2 me-1"></i> Save changes</button>
                        </div>
                    </form>

                    <p class="small text-muted-soft mb-0 mt-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Roles are managed from the user's profile page.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
