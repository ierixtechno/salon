<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A tenant's request to lower how many directly-purchased employee slots
     * its subscription is billed for — the employee-only counterpart to
     * branch_reduction_requests. Only Super Admin can approve one; see
     * RequestEmployeeReduction / DecideEmployeeReductionRequest.
     *
     * Deliberately NOT tenant-scoped via BelongsToTenant (same reasoning as
     * BranchReductionRequest/Quotation): Super Admin needs cross-tenant
     * visibility.
     */
    public function up(): void
    {
        Schema::create('employee_reduction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->unsignedInteger('current_extra_user_count');
            $table->unsignedInteger('requested_extra_user_count');
            $table->text('reason')->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected | cancelled
            $table->foreignId('decided_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_reduction_requests');
    }
};
