<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Application settings.
 *
 * Two guard rails make configuration snapshots safe:
 *
 *   isEligible($key)  - the key is on the whitelist in config/restore-points.php
 *                       and may therefore be captured/restored.
 *   isForbidden($key) - the key matches a secret-ish pattern (password, token,
 *                       key, salt, pepper, otp, ...) and is NEVER captured or
 *                       restored, even if someone whitelists it by mistake.
 */
final class SettingsService
{
    private const CACHE_KEY = 'system_settings.all';

    public function __construct(private readonly AuditLogger $audit) {}

    /** @return Collection<int, SystemSetting> */
    public function all(): Collection
    {
        // SECURITY NOTE: Laravel 13 ships with config('cache.serializable_classes')
        // defaulting to false, which means PHP will NOT unserialize *any* class
        // from the cache (a defence against gadget-chain attacks). We therefore
        // cache plain attribute arrays and hydrate models on read — never
        // serialise Eloquent models into the cache.
        $rows = Cache::rememberForever(self::CACHE_KEY, fn (): array => SystemSetting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->map(fn (SystemSetting $setting): array => $setting->getAttributes())
            ->all());

        return SystemSetting::hydrate($rows);
    }

    /** @return Collection<int, SystemSetting> */
    public function eligible(): Collection
    {
        return $this->all()
            ->filter(fn (SystemSetting $s): bool => $this->isEligible($s->key) && ! $this->isForbidden($s->key))
            ->values();
    }

    /** @return Collection<int, SystemSetting> */
    public function group(string $group): Collection
    {
        return $this->all()->where('group', $group)->values();
    }

    /** Typed value of a setting, or $default when absent. */
    public function get(string $key, mixed $default = null): mixed
    {
        $setting = $this->all()->firstWhere('key', $key);

        return $setting?->typedValue() ?? $default;
    }

    public function raw(string $key, ?string $default = null): ?string
    {
        return $this->all()->firstWhere('key', $key)?->value ?? $default;
    }

    /**
     * Create or update a setting.
     *
     * @throws \InvalidArgumentException when the key looks like a secret
     */
    public function set(string $key, mixed $value, ?User $actor = null, bool $audit = true): SystemSetting
    {
        if ($this->isForbidden($key)) {
            throw new \InvalidArgumentException("Refusing to store \"{$key}\": the key matches a protected/secret pattern.");
        }

        $setting = SystemSetting::query()->firstOrNew(['key' => $key]);
        $old = $setting->exists ? $setting->value : null;

        $setting->value = is_array($value) ? json_encode($value) : ($value === null ? null : (string) $value);
        $setting->save();

        $this->flush();

        if ($audit && $old !== $setting->value) {
            $this->audit->success(
                action: 'setting.update',
                eventType: 'system',
                description: sprintf('Setting "%s" changed.', $key),
                subject: $setting,
                oldValues: ['value' => $old],
                newValues: ['value' => $setting->value],
            );
        }

        return $setting;
    }

    public function isEligible(string $key): bool
    {
        return in_array($key, (array) config('restore-points.eligible_settings', []), true);
    }

    /**
     * True when the key must never be part of a snapshot or restore.
     */
    public function isForbidden(string $key): bool
    {
        $key = strtolower($key);

        foreach ((array) config('restore-points.never_snapshot', []) as $needle) {
            if ($needle !== '' && str_contains($key, strtolower((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
