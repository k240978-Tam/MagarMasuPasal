<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * roles.business_id NULL = seeded system role template (Owner, Manager, ...);
     * non-null = a tenant's own Custom Role. Spatie's role name uniqueness stays
     * scoped per-guard; we additionally scope it per-business at the app layer.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->after('id')
                ->constrained('businesses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
        });
    }
};
