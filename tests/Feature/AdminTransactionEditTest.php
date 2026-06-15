<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CustomerPayment;
use App\Models\StockTransfer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTransactionEditTest extends TestCase
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

    public function test_admin_can_edit_pending_transfer_items(): void
    {
        $fromBranch = Branch::query()->firstOrFail();
        $toBranch = Branch::query()->create(['name' => 'Edit Target', 'is_active' => true]);

        $part = $this->withToken($this->token)->postJson('/api/v1/parts?branch_id='.$fromBranch->id, [
            'code' => 'REG-001',
            'name' => 'Regulator',
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 40,
            'min_stock' => 0,
            'branch_id' => $fromBranch->id,
            'initial_quantity' => 10,
        ]);
        $part->assertCreated();
        $partId = (string) $part->json('id');

        $create = $this->withToken($this->token)->postJson('/api/v1/transfers', [
            'from_branch_id' => $fromBranch->id,
            'to_branch_id' => $toBranch->id,
            'items' => [['part_id' => $partId, 'quantity' => 2]],
        ]);
        $create->assertCreated();
        $transferId = (string) $create->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/transfers/{$transferId}", [
            'items' => [['part_id' => $partId, 'quantity' => 1]],
            'notes' => 'Reduced to one regulator',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('items.0.quantity', 1)
            ->assertJsonPath('notes', 'Reduced to one regulator');
    }

    public function test_completed_transfer_cannot_be_edited(): void
    {
        $fromBranch = Branch::query()->firstOrFail();
        $toBranch = Branch::query()->create(['name' => 'Done Target', 'is_active' => true]);

        $part = $this->withToken($this->token)->postJson('/api/v1/parts?branch_id='.$fromBranch->id, [
            'code' => 'REG-002',
            'name' => 'Regulator 2',
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 40,
            'min_stock' => 0,
            'branch_id' => $fromBranch->id,
            'initial_quantity' => 5,
        ]);
        $partId = (string) $part->json('id');

        $transferId = (string) $this->withToken($this->token)->postJson('/api/v1/transfers', [
            'from_branch_id' => $fromBranch->id,
            'to_branch_id' => $toBranch->id,
            'items' => [['part_id' => $partId, 'quantity' => 1]],
        ])->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/transfers/{$transferId}/complete")->assertOk();

        $this->withToken($this->token)->patchJson("/api/v1/transfers/{$transferId}", [
            'items' => [['part_id' => $partId, 'quantity' => 2]],
        ])->assertStatus(422);

        $this->assertSame('completed', StockTransfer::query()->findOrFail($transferId)->status->value);
    }

    public function test_admin_can_edit_latest_customer_payment_amount(): void
    {
        [$customerId, $invoiceId] = $this->createCreditCustomerWithInvoice(total: 500);

        $payment = $this->withToken($this->token)->postJson("/api/v1/customers/{$customerId}/payments", [
            'payment_method' => 'cash',
            'amount' => 100,
        ]);
        $payment->assertCreated();
        $paymentId = (string) $payment->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/customers/{$customerId}/payments/{$paymentId}", [
            'amount' => 80,
        ])
            ->assertOk()
            ->assertJsonPath('amount', 80);

        $this->withToken($this->token)->getJson("/api/v1/customers/{$customerId}/balance")
            ->assertOk()
            ->assertJsonPath('outstanding_balance', 420);

        $this->assertEquals('80.00', CustomerPayment::query()->findOrFail($paymentId)->amount);
    }

    public function test_only_latest_customer_payment_can_be_edited(): void
    {
        [$customerId] = $this->createCreditCustomerWithInvoice(total: 500);

        $first = $this->withToken($this->token)->postJson("/api/v1/customers/{$customerId}/payments", [
            'payment_method' => 'cash',
            'amount' => 100,
        ])->json('id');

        $this->withToken($this->token)->postJson("/api/v1/customers/{$customerId}/payments", [
            'payment_method' => 'cash',
            'amount' => 50,
        ])->assertCreated();

        $this->withToken($this->token)->patchJson("/api/v1/customers/{$customerId}/payments/{$first}", [
            'amount' => 80,
        ])->assertStatus(422);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createCreditCustomerWithInvoice(float $total): array
    {
        $branch = Branch::query()->firstOrFail();

        $part = $this->withToken($this->token)->postJson('/api/v1/parts?branch_id='.$branch->id, [
            'code' => 'CP-'.uniqid(),
            'name' => 'Credit Part',
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => $total,
            'cost_price' => 50,
            'min_stock' => 0,
            'branch_id' => $branch->id,
            'initial_quantity' => 10,
        ]);
        $partId = (string) $part->json('id');

        $customer = $this->withToken($this->token)->postJson('/api/v1/customers', [
            'name' => 'Credit Customer',
            'type' => 'credit',
            'credit_limit' => 10000,
            'branch_id' => $branch->id,
        ]);
        $customerId = (string) $customer->json('id');

        $invoice = $this->withToken($this->token)->postJson('/api/v1/invoices', [
            'customer_id' => $customerId,
            'branch_id' => $branch->id,
            'payment_type' => 'credit',
            'items' => [['part_id' => $partId, 'quantity' => 1, 'unit_price' => $total]],
        ]);
        $invoice->assertCreated();

        return [$customerId, (string) $invoice->json('id')];
    }
}
