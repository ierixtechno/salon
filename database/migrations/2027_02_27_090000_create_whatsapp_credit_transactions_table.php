<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A ledger, never a mutable balance column — mirrors wallet_transactions
 * (CLAUDE.md §20/§21 money-safety precedent, applied here to a credit
 * count rather than money). `amount` is signed: positive for a Super
 * Admin top-up, negative (-1) for one WhatsApp message actually sent.
 * Balance for a tenant is always SUM(amount).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // credit | debit
            $table->integer('amount'); // credit count, not money — 1 credit = 1 WhatsApp message
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason')->nullable();
            // Set only for 'credit' rows (who topped it up) — always null
            // for system-generated 'debit' rows (DeliverNotification has
            // no human actor).
            $table->foreignId('platform_admin_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_credit_transactions');
    }
};
