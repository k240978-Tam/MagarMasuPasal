<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same gap as sale_items (see the Phase 7 index review): no indexes
     * beyond the primary key, so listing a sale return's lines or tracing
     * returns back to a sale item across a business would scan the whole
     * table on MySQL.
     */
    public function up(): void
    {
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->index(['business_id', 'sale_return_id']);
            $table->index(['business_id', 'sale_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'sale_return_id']);
            $table->dropIndex(['business_id', 'sale_item_id']);
        });
    }
};
