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

class CustomerPartialPaymentTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);
        $this->token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');
    }

    public function test_partial_payment_reduces_customer_balance(): void
    {
        [$customerId, $invoiceId] = $this->createCreditCustomerWithInvoice(total: 1000);

        $pay = $this->withToken($this->token)->postJson("/api/v1/customers/{$customerId}/payments", [
            'payment_method' => 'cash',
            'amount' => 300,
            'notes' => 'Partial collection',
        ]);

        $pay->assertCreated()->assertJsonPath('amount', 300);

        $this->withToken($this->token)->getJson("/api/v1/customers/{$customerId}/balance")
            ->assertOk()
            ->assertJsonPath('outstanding_balance', 700);

        $invoice = Invoice::query()->findOrFail($invoiceId);
        $this->assertEquals('300.00', $invoice->amount_paid);
        $this->assertFalse($invoice->is_paid);
    }

    public function test_second_payment_completes_invoice(): void
    {
        [$customerId, $invoiceId] = $this->createCreditCustomerWithInvoice(total: 500);

        $this->withToken($this->token)->postJson("/api/v1/customers/{$customerId}/payments", [
            'payment_method' => 'cash',
            'amount' => 200,
        ])->assertCreated();

        $this->withToken($this->token)->postJson("/api/v1/customers/{$customerId}/payments", [
            'payment_method' => 'cash',
            'amount' => 300,
        ])->assertCreated();

        $invoice = Invoice::query()->findOrFail($invoiceId);
        $this->assertTrue($invoice->is_paid);
        $this->assertEquals('500.00', $invoice->amount_paid);

        $this->withToken($this->token)->getJson("/api/v1/customers/{$customerId}/balance")
            ->assertOk()
            ->assertJsonPath('outstanding_balance', 0);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createCreditCustomerWithInvoice(float $total): array
    {
        $branch = Branch::query()->firstOrFail();
        $part = Part::query()->create([
            'code' => 'CP-'.uniqid(),
            'name' => 'Credit Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => $total,
            'cost_price' => 50,
            'min_stock' => 0,
            'is_active' => true,
        ]);
        Stock::query()->create(['part_id' => $part->id, 'branch_id' => $branch->id, 'quantity' => 100]);

        $customer = Customer::query()->create([
            'name' => 'Credit Customer',
            'type' => 'credit',
            'phone' => null,
            'address' => null,
            'credit_limit' => 10000,
            'outstanding_balance' => 0,
            'last_settled_at' => null,
            'is_active' => true,
        ]);

        $invoice = $this->withToken($this->token)->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'payment_type' => 'credit',
            'items' => [['part_id' => $part->id, 'quantity' => 1, 'unit_price' => $total]],
        ]);
        $invoice->assertCreated();

        return [$customer->id, (string) $invoice->json('id')];
    }
}
