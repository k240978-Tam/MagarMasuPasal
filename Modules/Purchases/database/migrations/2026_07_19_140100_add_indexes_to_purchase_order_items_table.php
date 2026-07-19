<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same gap as sale_items (see the Phase 7 index review): no indexes
     * beyond the primary key, so lookups by purchase order or by product
     * across a business (e.g. purchasing history, cost trend reports)
     * would scan the whole table on MySQL.
     */
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->index(['business_id', 'purchase_order_id']);
            $table->index(['business_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'purchase_order_id']);
            $table->dropIndex(['business_id', 'product_id']);
        });
    }
};
