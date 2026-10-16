<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\BackupFailedMail;
use App\Models\BackupLog;
use App\Models\User;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Encrypted database backup to local storage or Amazon S3.
 *
 * PIPELINE
 *   1. dump the database (mysqldump, or a file copy / simulated dump for SQLite)
 *   2. gzip the dump
 *   3. encrypt it with AES-256-GCM (APP_ENCRYPTION_KEY)
 *   4. store it: `local` driver writes under storage/app/backups (the labelled
 *      development simulation that needs no AWS credentials); `s3` driver
 *      uploads to the private backup bucket
 *   5. verify: SHA-256 checksum, decrypt + gunzip round trip, and (for S3) a
 *      HEAD request confirming the object exists with the expected size
 *   6. record everything in `backup_logs` and raise an alert on failure
 *
 * IAM LEAST PRIVILEGE (driver = s3): the principal needs only
 * s3:PutObject, s3:GetObject, s3:DeleteObject and s3:ListBucket on the backup
 * bucket/prefix. See docs/AWS_DEPLOYMENT.md and docs/BACKUP_RESTORE.md.
 */
final class BackupService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly \App\Support\Encryption\FileEncryptor $fileEncryptor,
    ) {}

    /**
     * Run a backup and return its log entry.
     *
     * This never throws for an expected failure (missing mysqldump, network
     * error, ...): the failure is recorded and alerted instead, so a scheduled
     * run cannot take the queue/worker down.
     */
    public function run(?User $actor = null): BackupLog
    {
        $driver = (string) config('backup.driver', 'local');
        $filename = sprintf('hotel-backup-%s.sql.gz.enc', now()->format('Ymd-His'));

        /** @var BackupLog $log */
        $log = BackupLog::query()->create([
            'reference' => 'BK-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
            'filename' => $filename,
            'driver' => $driver,
            'path' => '',
            'bucket' => $driver === 's3' ? (string) config('backup.s3.bucket') : null,
            'status' => BackupLog::STATUS_RUNNING,
            'verification_status' => 'pending',
            'retention_days' => (int) config('backup.retention_days', 30),
            'started_at' => now(),
            'created_by' => $actor?->getKey(),
            'is_encrypted' => (bool) config('backup.encrypt', true),
        ]);

        $started = microtime(true);
        $workspace = storage_path('app/backups/tmp');
        $this->ensureDirectory($workspace);

        $sqlPath = $workspace.DIRECTORY_SEPARATOR.$filename.'.sql';
        $gzPath = $sqlPath.'.gz';
        $encPath = $gzPath.'.enc'; // == $workspace/$filename

        try {
            $this->dumpDatabase($sqlPath);
            $this->compress($sqlPath, $gzPath);
            $this->fileEncryptor->encryptFile($gzPath, $encPath);

            $checksum = hash_file('sha256', $encPath) ?: null;
            $size = filesize($encPath) ?: 0;

            $storedPath = $this->store($encPath, $log);

            $log->forceFill([
                'path' => $storedPath,
                'checksum' => $checksum,
                'size_bytes' => $size,
            ])->save();

            $this->verify($log, $encPath);

            $log->forceFill([
                'status' => $this->verificationFailed($log) ? BackupLog::STATUS_FAILED : BackupLog::STATUS_SUCCESS,
                'finished_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            ])->save();

            if ($log->status === BackupLog::STATUS_SUCCESS) {
                $this->audit->success(
                    action: 'backup.run',
                    eventType: 'system',
                    description: sprintf('Backup %s completed (%s, %s) to %s.', $log->reference, $log->humanSize(), $log->filename, strtoupper($driver)),
                    subject: $log,
                    newValues: ['path' => $storedPath, 'checksum' => $checksum],
                );
            } else {
                $this->alertFailure($log);
            }
        } catch (Throwable $e) {
            $log->forceFill([
                'status' => BackupLog::STATUS_FAILED,
                'verification_status' => 'failed',
                'error' => $this->safeError($e),
                'finished_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            ])->save();

            $this->audit->failure(
                action: 'backup.run',
                eventType: 'system',
                description: sprintf('Backup %s failed: %s', $log->reference, $this->safeError($e)),
                subject: $log,
            );

            $this->alertFailure($log);
        } finally {
            foreach ([$sqlPath, $gzPath, $encPath] as $tmp) {
                if (is_file($tmp)) {
                    @unlink($tmp);
                }
            }
        }

        return $log->fresh();
    }

    /**
     * Delete archives older than the retention window.
     *
     * @return int number of archives removed
     */
    public function prune(?int $retentionDays = null): int
    {
        $days = $retentionDays ?? (int) config('backup.retention_days', 30);
        $cutoff = now()->subDays($days);

        $expired = BackupLog::query()
            ->where('created_at', '<', $cutoff)
            ->where('is_pruned', false)
            ->where('status', BackupLog::STATUS_SUCCESS)
            ->get();

        $removed = 0;

        foreach ($expired as $log) {
            try {
                $this->deleteArtifact($log);
                $log->forceFill(['is_pruned' => true, 'pruned_at' => now()])->save();
                $removed++;
            } catch (Throwable $e) {
                Log::warning('Failed to prune backup archive.', ['reference' => $log->reference, 'error' => $e->getMessage()]);
            }
        }

        if ($removed > 0) {
            $this->audit->success(
                action: 'backup.prune',
                eventType: 'system',
                description: sprintf('Pruned %d backup archive(s) older than %d days.', $removed, $days),
                newValues: ['pruned' => $removed, 'retention_days' => $days],
            );
        }

        return $removed;
    }

    /**
     * Re-verify an existing archive by re-reading it and checking the checksum.
     */
    public function verifyExisting(BackupLog $log): bool
    {
        try {
            $local = $this->fetchArtifact($log);
            $ok = $this->verifyArchiveFile($local, $log);
            @unlink($local);

            $log->forceFill([
                'verification_status' => $ok ? 'verified' : 'failed',
                'verification_detail' => $ok ? 'Re-verified at '.now()->toDateTimeString() : 'Checksum or decrypt round-trip failed.',
            ])->save();

            return $ok;
        } catch (Throwable $e) {
            $log->forceFill([
                'verification_status' => 'failed',
                'verification_detail' => $this->safeError($e),
            ])->save();

            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Pipeline steps
    // -------------------------------------------------------------------------

    /**
     * Dump the database to $destination.
     *
     * The password is passed through the MYSQL_PWD environment variable rather
     * than the command line, so it never appears in the process list.
     */
    private function dumpDatabase(string $destination): void
    {
        $connection = (string) config('database.default');
        /** @var array<string, mixed> $db */
        $db = (array) config("database.connections.{$connection}", []);
        $driver = (string) ($db['driver'] ?? '');

        if ($driver === 'sqlite') {
            $file = $db['database'] ?? null;

            if (is_string($file) && $file !== ':memory:' && is_file($file)) {
                if (! copy($file, $destination)) {
                    throw new RuntimeException('Unable to copy the SQLite database file for backup.');
                }

                return;
            }

            // Development/testing simulation: an in-memory SQLite database has
            // no file to copy, so a small placeholder dump is written. This
            // keeps the rest of the pipeline (compress -> encrypt -> store ->
            // verify) fully exercisable in automated tests.
            file_put_contents(
                $destination,
                "-- Simulated dump generated at ".now()->toIso8601String()."\n".
                "-- Source: SQLite in-memory connection (development/testing only).\n".
                "CREATE TABLE backups_probe (id INTEGER PRIMARY KEY);\n"
            );

            return;
        }

        $binary = (string) config('backup.mysqldump.binary', 'mysqldump');

        if ($binary === '' || ! $this->binaryAvailable($binary)) {
            throw new RuntimeException("Database dump tool \"{$binary}\" was not found. Set MYSQLDUMP_BINARY to the full path of mysqldump.");
        }

        $args = [
            '--host='.(string) ($db['host'] ?? '127.0.0.1'),
            '--port='.(string) ($db['port'] ?? '3306'),
            '--user='.(string) ($db['username'] ?? 'root'),
            ...(array) config('backup.mysqldump.extra_args', []),
            (string) ($db['database'] ?? ''),
        ];

        $process = new Process(
            [$binary, ...$args],
            null,
            ['MYSQL_PWD' => (string) ($db['password'] ?? '')],
            null,
            (float) config('backup.mysqldump.timeout', 900),
        );

        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('mysqldump failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        file_put_contents($destination, $process->getOutput());
    }

    private function compress(string $source, string $destination): void
    {
        if (! (bool) config('backup.compression', true)) {
            if (! copy($source, $destination)) {
                throw new RuntimeException('Unable to copy the database dump.');
            }

            return;
        }

        $input = fopen($source, 'rb');
        $output = gzopen($destination, 'wb6');

        if ($input === false || $output === false) {
            throw new RuntimeException('Unable to open files for compression.');
        }

        while (! feof($input)) {
            $chunk = fread($input, 262_144);

            if ($chunk === false) {
                break;
            }

            gzwrite($output, $chunk);
        }

        fclose($input);
        gzclose($output);
    }

    /** Store the encrypted archive and return the recorded path/object key. */
    private function store(string $encPath, BackupLog $log): string
    {
        if ($log->driver === BackupLog::DRIVER_S3) {
            return $this->uploadToS3($encPath, $log);
        }

        $directory = storage_path('app/'.trim((string) config('backup.path', 'backups'), '/'));
        $this->ensureDirectory($directory);
        $target = $directory.DIRECTORY_SEPARATOR.$log->filename;

        if (! copy($encPath, $target)) {
            throw new RuntimeException('Unable to write the backup archive to local storage.');
        }

        // Path stored relative to storage/app for portability.
        return trim((string) config('backup.path', 'backups'), '/').'/'.$log->filename;
    }

    private function uploadToS3(string $encPath, BackupLog $log): string
    {
        $bucket = (string) config('backup.s3.bucket');

        if ($bucket === '') {
            throw new RuntimeException('AWS_BACKUP_BUCKET is not configured, so the S3 backup driver cannot run.');
        }

        $key = trim((string) config('backup.s3.prefix', 'hotel-backups'), '/').'/'.$log->filename;

        $params = [
            'Bucket' => $bucket,
            'Key' => $key,
            'SourceFile' => $encPath,
            'ContentType' => 'application/octet-stream',
            'ServerSideEncryption' => (string) config('backup.s3.sse', 'AES256'),
            'StorageClass' => (string) config('backup.s3.storage_class', 'STANDARD_IA'),
        ];

        $kmsKey = (string) config('backup.s3.kms_key_id', '');
        if ($params['ServerSideEncryption'] === 'aws:kms' && $kmsKey !== '') {
            $params['SSEKMSKeyId'] = $kmsKey;
        }

        $this->s3Client()->putObject($params);

        return $key;
    }

    /**
     * Verify the archive: checksum, decrypt + gunzip round trip, and (S3)
     * object presence.
     */
    private function verify(BackupLog $log, string $encPath): void
    {
        try {
            if ($log->driver === BackupLog::DRIVER_S3) {
                $head = $this->s3Client()->headObject([
                    'Bucket' => (string) config('backup.s3.bucket'),
                    'Key' => $log->path,
                ]);

                $remoteSize = (int) ($head['ContentLength'] ?? 0);

                if ($remoteSize !== (int) $log->size_bytes) {
                    $log->forceFill([
                        'verification_status' => 'failed',
                        'verification_detail' => sprintf('Remote object size %d does not match local size %d.', $remoteSize, (int) $log->size_bytes),
                    ])->save();

                    return;
                }
            }

            $ok = $this->verifyArchiveFile($encPath, $log);

            $log->forceFill([
                'verification_status' => $ok ? 'verified' : 'failed',
                'verification_detail' => $ok
                    ? 'Checksum matched and the archive decrypted and decompressed successfully.'
                    : 'Checksum or decrypt round-trip failed.',
            ])->save();
        } catch (Throwable $e) {
            $log->forceFill([
                'verification_status' => 'failed',
                'verification_detail' => $this->safeError($e),
            ])->save();
        }
    }

    /** Decrypt + gunzip an archive and confirm it looks like a SQL dump. */
    private function verifyArchiveFile(string $encPath, BackupLog $log): bool
    {
        if (! is_file($encPath)) {
            return false;
        }

        if ($log->checksum && hash_file('sha256', $encPath) !== $log->checksum) {
            return false;
        }

        if (! (bool) config('backup.encrypt', true)) {
            return true;
        }

        $tmpGz = $encPath.'.verify.gz';
        $tmpSql = $encPath.'.verify.sql';

        try {
            $this->fileEncryptor->decryptFile($encPath, $tmpGz);

            if (! (bool) config('backup.compression', true)) {
                return is_file($tmpGz) && filesize($tmpGz) > 0;
            }

            $in = gzopen($tmpGz, 'rb');
            if ($in === false) {
                return false;
            }

            $out = fopen($tmpSql, 'wb');
            while (! gzeof($in)) {
                $chunk = gzread($in, 262_144);
                if ($chunk === false) {
                    break;
                }
                fwrite($out, $chunk);
            }
            gzclose($in);
            fclose($out);

            $head = (string) file_get_contents($tmpSql, false, null, 0, 4096);

            return str_contains($head, 'CREATE TABLE')
                || str_contains($head, '-- Simulated dump')
                || str_contains($head, 'SQLite format');
        } finally {
            foreach ([$tmpGz, $tmpSql] as $tmp) {
                if (is_file($tmp)) {
                    @unlink($tmp);
                }
            }
        }
    }

    private function verificationFailed(BackupLog $log): bool
    {
        return $log->verification_status === 'failed';
    }

    /** Copy/download an archive so it can be re-verified locally. */
    private function fetchArtifact(BackupLog $log): string
    {
        $target = storage_path('app/backups/tmp/reverify-'.Str::uuid()->toString().'.enc');
        $this->ensureDirectory(dirname($target));

        if ($log->driver === BackupLog::DRIVER_S3) {
            $this->s3Client()->getObject([
                'Bucket' => (string) config('backup.s3.bucket'),
                'Key' => $log->path,
                'SaveAs' => $target,
            ]);

            return $target;
        }

        $source = storage_path('app/'.ltrim($log->path, '/'));

        if (! is_file($source)) {
            throw new RuntimeException('Backup archive is missing from local storage.');
        }

        if (! copy($source, $target)) {
            throw new RuntimeException('Unable to read the backup archive for verification.');
        }

        return $target;
    }

    private function deleteArtifact(BackupLog $log): void
    {
        if ($log->driver === BackupLog::DRIVER_S3) {
            $this->s3Client()->deleteObject([
                'Bucket' => (string) config('backup.s3.bucket'),
                'Key' => $log->path,
            ]);

            return;
        }

        $path = storage_path('app/'.ltrim($log->path, '/'));

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function alertFailure(BackupLog $log): void
    {
        $to = (string) config('backup.alert.mail_to', '');

        if ($to === '' || ! (bool) config('backup.alert.on_failure', true)) {
            return;
        }

        try {
            Mail::to($to)->send(new BackupFailedMail($log));
            $log->forceFill(['alerted_at' => now()])->saveQuietly();
        } catch (Throwable $e) {
            Log::error('Unable to send the backup failure alert.', ['error' => $e->getMessage()]);
        }
    }

    private function s3Client(): S3Client
    {
        $config = [
            'version' => 'latest',
            'region' => (string) config('backup.s3.region', 'us-east-1'),
        ];

        // Explicit keys are used when provided (local/CI testing); otherwise the
        // SDK falls back to its default credential chain (environment
        // variables, shared credentials file, or an EC2/ECS IAM role). No
        // credential is ever hard-coded in this project.
        $key = (string) config('backup.s3.key', '');
        $secret = (string) config('backup.s3.secret', '');

        if ($key !== '' && $secret !== '') {
            $config['credentials'] = [
                'key' => $key,
                'secret' => $secret,
                'token' => config('backup.s3.session_token'),
            ];
        }

        return new S3Client($config);
    }

    private function binaryAvailable(string $binary): bool
    {
        $process = new Process([$binary, '--version'], null, null, null, 10.0);

        try {
            $process->run();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new RuntimeException("Unable to create directory: {$path}");
        }
    }

    /** Keep secrets out of the stored error text. */
    private function safeError(Throwable $e): string
    {
        $message = $e->getMessage();
        $password = (string) config('database.connections.'.config('database.default').'.password', '');

        if ($password !== '') {
            $message = str_replace($password, '[redacted]', $message);
        }

        return Str::limit($message, 2000, '…');
    }
}
