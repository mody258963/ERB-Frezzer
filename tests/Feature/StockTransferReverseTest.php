<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchFinancialEntry;
use App\Models\Stock;
use App\Models\StockTransfer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferReverseTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');
    }

    public function test_admin_can_reverse_completed_transfer_and_void_charge(): void
    {
        $from = Branch::query()->firstOrFail();
        $to = Branch::query()->create(['name' => 'Reverse Target', 'is_active' => true]);

        $part = $this->withToken($this->token)->postJson('/api/v1/parts?branch_id='.$from->id, [
            'code' => 'REV-001',
            'name' => 'Reverse Part',
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 40,
            'min_stock' => 0,
            'branch_id' => $from->id,
            'initial_quantity' => 10,
        ]);
        $partId = (string) $part->json('id');

        $stockBefore = $this->stockQty($partId, $from->id);
        $destBefore = $this->stockQty($partId, $to->id);

        $transferId = (string) $this->withToken($this->token)->postJson('/api/v1/transfers', [
            'from_branch_id' => $from->id,
            'to_branch_id' => $to->id,
            'items' => [['part_id' => $partId, 'quantity' => 3]],
        ])->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/transfers/{$transferId}/complete")->assertOk();

        $this->assertEquals($stockBefore - 3, $this->stockQty($partId, $from->id));
        $this->assertEquals($destBefore + 3, $this->stockQty($partId, $to->id));

        $charge = BranchFinancialEntry::query()->where('reference_id', $transferId)->firstOrFail();
        $this->assertNull($charge->voided_at);

        $this->withToken($this->token)->patchJson("/api/v1/transfers/{$transferId}/reverse")
            ->assertOk()
            ->assertJsonPath('status', 'reversed');

        $this->assertEquals('reversed', StockTransfer::query()->findOrFail($transferId)->status->value);
        $this->assertEquals($stockBefore, $this->stockQty($partId, $from->id));
        $this->assertEquals($destBefore, $this->stockQty($partId, $to->id));
        $this->assertNotNull($charge->fresh()->voided_at);
    }

    public function test_pending_transfer_cannot_be_reversed(): void
    {
        $from = Branch::query()->firstOrFail();
        $to = Branch::query()->create(['name' => 'Pending Reverse', 'is_active' => true]);

        $partId = (string) $this->withToken($this->token)->postJson('/api/v1/parts?branch_id='.$from->id, [
            'code' => 'REV-002',
            'name' => 'Pending Part',
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => 50,
            'cost_price' => 20,
            'min_stock' => 0,
            'branch_id' => $from->id,
            'initial_quantity' => 5,
        ])->json('id');

        $transferId = (string) $this->withToken($this->token)->postJson('/api/v1/transfers', [
            'from_branch_id' => $from->id,
            'to_branch_id' => $to->id,
            'items' => [['part_id' => $partId, 'quantity' => 1]],
        ])->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/transfers/{$transferId}/reverse")
            ->assertStatus(422);
    }

    public function test_reverse_fails_when_destination_stock_was_consumed(): void
    {
        $from = Branch::query()->firstOrFail();
        $to = Branch::query()->create(['name' => 'Sold At Dest', 'is_active' => true]);

        $partId = (string) $this->withToken($this->token)->postJson('/api/v1/parts?branch_id='.$from->id, [
            'code' => 'REV-003',
            'name' => 'Consumed Part',
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => 80,
            'cost_price' => 30,
            'min_stock' => 0,
            'branch_id' => $from->id,
            'initial_quantity' => 10,
        ])->json('id');

        $transferId = (string) $this->withToken($this->token)->postJson('/api/v1/transfers', [
            'from_branch_id' => $from->id,
            'to_branch_id' => $to->id,
            'items' => [['part_id' => $partId, 'quantity' => 4]],
        ])->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/transfers/{$transferId}/complete")->assertOk();

        $this->withToken($this->token)->postJson('/api/v1/inventory/adjust', [
            'part_id' => $partId,
            'branch_id' => $to->id,
            'quantity_delta' => -4,
            'reason' => 'Sold at destination',
        ])->assertOk();

        $this->withToken($this->token)->patchJson("/api/v1/transfers/{$transferId}/reverse")
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'Cannot reverse transfer'));
    }

    private function stockQty(string $partId, string $branchId): float
    {
        return (float) (Stock::query()
            ->where('part_id', $partId)
            ->where('branch_id', $branchId)
            ->value('quantity') ?? 0);
    }
}
