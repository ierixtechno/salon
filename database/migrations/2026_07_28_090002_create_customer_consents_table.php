<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // D-004 (docs/decisions/README.md): consent is an auditable ledger,
        // not a single mutable flag — every grant/revoke is its own row,
        // who recorded it and when. `customers.marketing_consent` is a
        // denormalized "current state" read model derived from this ledger,
        // not the source of truth.
        Schema::create('customer_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('purpose'); // service_delivery | marketing | data_sharing
            $table->boolean('granted');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_consents');
    }
};
