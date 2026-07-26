<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extends a User with HR-facing profile data. Deliberately not
        // merged into `users` — auth identity vs. employee profile are
        // different lifecycles/concerns. Service capability (CLAUDE.md
        // §14, "staff capability") is added in Phase 4 once a Service
        // Catalog exists to reference; attendance/leave/commission are
        // Phase 9 (see docs/modules/EMPLOYEE.md).
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('job_title')->nullable();
            $table->string('employment_type')->default('full_time'); // full_time | part_time | contract
            $table->date('hire_date')->nullable();
            $table->string('phone')->nullable();
            $table->json('skills')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
