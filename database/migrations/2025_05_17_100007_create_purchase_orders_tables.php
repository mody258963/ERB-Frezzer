<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('po_number')->unique();
            $table->foreignUuid('supplier_id')->constrained('suppliers');
            $table->foreignUuid('branch_id')->constrained('branches');
            $table->text('description')->nullable();
            $table->decimal('total_amount', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('payment_type', 32);
            $table->string('status', 32);
            $table->timestamp('received_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('po_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignUuid('part_id')->constrained('parts');
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total', 12, 2);
        });

        Schema::create('supplier_installments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('po_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers');
            $table->unsignedInteger('installment_no');
            $table->decimal('amount', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->date('due_date');
            $table->boolean('is_paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->foreignUuid('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['supplier_id', 'is_paid']);
            $table->index('due_date');
        });

        Schema::create('supplier_installment_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('installment_id')->constrained('supplier_installments')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers');
            $table->foreignUuid('po_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 32);
            $table->foreignUuid('paid_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->useCurrent();

            $table->index(['supplier_id', 'paid_at']);
            $table->index(['installment_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_installment_payments');
        Schema::dropIfExists('supplier_installments');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
