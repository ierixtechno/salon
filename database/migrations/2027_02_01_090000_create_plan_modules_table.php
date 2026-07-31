<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which vertical modules (Salon/Beauty/Spa/Tattoo) a subscription
        // plan bundles — separate from `plan_features` (generic capability
        // flags/limits like advanced reporting): CLAUDE.md §10 keeps
        // module/feature/plan/permission distinct concepts. Paying an
        // invoice for this plan syncs the tenant's enabled modules to
        // exactly this set (PayQuotation action).
        Schema::create('plan_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['subscription_plan_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_modules');
    }
};
