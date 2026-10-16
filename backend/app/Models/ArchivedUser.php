<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An archived (soft-deleted) user account.
 *
 * @property array<int, string>|null $roles
 */
class ArchivedUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_user_id',
        'name',
        'username',
        'email',
        'password_hash',
        'password_salt',
        'status_at_archive',
        'two_factor_enabled',
        'roles',
        'attributes',
        'archived_by',
        'reason',
        'archived_at',
        'restored_at',
        'restored_by',
    ];

    protected $hidden = [
        'password_hash',
        'password_salt',
    ];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'attributes' => 'array',
            'two_factor_enabled' => 'boolean',
            'archived_at' => 'datetime',
            'restored_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /** @return BelongsTo<User, $this> */
    public function restoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }

    public function isRestored(): bool
    {
        return $this->restored_at !== null;
    }

    /** @return string[] */
    public function roleNames(): array
    {
        return $this->roles ?? [];
    }
}
