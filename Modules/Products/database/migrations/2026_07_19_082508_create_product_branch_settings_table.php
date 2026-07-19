<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_branch_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('min_stock', 12, 3)->nullable();
            $table->decimal('max_stock', 12, 3)->nullable();
            $table->decimal('selling_price_override', 14, 2)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_branch_settings');
    }
};
