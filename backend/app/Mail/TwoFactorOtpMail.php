<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The 2FA one-time-password email.
 *
 * The plaintext code is a public property so automated tests can read it from
 * the recorded mailable (Mail::fake()). It is NEVER logged by the application
 * and never returned in an HTTP response in production.
 */
class TwoFactorOtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $code,
        public readonly int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your verification code - '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.two-factor-otp',
            with: [
                'name' => $this->user->name,
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }
}
