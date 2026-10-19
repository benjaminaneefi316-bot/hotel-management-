<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authenticator-app (TOTP / RFC 6238) support - Google Authenticator, Authy,
 * 1Password, Microsoft Authenticator, etc.
 *
 * `two_factor_secret` holds the Base32 shared secret and is encrypted at rest
 * through the model's `encrypted` cast, so a stolen database dump alone does
 * not yield the ability to generate the user's codes.
 *
 * Enrolment is deliberately two-step: the secret is only written to the
 * database once the user has proven they can read a code off their app, which
 * means a half-finished setup can never lock anybody out.
 *
 * A user with NO secret simply keeps the existing emailed-code flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['two_factor_secret', 'two_factor_confirmed_at']);
        });
    }
};
