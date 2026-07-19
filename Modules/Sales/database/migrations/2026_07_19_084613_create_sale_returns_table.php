<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->string('reason')->nullable();
            $table->enum('refund_method', ['cash', 'original_payment', 'store_credit'])->default('cash');
            $table->decimal('refund_amount', 14, 2);
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'sale_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};
