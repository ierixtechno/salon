<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * spatie/laravel-permission's published migration creates the
     * `tenant_id` "team" column as a plain unsignedBigInteger (no FK,
     * since the package doesn't know about our `tenants` table). This
     * migration adds the real foreign key now that `tenants` exists.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });
    }
};
