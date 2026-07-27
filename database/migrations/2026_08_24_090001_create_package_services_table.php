<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The template "recipe" — which services, and how many of each,
        // a purchased instance of this package entitles the customer to.
        // Plain pivot (withPivot('quantity')), no dedicated model, since it
        // has no lifecycle of its own beyond the association + quantity.
        // Pure pivot of two already tenant-scoped models (Package, Service)
        // — no independent tenant_id needed, same precedent as
        // service_branch/service_user.
        Schema::create('package_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['package_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_services');
    }
};
