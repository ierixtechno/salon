<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Platform-owned record referencing a tenant — deliberately NOT
        // tenant-scoped (no BelongsToTenant) since it's created by the
        // Super Admin and read by both the Platform area (all tenants) and
        // the specific tenant's own Billing pages. Every tenant-facing
        // controller must check tenant_id ownership explicitly (CLAUDE.md
        // §32 IDOR prevention) — there is no automatic scope here.
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained();
            $table->foreignId('platform_admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('quotation_number')->unique();
            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // pending | paid | cancelled
            $table->string('razorpay_order_id')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
