<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saturday_settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('settlement_date');
            $table->foreignUuid('customer_id')->constrained('customers');
            $table->decimal('total_amount', 12, 2);
            $table->string('payment_method', 32);
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'settlement_date']);
        });

        Schema::create('customer_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers');
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 32);
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('saturday_settlements');
    }
};
