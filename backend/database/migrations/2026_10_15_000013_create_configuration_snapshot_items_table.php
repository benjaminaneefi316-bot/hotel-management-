<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Individual entries inside a configuration snapshot.
 *
 * `item_type`
 *   setting         - item_key = system_settings.key,
 *                     captured_value = value at snapshot time
 *   role_assignment - item_key = "user:<id>",
 *                     captured_value = JSON list of role names the user held
 *
 * `captured_value` is what a restore writes back. Before restoring, the
 * controller shows a preview (current value -> captured value) and asks the
 * administrator to confirm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuration_snapshot_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configuration_snapshot_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 30);
            $table->string('item_key', 160);
            $table->text('captured_value')->nullable();
            $table->timestamps();

            // Explicit short index names: MySQL limits identifiers to 64
            // characters and the default generated name would exceed that.
            $table->unique(
                ['configuration_snapshot_id', 'item_type', 'item_key'],
                'snapshot_items_unique'
            );
            $table->index(['item_type', 'item_key'], 'snapshot_items_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_snapshot_items');
    }
};
