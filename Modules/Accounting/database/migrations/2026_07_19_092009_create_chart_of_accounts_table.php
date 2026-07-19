<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['business_id', 'code']);
            $table->index(['business_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
