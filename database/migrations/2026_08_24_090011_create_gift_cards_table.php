<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `code` is the public-facing reference (CLAUDE.md §19) — server
        // generated, never client supplied, unique per tenant. Purchase
        // (issuance) is recorded directly here, same D-006 scope note as
        // CustomerPackage/CustomerMembership. `customer_id` is nullable:
        // a gift card may be bought as a gift for someone without a
        // Customer record yet (redemption still requires it be presented
        // by code, not tied to who originally bought it).
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->decimal('initial_value', 12, 2);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purchase_method'); // cash | card | upi | bank_transfer
            $table->string('purchase_reference')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->string('status')->default('active'); // active | cancelled | expired
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('issued_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_cards');
    }
};
