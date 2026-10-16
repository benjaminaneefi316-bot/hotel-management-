<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\View\View;

/**
 * Roles and the permission matrix from config/abilities.php.
 *
 * Roles are system-defined (three fixed roles); this screen is read-only and
 * shows exactly which abilities each role holds, so the UI can never drift from
 * the server-side gate definitions.
 */
final class RoleController extends Controller
{
    public function index(): View
    {
        return view('roles.index', [
            'roles' => Role::query()->withCount('users')->orderBy('name')->get(),
            'abilities' => (array) config('abilities', []),
        ]);
    }

    public function show(Role $role): View
    {
        $role->load('users');

        /** @var array<string, array<int, string>> $matrix */
        $matrix = (array) config('abilities', []);

        $abilities = array_values(array_keys(array_filter(
            $matrix,
            static fn (array $roles): bool => in_array($role->name, $roles, true),
        )));

        return view('roles.show', [
            'role' => $role,
            'abilities' => $abilities,
        ]);
    }
}
