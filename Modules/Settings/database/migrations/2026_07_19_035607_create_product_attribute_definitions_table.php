<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attribute_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('business_type_id')->nullable()->constrained('business_types')->nullOnDelete();
            $table->string('key', 100);
            $table->string('label', 150);
            $table->enum('data_type', ['string', 'number', 'date', 'boolean', 'enum']);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->enum('applies_to', ['product', 'sale_item'])->default('product');
            $table->timestamps();

            $table->unique(['business_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_definitions');
    }
};
