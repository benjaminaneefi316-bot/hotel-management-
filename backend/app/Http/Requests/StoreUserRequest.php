<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

// NOTE: App\Enums\UserStatus does not expose a values() helper, so the allowed
// statuses are derived from its cases directly.

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'unique:users,username'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(RoleName::names())],
            'status' => ['nullable', Rule::in(array_map(static fn (UserStatus $s): string => $s->value, UserStatus::cases()))],
            'two_factor_enabled' => ['sometimes', 'boolean'],
            // The password policy comes from Password::defaults() (AppServiceProvider).
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
