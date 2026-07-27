<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The single delivery ledger for every channel (email/sms/whatsapp/
        // in_app), both transactional and marketing — CLAUDE.md §41/§47:
        // auditable, never overwritten in place once sent/failed.
        // `to_address` snapshots the email/phone used at send time (the
        // customer's address may change later — the log should reflect
        // what was actually used). `read_at` is only ever set for the
        // in_app channel (self-service "my notifications" mark-as-read);
        // in_app rows are written directly as `sent` — there is no external
        // provider to queue a delivery job for.
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('channel'); // email | sms | whatsapp | in_app
            $table->string('recipient_type'); // customer | user
            $table->unsignedBigInteger('recipient_id');
            $table->string('to_address')->nullable();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status')->default('queued'); // queued | sent | failed | skipped
            $table->string('provider')->nullable();
            $table->string('error_message')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'recipient_type', 'recipient_id']);
            $table->index(['tenant_id', 'channel', 'status']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
