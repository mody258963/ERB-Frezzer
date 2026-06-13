<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code');
            $table->string('name');
            $table->foreignUuid('category_id')->constrained('part_categories');
            $table->string('unit', 32);
            $table->decimal('sell_price', 12, 2);
            $table->decimal('cost_price', 12, 2);
            $table->unsignedInteger('min_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('image_path')->nullable();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamps();

            $table->unique(['code', 'branch_id'], 'parts_code_branch_id_unique');
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
