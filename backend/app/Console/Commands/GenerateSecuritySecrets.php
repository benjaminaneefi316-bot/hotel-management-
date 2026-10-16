<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Generates the two secrets this application needs but must never commit:
 *
 *   APP_PASSWORD_PEPPER  - the application-wide password pepper
 *   APP_ENCRYPTION_KEY   - the AES-256-GCM key for field + backup encryption
 *
 * Both are printed only to the console. Store them in .env locally and in
 * AWS Secrets Manager / SSM Parameter Store in production.
 */
class GenerateSecuritySecrets extends Command
{
    protected $signature = 'security:generate-secrets {--write : Also write/update the values in the .env file}';

    protected $description = 'Generate APP_PASSWORD_PEPPER and APP_ENCRYPTION_KEY values';

    public function handle(): int
    {
        $pepper = bin2hex(random_bytes(32));
        $key = base64_encode(random_bytes(32));

        $this->newLine();
        $this->line('<fg=yellow>Generated secrets - store these securely and never commit them:</>');
        $this->newLine();
        $this->line('APP_PASSWORD_PEPPER='.$pepper);
        $this->line('APP_ENCRYPTION_KEY='.$key);
        $this->newLine();

        if ($this->option('write')) {
            $path = base_path('.env');

            if (! is_file($path)) {
                $this->error('No .env file found; copy .env.example first.');

                return self::FAILURE;
            }

            $env = (string) file_get_contents($path);
            $env = $this->upsert($env, 'APP_PASSWORD_PEPPER', $pepper);
            $env = $this->upsert($env, 'APP_ENCRYPTION_KEY', $key);
            file_put_contents($path, $env);

            $this->info('Written to .env');
        }

        $this->warn('Changing APP_PASSWORD_PEPPER later requires a rotation plan: move the old value');
        $this->warn('into APP_PASSWORD_PEPPER_PREVIOUS so existing passwords still verify.');

        return self::SUCCESS;
    }

    private function upsert(string $env, string $key, string $value): string
    {
        if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $env) === 1) {
            return (string) preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $key.'='.$value, $env);
        }

        return rtrim($env, "\r\n").PHP_EOL.$key.'='.$value.PHP_EOL;
    }
}
