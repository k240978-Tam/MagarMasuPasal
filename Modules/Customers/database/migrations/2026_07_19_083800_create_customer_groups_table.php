<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configurable, not hardcoded — a meat shop names these "Retail"/"Credit",
     * a restaurant might rename to "Table"/"Room Service" (docs/architecture/03-database-schema.md §3.4).
     */
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('allow_credit')->default(false);
            $table->decimal('credit_limit', 14, 2)->nullable();
            $table->decimal('default_discount_percent', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_groups');
    }
};
