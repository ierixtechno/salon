<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Purchase recorded directly here, same D-006 scope note as
        // CustomerPackage. `usage_count` accumulates only via the discount
        // application inside AddInvoiceLine, never mass assignable.
        Schema::create('customer_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('membership_plan_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->decimal('price_paid', 12, 2);
            $table->string('purchase_method'); // cash | card | upi | bank_transfer
            $table->string('purchase_reference')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');
            $table->unsignedInteger('usage_count')->default(0);
            $table->string('status')->default('active'); // active | expired | cancelled
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_memberships');
    }
};
