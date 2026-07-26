<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shared, polymorphic weekly working-hours table: one owner is
        // either a Tenant (organization-level default) or a Branch
        // (override). CLAUDE.md §3 — do not duplicate this shared concept
        // per owner type. tenant_id is denormalized here (also derivable
        // via the polymorphic owner) purely so BelongsToTenant/TenantScope
        // can scope this table the same uniform way as everything else.
        Schema::create('business_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->morphs('owner'); // owner_type, owner_id
            $table->unsignedTinyInteger('day_of_week'); // 0 = Sunday .. 6 = Saturday
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'day_of_week'], 'business_hours_owner_day_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hours');
    }
};
