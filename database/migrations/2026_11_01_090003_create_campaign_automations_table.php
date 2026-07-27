<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configuration only — no execution state. Off (is_enabled=false,
        // template_id=null) by default (CLAUDE.md §70: an additive,
        // tenant-gated behavior, never forced on) — RunMarketingAutomations
        // silently skips any type that isn't enabled or has no template
        // configured yet.
        Schema::create('campaign_automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // birthday | membership_expiry | package_expiry | re_engagement | feedback_request
            $table->boolean('is_enabled')->default(false);
            $table->foreignId('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->unsignedInteger('threshold_days')->nullable(); // meaning depends on type: expiry lookahead, or inactivity window
            $table->timestamps();

            $table->unique(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_automations');
    }
};
