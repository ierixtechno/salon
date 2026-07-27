<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `body` supports simple {{placeholder}} tokens resolved by
        // RenderNotificationTemplate (customer_name, business_name, etc.)
        // — never raw PHP/Blade, so a tenant editing their own template
        // can never execute code (CLAUDE.md §30 XSS / untrusted content).
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('channel'); // email | sms | whatsapp
            $table->string('subject')->nullable(); // email only
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
