<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchFinancialEntry;
use App\Models\BranchFinancialPaymentAllocation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchFinanceAdminEditTest extends TestCase
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

    public function test_admin_can_edit_open_charge_amount(): void
    {
        [$creditor, $debtor] = $this->branchPair();

        $entryId = (string) $this->withToken($this->token)->postJson('/api/v1/branch-finance/charges', [
            'creditor_branch_id' => $creditor,
            'debtor_branch_id' => $debtor,
            'amount' => 300,
            'description' => 'Original',
        ])->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/branch-finance/entries/{$entryId}", [
            'amount' => 450,
            'description' => 'Updated charge',
        ])
            ->assertOk()
            ->assertJsonPath('amount', 450)
            ->assertJsonPath('description', 'Updated charge');

        $this->assertBalanceOwed(450);
    }

    public function test_payment_records_allocations_and_edit_reapplies_fifo(): void
    {
        [$creditor, $debtor] = $this->branchPair();

        $chargeA = (string) $this->withToken($this->token)->postJson('/api/v1/branch-finance/charges', [
            'creditor_branch_id' => $creditor,
            'debtor_branch_id' => $debtor,
            'amount' => 200,
        ])->json('id');

        $this->withToken($this->token)->postJson('/api/v1/branch-finance/charges', [
            'creditor_branch_id' => $creditor,
            'debtor_branch_id' => $debtor,
            'amount' => 300,
        ])->assertCreated();

        $paymentId = (string) $this->withToken($this->token)->postJson('/api/v1/branch-finance/payments', [
            'creditor_branch_id' => $creditor,
            'debtor_branch_id' => $debtor,
            'amount' => 250,
        ])->json('id');

        $this->assertDatabaseHas('branch_financial_payment_allocations', [
            'payment_entry_id' => $paymentId,
            'charge_entry_id' => $chargeA,
            'amount' => '200.00',
        ]);

        $this->assertBalanceOwed(250);

        $this->withToken($this->token)->patchJson("/api/v1/branch-finance/entries/{$paymentId}", [
            'amount' => 150,
        ])->assertOk()->assertJsonPath('amount', 150);

        $this->assertBalanceOwed(350);
        $this->assertEquals('open', BranchFinancialEntry::query()->findOrFail($chargeA)->status->value);
    }

    public function test_void_payment_reopens_charges(): void
    {
        [$creditor, $debtor] = $this->branchPair();

        $chargeId = (string) $this->withToken($this->token)->postJson('/api/v1/branch-finance/charges', [
            'creditor_branch_id' => $creditor,
            'debtor_branch_id' => $debtor,
            'amount' => 500,
        ])->json('id');

        $paymentId = (string) $this->withToken($this->token)->postJson('/api/v1/branch-finance/payments', [
            'creditor_branch_id' => $creditor,
            'debtor_branch_id' => $debtor,
            'amount' => 500,
        ])->json('id');

        $this->assertEquals('settled', BranchFinancialEntry::query()->findOrFail($chargeId)->status->value);
        $this->assertBalanceOwed(0);

        $this->withToken($this->token)->deleteJson("/api/v1/branch-finance/entries/{$paymentId}")
            ->assertNoContent();

        $this->assertEquals(0, BranchFinancialPaymentAllocation::query()->where('payment_entry_id', $paymentId)->count());
        $this->assertEquals('open', BranchFinancialEntry::query()->findOrFail($chargeId)->status->value);
        $this->assertBalanceOwed(500);
    }

    public function test_transfer_linked_charge_cannot_be_voided_directly(): void
    {
        $from = (string) Branch::query()->value('id');
        $to = (string) $this->withToken($this->token)->postJson('/api/v1/branches', [
            'name' => 'Finance Void Block',
            'is_active' => true,
        ])->json('id');

        $partId = (string) $this->withToken($this->token)->postJson('/api/v1/parts?branch_id='.$from, [
            'code' => 'BF-VOID',
            'name' => 'Void Block Part',
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 40,
            'min_stock' => 0,
            'branch_id' => $from,
            'initial_quantity' => 5,
        ])->json('id');

        $transferId = (string) $this->withToken($this->token)->postJson('/api/v1/transfers', [
            'from_branch_id' => $from,
            'to_branch_id' => $to,
            'items' => [['part_id' => $partId, 'quantity' => 2]],
        ])->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/transfers/{$transferId}/complete")->assertOk();

        $entryId = (string) BranchFinancialEntry::query()->where('reference_id', $transferId)->value('id');

        $this->withToken($this->token)->deleteJson("/api/v1/branch-finance/entries/{$entryId}")
            ->assertStatus(422);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function branchPair(): array
    {
        $creditor = (string) Branch::query()->value('id');
        $debtor = (string) $this->withToken($this->token)->postJson('/api/v1/branches', [
            'name' => 'Debtor '.uniqid(),
            'is_active' => true,
        ])->json('id');

        return [$creditor, $debtor];
    }

    private function assertBalanceOwed(float $expected): void
    {
        $balances = $this->withToken($this->token)->getJson('/api/v1/branch-finance/balances')
            ->assertOk()
            ->json('balances');

        $this->assertEquals($expected, (float) ($balances[0]['balance_owed'] ?? 0));
    }
}
