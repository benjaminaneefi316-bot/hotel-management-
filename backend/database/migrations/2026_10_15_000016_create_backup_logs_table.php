<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per backup attempt (scheduled or manual).
 *
 * Fields recorded: filename, driver (local/s3), object key, status, size,
 * checksum, timestamps, verification result and any error message. A failed
 * backup writes status = 'failed' with the error and raises an alert (see
 * app/Services/BackupService.php).
 *
 * `driver = 'local'` is the labelled development simulation that runs without
 * AWS credentials. `driver = 's3'` uploads the encrypted archive to the
 * protected Amazon S3 bucket configured in config/backup.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->nullable()->unique();
            $table->string('filename', 255);
            $table->string('driver', 20)->default('local');    // local | s3
            $table->string('path', 500);                       // local path or s3 object key
            $table->string('bucket', 160)->nullable();

            $table->string('status', 30)->default('running')->index();  // running|success|failed
            $table->string('verification_status', 30)->nullable();      // pending|verified|failed
            $table->text('verification_detail')->nullable();

            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum', 160)->nullable();       // sha256 of the archive
            $table->boolean('is_encrypted')->default(true);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();

            $table->unsignedInteger('retention_days')->default(30);
            $table->boolean('is_pruned')->default(false);
            $table->timestamp('pruned_at')->nullable();

            $table->timestamp('alerted_at')->nullable();       // failure alert sent
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['driver', 'status']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
    }
};
