<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchFinancialEntry;
use App\Models\Stock;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferDecimalTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_accepts_multiple_decimal_lines_with_unit_cost(): void
    {
        $this->seed(DatabaseSeeder::class);

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $fromBranch = Branch::query()->firstOrFail();
        $toBranch = Branch::query()->create([
            'name' => 'Transfer Target',
            'is_active' => true,
        ]);

        $cable = $this->withToken($token)->postJson('/api/v1/parts?branch_id='.$fromBranch->id, [
            'code' => 'CBL-001',
            'name' => 'Cable',
            'category_key' => 'compressor',
            'unit' => 'm',
            'sell_price' => 200,
            'cost_price' => 80,
            'min_stock' => 0,
            'branch_id' => $fromBranch->id,
            'initial_quantity' => 20,
        ]);
        $cable->assertCreated();
        $cableId = (string) $cable->json('id');

        $wire = $this->withToken($token)->postJson('/api/v1/parts?branch_id='.$fromBranch->id, [
            'code' => 'WIR-001',
            'name' => 'Wire',
            'category_key' => 'compressor',
            'unit' => 'm',
            'sell_price' => 100,
            'cost_price' => 40,
            'min_stock' => 0,
            'branch_id' => $fromBranch->id,
            'initial_quantity' => 10,
        ]);
        $wire->assertCreated();
        $wireId = (string) $wire->json('id');

        $transfer = $this->withToken($token)->postJson('/api/v1/transfers', [
            'from_branch_id' => $fromBranch->id,
            'to_branch_id' => $toBranch->id,
            'items' => [
                ['part_id' => $cableId, 'quantity' => 2.5, 'unit_cost' => 150],
                ['part_id' => $wireId, 'quantity' => 1.25, 'unit_cost' => 60],
            ],
        ]);

        $transfer->assertCreated();
        $transfer->assertJsonCount(2, 'items');
        $transfer->assertJsonPath('items.0.quantity', 2.5);
        $transfer->assertJsonPath('items.0.unit_cost', 150);
        $transfer->assertJsonPath('items.1.quantity', 1.25);
        $transfer->assertJsonPath('items.1.unit_cost', 60);

        $transferId = (string) $transfer->json('id');

        $this->withToken($token)->patchJson("/api/v1/transfers/{$transferId}/complete", [
            'valuation' => 'cost',
        ])->assertOk();

        $this->assertEquals(17.5, (float) Stock::query()->where('part_id', $cableId)->where('branch_id', $fromBranch->id)->value('quantity'));
        $this->assertEquals(2.5, (float) Stock::query()->where('part_id', $cableId)->where('branch_id', $toBranch->id)->value('quantity'));
        $this->assertEquals(8.75, (float) Stock::query()->where('part_id', $wireId)->where('branch_id', $fromBranch->id)->value('quantity'));
        $this->assertEquals(1.25, (float) Stock::query()->where('part_id', $wireId)->where('branch_id', $toBranch->id)->value('quantity'));

        $entry = BranchFinancialEntry::query()->where('reference_id', $transferId)->first();
        $this->assertNotNull($entry);
        $this->assertEquals(450.0, (float) $entry->amount);
    }
}
