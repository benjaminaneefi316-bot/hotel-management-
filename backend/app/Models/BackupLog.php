<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One backup attempt (scheduled or manual).
 */
class BackupLog extends Model
{
    use HasFactory;

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const DRIVER_LOCAL = 'local';

    public const DRIVER_S3 = 's3';

    protected $fillable = [
        'reference',
        'filename',
        'driver',
        'path',
        'bucket',
        'status',
        'verification_status',
        'verification_detail',
        'size_bytes',
        'checksum',
        'is_encrypted',
        'started_at',
        'finished_at',
        'duration_ms',
        'error',
        'retention_days',
        'is_pruned',
        'pruned_at',
        'alerted_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'duration_ms' => 'integer',
            'retention_days' => 'integer',
            'is_encrypted' => 'boolean',
            'is_pruned' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'pruned_at' => 'datetime',
            'alerted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size_bytes;

        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
