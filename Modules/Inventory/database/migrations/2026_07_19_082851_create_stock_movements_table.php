<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only ledger — every stock change, ever. inventory_stocks is
     * derived from this; corrections are new offsetting rows, never edits.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('purchase_batches')->nullOnDelete();
            $table->enum('type', [
                'purchase_receipt', 'sale', 'sale_return', 'adjustment',
                'spoilage', 'waste', 'damage', 'transfer_in', 'transfer_out',
            ]);
            $table->decimal('quantity_change', 12, 3);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('unit_cost_at_movement', 14, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'branch_id', 'product_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
