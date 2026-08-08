<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 150);
            $table->string('legal_name', 150)->nullable();
            $table->foreignId('business_type_id')->constrained('business_types')->restrictOnDelete();
            // text: the model encrypts this field and ciphertext far exceeds 30 chars
            $table->text('pan_vat_number')->nullable();
            $table->char('currency', 3)->default('NPR');
            $table->string('timezone', 50)->default('Asia/Kathmandu');
            $table->unsignedBigInteger('logo_media_id')->nullable();
            $table->enum('status', ['active', 'suspended', 'trial'])->default('trial');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
