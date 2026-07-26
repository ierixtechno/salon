<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v1 records payments manually (front desk logs "paid via UPI,
        // ref #..."); there is no live gateway integration in this phase.
        // `idempotency_key` (a UUID generated once per payment-form
        // render) is unique so a double-click/resubmit can never create a
        // duplicate payment (CLAUDE.md §31/§37).
        //
        // `tip_amount` is collected in the same transaction (e.g. one card
        // swipe covers invoice + tip) but is NOT part of the invoice's
        // taxable total — it's a gratuity to staff, not salon revenue —
        // so it's tracked here on the payment, never added into
        // `amount` (which is strictly what's applied against the invoice
        // balance, POS.md's "tips" requirement).
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained();
            $table->string('method'); // cash | card | upi | bank_transfer
            $table->decimal('amount', 12, 2);
            $table->decimal('tip_amount', 12, 2)->default(0);
            $table->string('reference')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
