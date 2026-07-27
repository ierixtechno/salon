<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A purchased instance. Purchase is recorded directly on this row
        // (method/reference/price_paid) rather than through the Invoice
        // engine — see docs/decisions/README.md D-006: whether GST applies
        // at prepaid-package sale or only at redemption is a genuine tax
        // question, deliberately not guessed here (same weight as D-003).
        Schema::create('customer_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('package_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->decimal('price_paid', 12, 2);
            $table->string('purchase_method'); // cash | card | upi | bank_transfer
            $table->string('purchase_reference')->nullable();
            $table->dateTime('purchased_at');
            $table->dateTime('expires_at');
            $table->string('status')->default('active'); // active | expired | exhausted | cancelled
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
        Schema::dropIfExists('customer_packages');
    }
};
