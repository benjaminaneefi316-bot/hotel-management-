<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roles used by the role-based access control (RBAC) system.
 *
 * The three roles required by the assignment are:
 *   admin         - full access (users, roles, backups, restore points, audit logs)
 *   receptionist  - operational access (guests, rooms, bookings, check-in/out)
 *   guest         - self-service access (own profile, own bookings only)
 *
 * Roles are seeded in database/seeders/RoleSeeder.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('display_name', 80);
            $table->string('description', 255)->nullable();
            // System roles (admin) cannot be deleted and are protected from
            // accidental removal in the application layer.
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
