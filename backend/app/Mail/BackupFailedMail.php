<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\BackupLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the configured address when a backup fails or cannot be verified.
 * In AWS this is normally backed by an SNS topic / CloudWatch alarm.
 */
class BackupFailedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly BackupLog $backup) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.config('app.name').'] Backup '.($this->backup->status === 'failed' ? 'FAILED' : 'needs attention'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.backup-failed',
            with: ['backup' => $this->backup],
        );
    }
}
