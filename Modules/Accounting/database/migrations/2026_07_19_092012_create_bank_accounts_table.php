<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('bank_name', 150)->nullable();
            $table->text('account_number')->nullable();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->foreignId('chart_of_accounts_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
