<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('payable_type')->nullable();
            $table->unsignedBigInteger('payable_id')->nullable();
            $table->string('gateway_key', 30);
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('NPR');
            $table->enum('status', ['pending', 'authorized', 'captured', 'failed', 'cancelled', 'refunded'])->default('pending');
            $table->string('gateway_reference', 100)->nullable();
            // text, not json: the model encrypts meta (encrypted:array cast),
            // and the ciphertext is not valid JSON — Postgres rejects it in a
            // json column even though SQLite happily stored it in dev.
            $table->text('meta')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
            $table->index(['business_id', 'gateway_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
