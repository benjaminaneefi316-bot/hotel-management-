<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BackupLog;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsHotel;
use Tests\TestCase;

/**
 * The backup pipeline, exercised end to end against local storage (the
 * documented development simulation that needs no AWS credentials):
 *
 *   dump -> compress -> encrypt (AES-256-GCM) -> store -> verify -> audit
 */
class BackupPipelineTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHotel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        config([
            'backup.driver' => 'local',
            'backup.disk' => 'local',
            'backup.encrypt' => true,
            'backup.compression' => true,
        ]);
    }

    public function test_a_backup_is_dumped_encrypted_stored_verified_and_audited(): void
    {
        $admin = User::factory()->admin()->create();

        $log = app(BackupService::class)->run($admin);

        $this->assertTrue($log->isSuccess(), (string) ($log->error ?? ''));
        $this->assertSame(BackupLog::DRIVER_LOCAL, $log->driver);
        $this->assertTrue((bool) $log->is_encrypted);
        $this->assertSame('verified', $log->verification_status);
        $this->assertNotEmpty($log->checksum);
        $this->assertGreaterThan(0, (int) $log->size_bytes);

        $stored = storage_path('app/'.ltrim((string) $log->path, '/'));

        $this->assertFileExists($stored);
        // The archive must be encrypted, not a readable SQL dump.
        $this->assertStringNotContainsString('CREATE TABLE', (string) file_get_contents($stored));

        // It can be re-verified later (checksum + decrypt + decompress).
        $this->assertTrue(app(BackupService::class)->verifyExisting($log));

        $this->assertDatabaseHas('audit_logs', ['action' => 'backup.run', 'outcome' => 'success']);
    }

    public function test_prune_removes_archives_past_the_retention_window(): void
    {
        $admin = User::factory()->admin()->create();

        $log = app(BackupService::class)->run($admin);
        $log->forceFill(['created_at' => now()->subDays(90)])->save();

        $removed = app(BackupService::class)->prune(30);

        $this->assertSame(1, $removed);
        $this->assertTrue((bool) $log->fresh()->is_pruned);
    }
}
