<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /** Where to send a user after authentication, by role. */
    protected function homeFor(User $user): string
    {
        return $user->isAdmin() || $user->isReceptionist()
            ? route('dashboard')
            : route('guest.dashboard');
    }
}
