<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recovery migration when 2026_05_19_100000 failed after CREATE TABLE (MySQL index name > 64 chars).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branch_financial_entries')) {
            return;
        }

        if (! $this->indexExists('branch_financial_entries', 'bfe_branch_pair_status_idx')) {
            Schema::table('branch_financial_entries', function (Blueprint $table) {
                $table->index(['debtor_branch_id', 'creditor_branch_id', 'status'], 'bfe_branch_pair_status_idx');
            });
        }

        if (! $this->indexExists('branch_financial_entries', 'bfe_reference_idx')) {
            Schema::table('branch_financial_entries', function (Blueprint $table) {
                $table->index(['reference_type', 'reference_id'], 'bfe_reference_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('branch_financial_entries')) {
            return;
        }

        Schema::table('branch_financial_entries', function (Blueprint $table) {
            $table->dropIndex('bfe_branch_pair_status_idx');
            $table->dropIndex('bfe_reference_idx');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]);

        return $rows !== [];
    }
};
