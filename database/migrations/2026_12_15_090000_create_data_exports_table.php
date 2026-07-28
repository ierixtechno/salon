<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Data export/portability (CLAUDE.md §66) — queued (§43), tenant
        // scoped, permission protected. `file_path` points at a private
        // disk location, never publicly reachable; `expires_at` bounds how
        // long a ZIP containing customer PII sits on disk before the
        // scheduled prune command deletes it (CLAUDE.md §34/§36).
        // `status`/`file_path`/timestamps are deliberately not fillable —
        // set only via RequestTenantDataExport/GenerateTenantDataExport.
        Schema::create('data_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending'); // pending | processing | completed | failed
            $table->string('file_path')->nullable();
            $table->text('failure_reason')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_exports');
    }
};
