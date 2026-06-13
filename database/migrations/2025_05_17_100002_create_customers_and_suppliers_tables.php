<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('type', 16);
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('outstanding_balance', 12, 2)->default(0);
            $table->timestamp('last_settled_at')->nullable();
            $table->string('settlement_cycle', 16)->default('weekly');
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamps();

            $table->index('branch_id');
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->decimal('total_debt', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignUuid('linked_supplier_id')->nullable()->after('last_settled_at')->constrained('suppliers')->nullOnDelete();
            $table->unique('linked_supplier_id');
        });

        Schema::create('contra_settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers');
            $table->foreignUuid('supplier_id')->constrained('suppliers');
            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'created_at']);
            $table->index(['supplier_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contra_settlements');
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('linked_supplier_id');
        });
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
    }
};
