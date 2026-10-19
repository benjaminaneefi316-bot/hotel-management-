<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Break-glass tool for the "I lost my phone" case.
 *
 * Clearing the enrolled key drops the account back to the legacy emailed-code
 * path, which is what lets a locked-out administrator sign in again and enrol a
 * fresh authenticator from their profile page.
 *
 * Run it on the server, never over the network:
 *
 *   php artisan two-factor:disable someone@example.com --force
 */
class DisableTwoFactor extends Command
{
    protected $signature = 'two-factor:disable
                            {email : The account to reset}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Turn off authenticator-app two-factor authentication for one account';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No account found for {$email}.");

            return self::FAILURE;
        }

        $hadKey = $user->two_factor_secret !== null;

        if (! $this->option('force')) {
            $this->warn(sprintf(
                'This removes the authenticator key for %s and turns their second factor off.',
                $email,
            ));

            if (! $this->confirm('Continue?', false)) {
                $this->line('Cancelled.');

                return self::SUCCESS;
            }
        }

        // forceFill so the encrypted cast does not try to re-encrypt the null
        // and so a disabled account stays disabled.
        $user->forceFill([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->info("Two-factor authentication is now off for {$email}.".($hadKey ? ' Enrolled key removed.' : ''));

        return self::SUCCESS;
    }
}
