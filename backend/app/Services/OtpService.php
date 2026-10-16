<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\TwoFactorOtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Email OTP service - the second authentication factor.
 *
 * Guarantees:
 *   - codes are generated with a cryptographically secure RNG (random_int)
 *   - only a HASH of the code is stored (plaintext never touches the DB)
 *   - codes expire after a short window (default 5 minutes)
 *   - a code can be used exactly once (consumed_at)
 *   - issuing a new code invalidates older unused codes for the same purpose
 *   - verification attempts per code are capped (max_attempts)
 *   - resend frequency is throttled
 *   - plaintext codes are never written to logs
 */
final class OtpService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Issue and email a new OTP, invalidating previous unused codes.
     *
     * @return array{ok: bool, reason: string, code: string|null, retry_after: int|null}
     */
    public function issue(User $user, ?string $ip = null): array
    {
        $purpose = $this->purpose();
        $cooldown = (int) config('security.otp.resend_cooldown_seconds');

        $latest = EmailOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        // Resend cooldown: a fresh, still-valid code was just sent.
        if ($latest && $latest->last_sent_at && ! $latest->isConsumed()) {
            $elapsed = $latest->last_sent_at->diffInSeconds(now());

            if ($elapsed < $cooldown) {
                return [
                    'ok' => false,
                    'reason' => 'cooldown',
                    'code' => null,
                    'retry_after' => $cooldown - (int) $elapsed,
                ];
            }
        }

        // Hourly resend ceiling to blunt abuse of the "resend" button.
        $sentLastHour = EmailOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->where('last_sent_at', '>=', now()->subHour())
            ->count();

        if ($sentLastHour >= (int) config('security.otp.max_resends_per_hour')) {
            $this->audit->failure('auth.2fa.rate_limited', 'security', 'OTP hourly resend limit reached.', $user);

            return ['ok' => false, 'reason' => 'hourly_limit', 'code' => null, 'retry_after' => 3600];
        }

        $code = $this->generateCode();

        DB::transaction(function () use ($user, $purpose, $code, $ip): void {
            // Invalidate every previous, still-unused code for this purpose.
            EmailOtp::query()
                ->where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            EmailOtp::query()->create([
                'user_id' => $user->id,
                'purpose' => $purpose,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes((int) config('security.otp.expiry_minutes')),
                'attempts' => 0,
                'max_attempts' => (int) config('security.otp.max_attempts'),
                'last_sent_at' => now(),
                'ip_address' => $ip,
            ]);
        });

        Mail::to($user->email)->send(new TwoFactorOtpMail(
            $user,
            $code,
            (int) config('security.otp.expiry_minutes'),
        ));

        return ['ok' => true, 'reason' => 'issued', 'code' => $code, 'retry_after' => null];
    }

    /**
     * Verify a submitted code.
     *
     * @return array{ok: bool, reason: string}
     */
    public function verify(User $user, string $code): array
    {
        $purpose = $this->purpose();

        /** @var EmailOtp|null $otp */
        $otp = EmailOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            return ['ok' => false, 'reason' => 'no_code'];
        }

        if ($otp->isExpired()) {
            return ['ok' => false, 'reason' => 'expired'];
        }

        if (! $otp->hasAttemptsLeft()) {
            return ['ok' => false, 'reason' => 'too_many_attempts'];
        }

        // Count every attempt, valid or not.
        $otp->increment('attempts');

        if (! Hash::check(trim($code), $otp->code_hash)) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        // Single use.
        $otp->forceFill(['consumed_at' => now()])->save();

        return ['ok' => true, 'reason' => 'verified'];
    }

    /** True when a usable (unexpired, unconsumed) code exists. */
    public function hasActiveCode(User $user): bool
    {
        return EmailOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $this->purpose())
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    /** Remove expired / consumed codes. Returns the number deleted. */
    public function purgeExpired(): int
    {
        return EmailOtp::query()
            ->where(function ($q): void {
                $q->where('expires_at', '<', now()->subDay())
                    ->orWhere('consumed_at', '<', now()->subDay());
            })
            ->delete();
    }

    private function generateCode(): string
    {
        $length = max(4, (int) config('security.otp.length'));
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    private function purpose(): string
    {
        return (string) config('security.otp.purpose');
    }
}
