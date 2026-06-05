<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Stock;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePartialReturnReceiptTest extends TestCase
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

    public function test_invoice_list_does_not_error_with_resource_collection(): void
    {
        $this->createInvoiceWithTwoLines();

        $this->withToken($this->token)->getJson('/api/v1/invoices?per_page=50')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_invoice_show_includes_return_quantities_per_line(): void
    {
        [$invoiceId, $partA, $partB, $branchId, $customerId] = $this->createInvoiceWithTwoLines();

        $this->withToken($this->token)->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $invoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'items' => [
                ['part_id' => $partA, 'quantity' => 1, 'unit_price' => 50, 'condition' => 'sellable'],
            ],
        ])->assertCreated();

        $show = $this->withToken($this->token)->getJson("/api/v1/invoices/{$invoiceId}");
        $show->assertOk()
            ->assertJsonPath('return_status', 'partial');

        $items = collect($show->json('items'));
        $lineA = $items->firstWhere('part_id', $partA);
        $lineB = $items->firstWhere('part_id', $partB);

        $this->assertSame(3, $lineA['quantity']);
        $this->assertSame(1, $lineA['quantity_returned_pending']);
        $this->assertSame(2, $lineA['quantity_available_for_return']);
        $this->assertSame(2, $lineB['quantity_available_for_return']);
    }

    public function test_receipt_endpoint_for_reprint_after_partial_return(): void
    {
        [$invoiceId, $partA, $partB, $branchId, $customerId] = $this->createInvoiceWithTwoLines();

        $create = $this->withToken($this->token)->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $invoiceId,
            'reference_type' => 'invoice',
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'items' => [
                ['part_id' => $partA, 'quantity' => 2, 'unit_price' => 50, 'condition' => 'sellable'],
            ],
        ]);
        $create->assertCreated();
        $returnId = (string) $create->json('id');

        $this->withToken($this->token)->patchJson("/api/v1/returns/{$returnId}/approve", [
            'resolution' => 'refund_cash',
        ])->assertOk();

        $receipt = $this->withToken($this->token)->getJson("/api/v1/invoices/{$invoiceId}/receipt");
        $receipt->assertOk()
            ->assertJsonPath('invoice.return_status', 'partial')
            ->assertJsonPath('summary.returned_value_completed', 100)
            ->assertJsonPath('summary.net_total_after_completed_returns', 110);

        $items = collect($receipt->json('items'));
        $lineA = $items->firstWhere('part_id', $partA);
        $this->assertSame(2, $lineA['quantity_returned_completed']);
        $this->assertSame(1, $lineA['quantity_remaining']);
        $this->assertCount(1, $receipt->json('returns'));
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string}
     */
    private function createInvoiceWithTwoLines(): array
    {
        $branch = Branch::query()->firstOrFail();
        $catId = PartCategory::query()->where('key', 'compressor')->value('id');

        $partA = Part::query()->create([
            'code' => 'INV-A-'.uniqid(),
            'name' => 'Part A',
            'category_id' => $catId,
            'unit' => 'pc',
            'sell_price' => 50,
            'cost_price' => 25,
            'min_stock' => 0,
            'is_active' => true,
        ]);
        $partB = Part::query()->create([
            'code' => 'INV-B-'.uniqid(),
            'name' => 'Part B',
            'category_id' => $catId,
            'unit' => 'pc',
            'sell_price' => 30,
            'cost_price' => 15,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        Stock::query()->create(['part_id' => $partA->id, 'branch_id' => $branch->id, 'quantity' => 100]);
        Stock::query()->create(['part_id' => $partB->id, 'branch_id' => $branch->id, 'quantity' => 100]);

        $customer = Customer::query()->create([
            'name' => 'Partial Return Customer',
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
            'items' => [
                ['part_id' => $partA->id, 'quantity' => 3],
                ['part_id' => $partB->id, 'quantity' => 2],
            ],
        ]);
        $invoice->assertCreated();

        return [
            (string) $invoice->json('id'),
            $partA->id,
            $partB->id,
            $branch->id,
            $customer->id,
        ];
    }
}
