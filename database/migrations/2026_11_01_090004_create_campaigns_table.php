<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `type` = 'manual' for a staff-created one-off campaign (has a
        // segment_id), or one of the automation types for a system-
        // triggered daily run (segment_id null — recipients are resolved
        // directly by that automation's own precise query, not a generic
        // segment). One Campaign row is created per automation *run* (e.g.
        // "Birthday Wishes — 2026-07-27") rather than reused indefinitely,
        // so history/audit stays per-day and idempotency is just "does
        // today's automation campaign already exist" (CLAUDE.md §44).
        //
        // `status`/`sent_at` are deliberately not fillable — set only via
        // SendCampaign (CLAUDE.md §28).
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('manual'); // manual | birthday | membership_expiry | package_expiry | re_engagement | feedback_request
            $table->string('channel'); // email | sms | whatsapp
            $table->foreignId('template_id')->constrained('notification_templates');
            $table->foreignId('segment_id')->nullable()->constrained('customer_segments')->nullOnDelete();
            $table->string('status')->default('draft'); // draft | scheduled | sending | sent | cancelled
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
