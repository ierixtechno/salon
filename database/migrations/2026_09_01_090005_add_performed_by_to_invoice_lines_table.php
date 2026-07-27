<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which employee to credit commission to for this line — auto-
        // filled from the appointment's employee when the line came from
        // one, optionally picked for a standalone walk-in sale. Nullable:
        // a line with no employee attributed simply never accrues
        // commission, which is the safe default (CLAUDE.md §22 — never
        // trust/guess who should be paid).
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->foreignId('performed_by')->nullable()->after('appointment_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('performed_by');
        });
    }
};
