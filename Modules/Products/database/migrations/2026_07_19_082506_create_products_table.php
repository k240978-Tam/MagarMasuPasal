<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 170);
            $table->string('sku', 64)->nullable();
            $table->string('barcode', 64)->nullable();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->boolean('sell_by_weight')->default(false);
            $table->boolean('track_batches')->default(false);
            $table->boolean('track_expiry')->default(false);
            $table->decimal('cost_price', 14, 2)->default(0);
            $table->decimal('selling_price', 14, 2)->default(0);
            $table->decimal('min_stock', 12, 3)->nullable();
            $table->decimal('max_stock', 12, 3)->nullable();
            $table->foreignId('default_supplier_id')->nullable();
            $table->unsignedBigInteger('image_media_id')->nullable();
            $table->enum('status', ['active', 'inactive', 'discontinued'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'sku']);
            $table->unique(['business_id', 'barcode']);
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
