<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('held_bills', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('terminal_id')->constrained('branch_terminals')->cascadeOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->json('cart_snapshot');
            $table->timestamp('held_at');
            $table->timestamps();

            $table->index(['business_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('held_bills');
    }
};
