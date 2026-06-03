<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('return_number')->unique();
            $table->string('return_type', 32);
            $table->uuid('reference_id');
            $table->string('reference_type', 32);
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches');
            $table->text('reason')->nullable();
            $table->string('status', 32);
            $table->string('resolution', 32)->nullable();
            $table->decimal('total_value', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('attachment_url')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['return_type', 'status']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignUuid('part_id')->constrained('parts');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->string('condition', 32);
            $table->decimal('total', 12, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
    }
};
