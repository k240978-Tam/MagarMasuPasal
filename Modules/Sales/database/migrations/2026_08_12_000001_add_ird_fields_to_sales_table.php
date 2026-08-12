<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields the IRD Electronic Billing Directive requires a tax invoice to
 * carry or a billing system to track: fiscal-year-scoped numbering, print
 * (copy) counting, a cancellation record that keeps the invoice, and CBMS
 * sync state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Nepali fiscal year the invoice belongs to, e.g. "2083/84", and
            // the per-business sequence within it — invoice numbers must
            // restart each fiscal year.
            $table->string('fiscal_year', 9)->nullable()->after('invoice_no');
            $table->unsignedInteger('fiscal_sequence')->nullable()->after('fiscal_year');

            // Buyer identity is copied onto the invoice rather than read
            // through the customer relation: a tax invoice must keep the
            // name and PAN as they were at the time of sale, even if the
            // customer record is later edited.
            $table->string('buyer_name', 150)->nullable()->after('customer_id');
            $table->string('buyer_pan', 30)->nullable()->after('buyer_name');

            // The directive requires every reprint to be marked as a copy
            // and counted.
            $table->unsignedInteger('print_count')->default(0)->after('completed_at');
            $table->timestamp('first_printed_at')->nullable()->after('print_count');

            // Cancelled invoices are retained and reported, never deleted.
            $table->timestamp('cancelled_at')->nullable()->after('first_printed_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 255)->nullable()->after('cancelled_by');

            // CBMS (IRD real-time billing) sync state.
            $table->string('ird_sync_status', 20)->default('pending')->after('cancellation_reason');
            $table->timestamp('ird_synced_at')->nullable()->after('ird_sync_status');
            $table->text('ird_sync_error')->nullable()->after('ird_synced_at');

            $table->unique(['business_id', 'fiscal_year', 'fiscal_sequence'], 'sales_fiscal_number_unique');
            $table->index(['business_id', 'ird_sync_status'], 'sales_ird_sync_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            // Needed on the invoice for B2B sales; encrypted like every other
            // PAN/VAT number in the system, so it needs room for ciphertext.
            $table->text('pan_vat_number')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique('sales_fiscal_number_unique');
            $table->dropIndex('sales_ird_sync_index');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'fiscal_year',
                'fiscal_sequence',
                'buyer_name',
                'buyer_pan',
                'print_count',
                'first_printed_at',
                'cancelled_at',
                'cancellation_reason',
                'ird_sync_status',
                'ird_synced_at',
                'ird_sync_error',
            ]);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('pan_vat_number');
        });
    }
};
