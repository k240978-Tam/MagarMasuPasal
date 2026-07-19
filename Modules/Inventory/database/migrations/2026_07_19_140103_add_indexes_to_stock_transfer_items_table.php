<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same gap as sale_items (see the Phase 7 index review): no indexes
     * beyond the primary key, so listing a transfer's lines or a
     * product's transfer history across a business would scan the whole
     * table on MySQL.
     */
    public function up(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->index(['business_id', 'stock_transfer_id']);
            $table->index(['business_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'stock_transfer_id']);
            $table->dropIndex(['business_id', 'product_id']);
        });
    }
};
