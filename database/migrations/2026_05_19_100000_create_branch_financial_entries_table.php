<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_financial_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entry_number')->unique();
            $table->foreignUuid('creditor_branch_id')->constrained('branches');
            $table->foreignUuid('debtor_branch_id')->constrained('branches');
            $table->decimal('amount', 12, 2);
            $table->string('entry_type', 32);
            $table->string('status', 32)->default('open');
            $table->string('reference_type', 64)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamp('settled_at')->nullable();
            $table->foreignUuid('settled_by')->nullable()->constrained('users');
            $table->timestamp('voided_at')->nullable();
            $table->foreignUuid('voided_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['debtor_branch_id', 'creditor_branch_id', 'status'], 'bfe_branch_pair_status_idx');
            $table->index(['reference_type', 'reference_id'], 'bfe_reference_idx');
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
        Schema::dropIfExists('branch_financial_entries');
    }
};
