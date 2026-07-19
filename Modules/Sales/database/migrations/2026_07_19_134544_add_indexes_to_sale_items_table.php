<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * sale_items had no indexes beyond the primary key — a MySQL FK
     * constraint auto-indexes its own column, but SQLite doesn't, and
     * neither gives the composite (business_id, product_id) pattern that
     * Reports' product-performance queries (GROUP BY product_id within a
     * business) actually need. Found during the Phase 7 index review.
     */
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->index(['business_id', 'sale_id']);
            $table->index(['business_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'sale_id']);
            $table->dropIndex(['business_id', 'product_id']);
        });
    }
};
