<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adds a human/SEO-friendly `slug` to room types so public URLs look like
 *
 *     /accommodation/deluxe-king
 *
 * instead of /accommodation/DLX. The column is nullable + unique so existing
 * rows can be backfilled without a destructive change; the RoomType model
 * generates a slug automatically when one is not supplied.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('room_types', 'slug')) {
            return;
        }

        Schema::table('room_types', function (Blueprint $table): void {
            $table->string('slug', 120)->nullable()->unique()->after('name');
        });

        // Backfill from the existing name, de-duplicating collisions.
        $used = [];

        DB::table('room_types')->orderBy('id')->get(['id', 'name', 'code'])->each(function ($row) use (&$used): void {
            $base = Str::slug((string) $row->name) ?: Str::lower((string) $row->code);
            $slug = $base;
            $suffix = 2;

            while (in_array($slug, $used, true)) {
                $slug = $base.'-'.$suffix++;
            }

            $used[] = $slug;

            DB::table('room_types')->where('id', $row->id)->update(['slug' => $slug]);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('room_types', 'slug')) {
            return;
        }

        Schema::table('room_types', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
