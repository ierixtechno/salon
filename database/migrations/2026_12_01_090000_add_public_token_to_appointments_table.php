<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The public/unauthenticated view-and-cancel-my-booking link
        // (Phase 13, CLAUDE.md §75) must never expose the internal
        // auto-increment id in a URL (CLAUDE.md §19) — `group_uuid` is the
        // wrong shape to reuse for this (it's shared across a multi-service
        // booking group, not unique per appointment). Generated for every
        // appointment, not just online ones, for consistency; nullable only
        // because existing rows predate this column.
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('public_token', 26)->nullable()->unique()->after('group_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });
    }
};
