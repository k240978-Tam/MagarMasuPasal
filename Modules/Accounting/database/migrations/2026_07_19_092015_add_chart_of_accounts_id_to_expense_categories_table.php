<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maps each expense category to the expense account its journal entries
     * post against, so P&L can break expenses down the same way the
     * Expenses module's own reports do. Nullable: falls back to a generic
     * "General Expenses" account when unset.
     */
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->foreignId('chart_of_accounts_id')->nullable()->after('name')
                ->constrained('chart_of_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chart_of_accounts_id');
        });
    }
};
