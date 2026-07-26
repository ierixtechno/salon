<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One customer identity shared across every enabled vertical
        // (CLAUDE.md §14) — never forked per module.
        //
        // erasure_requested_at / erased_at / is_erased implement D-004
        // (docs/decisions/README.md): a DPDP erasure request anonymizes
        // this row in place. It is never deleted, because Phase 5/6 will
        // add appointments/invoices with a foreign key to customer_id that
        // must never dangle (CLAUDE.md §36/§45).
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->json('tags')->nullable();
            $table->json('preferences')->nullable();
            $table->string('source')->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('erasure_requested_at')->nullable();
            $table->timestamp('erased_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
