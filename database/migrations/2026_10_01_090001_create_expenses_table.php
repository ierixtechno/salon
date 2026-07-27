<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Approval is optional per tenant (BusinessProfile::expenseApprovalRequired(),
        // CLAUDE.md §14 "Approval where configured"): when off, an expense
        // is created directly as `approved`; when on, it starts `pending`
        // and only a Manager/Owner with expenses.approve can decide it.
        // `status`/`approved_by`/`approved_at`/`decision_reason` are
        // deliberately not fillable — set only via CreateExpense/
        // ApproveExpense/RejectExpense (CLAUDE.md §28).
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('expense_category_id')->constrained();
            $table->string('vendor_name')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->string('payment_method'); // cash | card | upi | bank_transfer
            $table->date('expense_date');
            $table->text('description')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->string('decision_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->index(['tenant_id', 'expense_category_id']);
            $table->index(['tenant_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
