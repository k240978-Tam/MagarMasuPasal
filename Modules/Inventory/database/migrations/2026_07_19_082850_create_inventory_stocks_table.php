<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maintained on-hand summary, one row per branch+product — derived from
     * stock_movements (the source of truth), kept here for fast reads.
     */
    public function up(): void
    {
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 12, 3)->default(0);
            $table->decimal('quantity_reserved', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stocks');
    }
};
