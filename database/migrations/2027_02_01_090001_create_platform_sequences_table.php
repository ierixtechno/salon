<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Concurrency-safe sequential numbering for platform-billing
        // documents (quotations, invoices) — same "lock a counter row"
        // pattern as InvoiceSequence (CLAUDE.md §24), just global rather
        // than per-branch since the platform itself has no branches.
        // One shared table with a `sequence_type` discriminator rather than
        // two near-identical tables.
        Schema::create('platform_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('sequence_type'); // 'quotation' | 'invoice'
            $table->string('financial_year'); // e.g. "2026-27"
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['sequence_type', 'financial_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_sequences');
    }
};
