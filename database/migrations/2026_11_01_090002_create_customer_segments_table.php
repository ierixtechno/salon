<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `type` is a whitelist ResolveSegmentCustomers switches on — never
        // a raw stored query — so a tenant-editable segment can never
        // become an arbitrary-SQL injection vector (CLAUDE.md §29).
        // `criteria` holds the small parameter each type needs (e.g.
        // {"tag": "vip"} or {"days": 90}), not a free-form filter tree.
        Schema::create('customer_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // all | tag | inactive_days
            $table->json('criteria')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_segments');
    }
};
