<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Soft reference to Settings.tax_rules — no FK constraint, so Products
            // never takes a hard migration-order dependency on the Settings module.
            $table->unsignedBigInteger('tax_rule_id')->nullable()->after('selling_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('tax_rule_id');
        });
    }
};
