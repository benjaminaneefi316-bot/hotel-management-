<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditOutcome;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Security / operations audit entry.
 *
 * @property AuditOutcome $outcome
 */
class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'actor_username',
        'action',
        'event_type',
        'description',
        'auditable_type',
        'auditable_id',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'outcome' => AuditOutcome::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Human label for the event type badge. */
    public function eventLabel(): string
    {
        return match ($this->event_type) {
            'auth' => 'Authentication',
            'security' => 'Security',
            'data' => 'Data',
            'system' => 'System',
            'admin' => 'Administration',
            default => ucfirst((string) $this->event_type),
        };
    }
}
