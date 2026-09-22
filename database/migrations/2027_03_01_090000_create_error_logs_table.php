<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-level application error log (see RecordErrorLog) — one row per
 * *distinct* error (grouped by fingerprint), with an occurrence counter,
 * rather than one row per occurrence: a broken page hit 5,000 times is one
 * line to triage, not 5,000 rows to scroll past. Deliberately not tenant
 * scoped / not BelongsToTenant — it is the operator's view across every
 * tenant; tenant_id is just the last tenant affected, as a plain column
 * (no FK, so deleting a tenant never touches error history).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->char('fingerprint', 40)->unique();
            $table->string('exception_class');
            $table->text('message');
            $table->string('file', 500)->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->text('trace')->nullable();
            $table->string('context', 10)->default('web'); // web | cli
            $table->string('http_method', 10)->nullable();
            $table->string('path', 500)->nullable(); // route pattern, never the raw URL
            $table->char('request_id', 26)->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->string('status', 10)->default('open'); // open | resolved
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'last_seen_at']);
            $table->index('tenant_id');
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
