<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('public_id')->unique()->after('id');
            $table->foreignId('business_id')->nullable()->after('public_id')
                ->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('default_branch_id')->nullable()->after('business_id')
                ->constrained('branches')->nullOnDelete();
            $table->string('phone', 30)->nullable()->after('email');
            $table->enum('status', ['active', 'suspended'])->default('active')->after('phone');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->softDeletes();

            $table->unique(['business_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'email']);
            $table->dropConstrainedForeignId('default_branch_id');
            $table->dropConstrainedForeignId('business_id');
            $table->dropColumn([
                'public_id', 'phone', 'status', 'two_factor_secret',
                'two_factor_recovery_codes', 'two_factor_confirmed_at',
                'last_login_at', 'last_login_ip', 'deleted_at',
            ]);
        });
    }
};
