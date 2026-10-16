<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Services\PasswordService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Application user.
 *
 * Passwords are stored as a peppered + salted digest (see PasswordService and
 * docs/PASSWORD_SALT_PEPPER.md). The plaintext password and the pepper are
 * never persisted, and `password` / `password_salt` are hidden from arrays and
 * JSON so they cannot leak through the API.
 *
 * @property UserStatus $status
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    /**
     * Note: `password` and `password_salt` are DELIBERATELY not mass-assignable.
     * They are always written through setPassword() so that the pepper and a
     * fresh per-user salt can never be bypassed by a mass-assignment mistake.
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'status',
        'two_factor_enabled',
        'email_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'password_salt',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'status' => UserStatus::class,
        ];
    }

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    /** @return HasMany<Guest, $this> */
    public function guestProfiles(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    /** Bookings this user created or owns. @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    // ---------------------------------------------------------------------
    // Roles
    // ---------------------------------------------------------------------

    /** @return string[] */
    public function roleNames(): array
    {
        return $this->roles->pluck('name')->all();
    }

    public function hasRole(RoleName|string ...$names): bool
    {
        $wanted = array_map(
            static fn (RoleName|string $n): string => $n instanceof RoleName ? $n->value : $n,
            $names,
        );

        return $this->roles->pluck('name')->intersect($wanted)->isNotEmpty();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(RoleName::Admin);
    }

    public function isReceptionist(): bool
    {
        return $this->hasRole(RoleName::Receptionist);
    }

    /** The role used for display and navigation. */
    public function primaryRole(): ?RoleName
    {
        foreach ([RoleName::Admin, RoleName::Receptionist, RoleName::Guest] as $role) {
            if ($this->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }

    // ---------------------------------------------------------------------
    // Password handling
    // ---------------------------------------------------------------------

    /** Hash and store a new password (fresh salt + current pepper). */
    public function setPassword(string $plaintext): void
    {
        $result = app(PasswordService::class)->hash($plaintext);

        $this->password = $result['hash'];
        $this->password_salt = $result['salt'];
    }

    /**
     * Verify a plaintext password.
     *
     * @return array{ok: bool, stale: bool}
     */
    public function verifyPassword(string $plaintext): array
    {
        return app(PasswordService::class)->check(
            $plaintext,
            (string) $this->password,
            (string) $this->password_salt,
        );
    }

    // ---------------------------------------------------------------------
    // Status helpers
    // ---------------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /** @param Builder<User> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', UserStatus::Active->value);
    }

    /** @param Builder<User> $query */
    public function scopeAdmins(Builder $query): void
    {
        $query->whereHas('roles', fn ($q) => $q->where('name', RoleName::Admin->value));
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        return strtoupper(substr($parts[0] ?? '', 0, 1).substr($parts[1] ?? '', 0, 1));
    }
}
