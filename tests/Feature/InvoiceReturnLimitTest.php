<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Stock;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceReturnLimitTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $this->token = (string) $login->json('token');
    }

    public function test_cannot_return_more_than_invoice_quantity(): void
    {
        [$invoiceId, $partId, $branchId, $customerId] = $this->createInvoiceWithOneLine(qty: 2);

        $this->withToken($this->token)->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $invoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'items' => [
                ['part_id' => $partId, 'quantity' => 1, 'unit_price' => 50, 'condition' => 'sellable'],
            ],
        ])->assertCreated();

        $this->withToken($this->token)->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $invoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'items' => [
                ['part_id' => $partId, 'quantity' => 2, 'unit_price' => 50, 'condition' => 'sellable'],
            ],
        ])->assertStatus(422)->assertJsonStructure(['failures']);
    }

    public function test_cannot_return_when_invoice_fully_returned(): void
    {
        [$invoiceId, $partId, $branchId, $customerId] = $this->createInvoiceWithOneLine(qty: 2);

        $this->withToken($this->token)->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $invoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'items' => [
                ['part_id' => $partId, 'quantity' => 2, 'unit_price' => 50, 'condition' => 'sellable'],
            ],
        ])->assertCreated();

        $this->withToken($this->token)->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $invoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'items' => [
                ['part_id' => $partId, 'quantity' => 1, 'unit_price' => 50, 'condition' => 'sellable'],
            ],
        ])->assertStatus(422);
    }

    public function test_invoice_marked_returned_after_approved_return(): void
    {
        [$invoiceId, $partId, $branchId, $customerId] = $this->createInvoiceWithOneLine(qty: 1);

        $create = $this->withToken($this->token)->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $invoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'items' => [
                ['part_id' => $partId, 'quantity' => 1, 'unit_price' => 50, 'condition' => 'sellable'],
            ],
        ]);
        $create->assertCreated();
        $returnId = (string) $create->json('id');

        $this->withToken($this->token)->getJson("/api/v1/invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('return_status', 'returned');

        $this->withToken($this->token)->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $invoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'items' => [
                ['part_id' => $partId, 'quantity' => 1, 'unit_price' => 50, 'condition' => 'sellable'],
            ],
        ])->assertStatus(422);
    }

    public function test_rejected_return_frees_invoice_quantity(): void
    {
        [$invoiceId, $partId, $branchId, $customerId] = $this->createInvoiceWithOneLine(qty: 1);

        $create = $this->withToken($this->token)->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $invoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'items' => [
                ['part_id' => $partId, 'quantity' => 1, 'unit_price' => 50, 'condition' => 'sellable'],
            ],
        ]);
        $returnId = (string) $create->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/returns/{$returnId}/reject", [
            'reason' => 'Changed mind',
        ])->assertOk();

        $this->withToken($this->token)->getJson("/api/v1/invoices/{$invoiceId}")
            ->assertJsonPath('return_status', 'none');

        $this->withToken($this->token)->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $invoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'items' => [
                ['part_id' => $partId, 'quantity' => 1, 'unit_price' => 50, 'condition' => 'sellable'],
            ],
        ])->assertCreated();
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function createInvoiceWithOneLine(int $qty): array
    {
        $branch = Branch::query()->firstOrFail();
        $part = Part::query()->create([
            'code' => 'INV-RET-'.uniqid(),
            'name' => 'Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 50,
            'cost_price' => 25,
            'min_stock' => 0,
            'is_active' => true,
        ]);
        Stock::query()->create(['part_id' => $part->id, 'branch_id' => $branch->id, 'quantity' => 100]);

        $customer = Customer::query()->create([
            'name' => 'Ret Customer',
            'type' => 'cash',
            'phone' => null,
            'address' => null,
            'credit_limit' => 0,
            'outstanding_balance' => 0,
            'last_settled_at' => null,
            'is_active' => true,
        ]);

        $invoice = $this->withToken($this->token)->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'payment_type' => 'cash',
            'items' => [['part_id' => $part->id, 'quantity' => $qty]],
        ]);
        $invoice->assertCreated();

        return [(string) $invoice->json('id'), $part->id, $branch->id, $customer->id];
    }
}
