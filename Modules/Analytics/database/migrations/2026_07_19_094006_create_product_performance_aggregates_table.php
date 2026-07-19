<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_performance_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('quantity_sold', 12, 3)->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->decimal('profit', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'product_id', 'date']);
            $table->index(['business_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_performance_aggregates');
    }
};
