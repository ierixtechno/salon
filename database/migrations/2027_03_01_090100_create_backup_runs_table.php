<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History of every backup attempt — including failures, which is the whole
 * point: a backup that silently stopped working weeks ago is the classic
 * way to discover you have no backup at the moment you need one. The
 * archive itself lives on disk (config('backup.path')); this table is only
 * the record of what was attempted and how it went.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('trigger', 12); // scheduled | manual
            $table->string('status', 10); // running | success | failed
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('table_count')->nullable();
            $table->unsignedBigInteger('row_count')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('platform_admin_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
