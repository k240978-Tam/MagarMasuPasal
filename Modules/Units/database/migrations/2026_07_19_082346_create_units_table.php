<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * business_id NULL = platform-seeded default unit (kg, g, pcs, ...),
     * available to every tenant; non-null = a tenant's own custom unit.
     */
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('symbol', 10);
            $table->foreignId('base_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('conversion_factor', 12, 6)->default(1);
            $table->timestamps();

            $table->index('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
