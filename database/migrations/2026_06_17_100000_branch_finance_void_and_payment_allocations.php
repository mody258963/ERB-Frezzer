<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_financial_entries', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('settled_by');
            $table->foreignUuid('voided_by')->nullable()->after('voided_at')->constrained('users');
        });

        Schema::create('branch_financial_payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_entry_id')->constrained('branch_financial_entries')->cascadeOnDelete();
            $table->foreignUuid('charge_entry_id')->constrained('branch_financial_entries')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index('payment_entry_id');
            $table->index('charge_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_financial_payment_allocations');

        Schema::table('branch_financial_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn('voided_at');
        });
    }
};
