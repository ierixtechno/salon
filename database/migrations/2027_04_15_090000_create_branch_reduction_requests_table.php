<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A tenant's request to lower the branch count their subscription is
     * billed for (and, with it, their employee limit — see
     * SubscriptionPlan::userLimitFor). Only Super Admin can approve one; see
     * DecideBranchReductionRequest and RequestBranchReduction.
     *
     * Deliberately NOT tenant-scoped via BelongsToTenant (same reasoning as
     * Quotation): Super Admin must see requests across every tenant. Every
     * tenant-facing controller checks tenant_id ownership explicitly.
     */
    public function up(): void
    {
        Schema::create('branch_reduction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->unsignedInteger('current_branch_count');
            $table->unsignedInteger('requested_branch_count');
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
        Schema::dropIfExists('branch_reduction_requests');
    }
};
