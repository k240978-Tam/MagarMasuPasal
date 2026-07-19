<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address')->nullable();
            $table->decimal('current_due', 14, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'phone']);
            $table->index(['business_id', 'customer_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
