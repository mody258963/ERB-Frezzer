<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('capital_amount', 14, 2)->default(0);
            $table->string('currency', 8)->default('EGP');
            $table->text('notes')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('capital_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 32)->default('manual_set');
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->decimal('previous_amount', 14, 2);
            $table->decimal('new_amount', 14, 2);
            $table->decimal('change_amount', 14, 2);
            $table->text('reason')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();

            $table->index('type');
            $table->index('branch_id');
        });

        Schema::create('owner_cash_outs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('amount', 14, 2);
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_cash_outs');
        Schema::dropIfExists('capital_adjustments');
        Schema::dropIfExists('company_settings');
    }
};
