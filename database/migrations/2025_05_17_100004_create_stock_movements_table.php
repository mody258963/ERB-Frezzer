<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('part_id')->constrained('parts');
            $table->foreignUuid('branch_id')->constrained('branches');
            $table->string('movement_type', 32);
            $table->integer('quantity');
            $table->uuid('reference_id')->nullable();
            $table->string('reference_type', 64)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['part_id', 'branch_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
