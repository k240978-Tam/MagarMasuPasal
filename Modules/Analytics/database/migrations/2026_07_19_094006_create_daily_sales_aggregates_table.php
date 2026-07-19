<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pre-computed so the dashboard's "last 12 months" trend reads one small
     * table instead of scanning sale_items at request time
     * (docs/architecture/03-database-schema.md §3.7). Populated by a nightly
     * job, not a request-time query.
     */
    public function up(): void
    {
        Schema::create('daily_sales_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->decimal('total_tax', 14, 2)->default(0);
            $table->decimal('total_discount', 14, 2)->default(0);
            $table->unsignedInteger('transaction_count')->default(0);
            $table->decimal('gross_profit', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'date']);
            $table->index(['business_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sales_aggregates');
    }
};
