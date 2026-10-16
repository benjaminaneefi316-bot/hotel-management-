<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment records.
 *
 * SECURITY: this project NEVER stores card numbers (PAN), CVVs, PINs, or any
 * other payment authentication data. Only a tokenised provider reference and
 * status metadata are kept, as required by PCI DSS. See docs/SECURITY.md.
 *
 * Valid `status` values (see App\Enums\PaymentStatus):
 *   pending | paid | refunded | failed | voided
 *
 * Valid `method` values (see App\Enums\PaymentMethod):
 *   cash | card | bank_transfer | mobile_money
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // recorded by

            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('method', 30)->default('cash');
            $table->string('status', 30)->default('pending')->index();

            // Tokenised reference from a payment provider - never a card number.
            $table->string('provider', 40)->nullable();
            $table->string('provider_reference', 140)->nullable()->index();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
            $table->index(['status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
