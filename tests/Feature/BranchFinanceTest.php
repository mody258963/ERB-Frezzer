<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchFinancialEntry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_complete_creates_inter_branch_charge(): void
    {
        $this->seed(DatabaseSeeder::class);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $token = (string) $login->json('token');

        $mainBranchId = (string) Branch::query()->value('id');

        $warehouse = $this->withToken($token)->postJson('/api/v1/branches', [
            'name' => 'Finance Branch B',
            'is_active' => true,
        ]);
        $warehouseId = (string) $warehouse->json('id');

        $part = $this->withToken($token)->postJson('/api/v1/parts', [
            'code' => 'FIN-P1',
            'name' => 'Finance Part',
            'category' => 'Compressor',
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 40,
            'min_stock' => 0,
            'is_active' => true,
        ]);
        $partId = (string) $part->json('id');

        $this->withToken($token)->postJson('/api/v1/inventory/adjust', [
            'part_id' => $partId,
            'branch_id' => $mainBranchId,
            'quantity_delta' => 20,
        ])->assertOk();

        $transfer = $this->withToken($token)->postJson('/api/v1/transfers', [
            'from_branch_id' => $mainBranchId,
            'to_branch_id' => $warehouseId,
            'items' => [['part_id' => $partId, 'quantity' => 5]],
        ]);
        $transferId = (string) $transfer->json('id');

        $this->withToken($token)->patchJson("/api/v1/transfers/{$transferId}/complete")->assertOk();

        $this->assertDatabaseHas('branch_financial_entries', [
            'reference_type' => 'stock_transfer',
            'reference_id' => $transferId,
            'creditor_branch_id' => $mainBranchId,
            'debtor_branch_id' => $warehouseId,
            'entry_type' => 'charge',
            'status' => 'open',
        ]);

        $entry = BranchFinancialEntry::query()->where('reference_id', $transferId)->first();
        $this->assertSame(200.0, (float) $entry->amount);

        $balances = $this->withToken($token)->getJson('/api/v1/branch-finance/balances');
        $balances->assertOk()
            ->assertJsonPath('balances.0.balance_owed', 200);
    }

    public function test_payment_reduces_balance(): void
    {
        $this->seed(DatabaseSeeder::class);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $token = (string) $login->json('token');

        $creditor = (string) Branch::query()->value('id');
        $debtor = (string) $this->withToken($token)->postJson('/api/v1/branches', [
            'name' => 'Debtor Branch',
            'is_active' => true,
        ])->json('id');

        $this->withToken($token)->postJson('/api/v1/branch-finance/charges', [
            'creditor_branch_id' => $creditor,
            'debtor_branch_id' => $debtor,
            'amount' => 500,
            'description' => 'Test charge',
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/branch-finance/payments', [
            'creditor_branch_id' => $creditor,
            'debtor_branch_id' => $debtor,
            'amount' => 200,
        ])->assertCreated();

        $balances = $this->withToken($token)->getJson('/api/v1/branch-finance/balances');
        $balances->assertOk()
            ->assertJsonPath('balances.0.balance_owed', 300);
    }
}
