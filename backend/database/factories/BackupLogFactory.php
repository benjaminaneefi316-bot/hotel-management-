<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BackupLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BackupLog>
 */
class BackupLogFactory extends Factory
{
    protected $model = BackupLog::class;

    public function definition(): array
    {
        $started = now()->subMinutes(fake()->numberBetween(1, 600));

        return [
            'reference' => 'BK-'.fake()->unique()->numerify('########').'-'.Str::upper(Str::random(4)),
            'filename' => 'hotel-backup-'.$started->format('Ymd-His').'.sql.gz.enc',
            'driver' => BackupLog::DRIVER_LOCAL,
            'path' => 'backups/placeholder.sql.gz.enc',
            'bucket' => null,
            'status' => BackupLog::STATUS_SUCCESS,
            'verification_status' => 'verified',
            'verification_detail' => 'Factory record.',
            'size_bytes' => fake()->numberBetween(50_000, 5_000_000),
            'checksum' => hash('sha256', fake()->uuid()),
            'is_encrypted' => true,
            'started_at' => $started,
            'finished_at' => $started->copy()->addSeconds(20),
            'duration_ms' => 20000,
            'error' => null,
            'retention_days' => 30,
            'is_pruned' => false,
            'pruned_at' => null,
            'alerted_at' => null,
            'created_by' => User::factory()->admin(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => BackupLog::STATUS_FAILED,
            'verification_status' => 'failed',
            'error' => 'mysqldump failed: factory simulated failure.',
        ]);
    }
}
