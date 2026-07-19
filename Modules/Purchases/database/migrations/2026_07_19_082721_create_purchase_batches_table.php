<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The FIFO cost-layer record, created on goods receipt. quantity_remaining
     * decreases as Inventory consumes it oldest-first; quantity_received never
     * changes once written, preserving the original receipt for valuation history.
     */
    public function up(): void
    {
        Schema::create('purchase_batches', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->string('batch_number', 64)->nullable();
            $table->decimal('quantity_received', 12, 3);
            $table->decimal('quantity_remaining', 12, 3);
            $table->decimal('unit_cost', 14, 2);
            $table->date('expiry_date')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['business_id', 'branch_id', 'product_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_batches');
    }
};
