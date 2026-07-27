<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Empty-means-all convention for each dimension independently
        // (mirrors Service::isAvailableAtBranch's branch-pivot pattern):
        // a plan with no module rows applies to every module, no branch
        // rows applies at every branch, no service rows applies to every
        // service (within whichever modules/branches already passed).
        Schema::create('membership_plan_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['membership_plan_id', 'module_id']);
        });

        Schema::create('membership_plan_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['membership_plan_id', 'branch_id']);
        });

        Schema::create('membership_plan_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['membership_plan_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plan_services');
        Schema::dropIfExists('membership_plan_branches');
        Schema::dropIfExists('membership_plan_modules');
    }
};
