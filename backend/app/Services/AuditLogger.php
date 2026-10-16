<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditOutcome;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Central audit trail.
 *
 * Every sensitive action (login, 2FA, role change, archiving, backup, restore,
 * access denial, ...) is written here. The logger is defensive: it never
 * throws into the caller, and it redacts secrets before storing anything.
 */
final class AuditLogger
{
    /**
     * Attribute names whose values are NEVER written to the audit trail.
     * Matched case-insensitively as a substring.
     *
     * @var string[]
     */
    private const REDACTED_KEYS = [
        'password', 'passwd', 'salt', 'pepper', 'secret', 'token',
        'otp', 'code', 'authorization', 'remember', 'api_key', 'apikey',
        'cvv', 'cvc', 'pan', 'card_number', 'cardnumber', 'private_key',
        'encryption_key', 'app_key',
    ];

    /**
     * Write an audit entry.
     *
     * @param  string  $action  machine name, e.g. "user.role.change"
     * @param  string  $eventType  auth|security|data|system|admin
     * @param  AuditOutcome|string  $outcome
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(
        string $action,
        string $eventType,
        AuditOutcome|string $outcome = AuditOutcome::Success,
        ?string $description = null,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?Authenticatable $actor = null,
    ): ?AuditLog {
        try {
            $actor ??= auth()->user();
            $request = function_exists('request') ? request() : null;

            return AuditLog::create([
                'user_id' => $actor?->getAuthIdentifier(),
                'actor_username' => $actor?->getAttribute('username')
                    ?? $actor?->getAttribute('email'),
                'action' => $action,
                'event_type' => $eventType,
                'description' => $description,
                'auditable_type' => $subject ? $subject::class : null,
                'auditable_id' => $subject?->getKey(),
                'ip_address' => $request?->ip(),
                'user_agent' => is_string($request?->userAgent()) ? substr((string) $request->userAgent(), 0, 500) : null,
                'old_values' => $oldValues ? $this->redact($oldValues) : null,
                'new_values' => $newValues ? $this->redact($newValues) : null,
                'outcome' => $outcome instanceof AuditOutcome ? $outcome->value : $outcome,
            ]);
        } catch (Throwable $e) {
            // Auditing must never break the request it is describing.
            Log::error('Failed to write audit log.', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Convenience: successful event. */
    public function success(
        string $action,
        string $eventType,
        ?string $description = null,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
    ): ?AuditLog {
        return $this->record($action, $eventType, AuditOutcome::Success, $description, $subject, $oldValues, $newValues);
    }

    /** Convenience: failed event (e.g. wrong password). */
    public function failure(
        string $action,
        string $eventType,
        ?string $description = null,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?Authenticatable $actor = null,
    ): ?AuditLog {
        return $this->record($action, $eventType, AuditOutcome::Failure, $description, $subject, $oldValues, $newValues, $actor);
    }

    /** Convenience: access denied (authorization failure). */
    public function denied(
        string $action,
        ?string $description = null,
        ?Model $subject = null,
    ): ?AuditLog {
        return $this->record($action, 'security', AuditOutcome::Denied, $description, $subject);
    }

    /**
     * Remove secret-bearing keys from an array before it is persisted.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function redact(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if ($this->isSensitive((string) $key)) {
                $clean[$key] = '[redacted]';

                continue;
            }

            $clean[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $clean;
    }

    private function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::REDACTED_KEYS as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
