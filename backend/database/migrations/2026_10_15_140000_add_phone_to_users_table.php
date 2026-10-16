<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional contact phone number to staff/administrator accounts.
 *
 * It is deliberately nullable: existing accounts and the guest-facing flow do
 * not require it, and the guest contact number lives (encrypted) on the
 * `guests` table. This column is for the people who operate the system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 40)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
