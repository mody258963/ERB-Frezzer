<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock', function (Blueprint $table) {
            $table->decimal('quantity', 12, 4)->default(0)->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('quantity', 12, 4)->change();
        });

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 4)->change();
        });

        if (! Schema::hasColumn('stock_transfer_items', 'unit_cost')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                $table->decimal('unit_cost', 12, 2)->nullable()->after('quantity');
            });
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 4)->change();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 4)->change();
        });

        Schema::table('return_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            if (Schema::hasColumn('stock_transfer_items', 'unit_cost')) {
                $table->dropColumn('unit_cost');
            }
        });

        Schema::table('stock', function (Blueprint $table) {
            $table->integer('quantity')->default(0)->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->integer('quantity')->change();
        });

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->change();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->change();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->change();
        });

        Schema::table('return_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->change();
        });
    }
};
