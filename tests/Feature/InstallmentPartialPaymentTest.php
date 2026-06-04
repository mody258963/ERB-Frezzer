<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\SupplierInstallment;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallmentPartialPaymentTest extends TestCase
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

    public function test_partial_payment_reduces_balance_and_keeps_installment_open(): void
    {
        $installmentId = $this->createInstallmentPurchase(amount: 100000, installments: 4);
        $inst = SupplierInstallment::query()->findOrFail($installmentId);

        $this->assertEquals('25000.00', $inst->amount);

        $pay = $this->withToken($this->token)->postJson("/api/v1/installments/{$installmentId}/pay", [
            'payment_method' => 'cash',
            'amount' => 10000,
            'notes' => 'Partial payment',
        ]);

        $pay->assertOk()
            ->assertJsonPath('amount_paid', 10000)
            ->assertJsonPath('balance_due', 15000)
            ->assertJsonPath('is_paid', false);

        $summary = $this->withToken($this->token)->getJson('/api/v1/dashboard/summary');
        $summary->assertOk();
        $this->assertEquals(90000.0, $summary->json('unpaid_installments_total'));
        $this->assertEquals(10000.0, $summary->json('weekly_supplier_payments'));
    }

    public function test_second_payment_completes_installment(): void
    {
        $installmentId = $this->createInstallmentPurchase(amount: 40000, installments: 2);
        $inst = SupplierInstallment::query()->findOrFail($installmentId);
        $this->assertEquals('20000.00', $inst->amount);

        $this->withToken($this->token)->postJson("/api/v1/installments/{$installmentId}/pay", [
            'payment_method' => 'cash',
            'amount' => 5000,
        ])->assertOk();

        $complete = $this->withToken($this->token)->postJson("/api/v1/installments/{$installmentId}/pay", [
            'payment_method' => 'bank_transfer',
            'amount' => 15000,
        ]);

        $complete->assertOk()
            ->assertJsonPath('amount_paid', 20000)
            ->assertJsonPath('balance_due', 0)
            ->assertJsonPath('is_paid', true);
    }

    public function test_pay_without_amount_pays_remaining_balance(): void
    {
        $installmentId = $this->createInstallmentPurchase(amount: 30000, installments: 3);
        $inst = SupplierInstallment::query()->findOrFail($installmentId);

        $this->withToken($this->token)->postJson("/api/v1/installments/{$installmentId}/pay", [
            'payment_method' => 'cash',
            'amount' => 5000,
        ])->assertOk();

        $full = $this->withToken($this->token)->postJson("/api/v1/installments/{$installmentId}/pay", [
            'payment_method' => 'cash',
        ]);

        $full->assertOk()
            ->assertJsonPath('is_paid', true)
            ->assertJsonPath('balance_due', 0);
    }

    public function test_rejects_overpayment(): void
    {
        $installmentId = $this->createInstallmentPurchase(amount: 20000, installments: 2);

        $this->withToken($this->token)->postJson("/api/v1/installments/{$installmentId}/pay", [
            'payment_method' => 'cash',
            'amount' => 15000,
        ])->assertStatus(422);
    }

    private function createInstallmentPurchase(float $amount, int $installments): string
    {
        $branch = Branch::query()->firstOrFail();
        $supplier = Supplier::query()->create([
            'name' => 'Partial Pay Supplier',
            'contact_person' => null,
            'phone' => null,
            'address' => null,
            'total_debt' => 0,
            'is_active' => true,
        ]);

        $part = Part::query()->create([
            'code' => 'PP-'.uniqid(),
            'name' => 'Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 50,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        Stock::query()->create([
            'part_id' => $part->id,
            'branch_id' => $branch->id,
            'quantity' => 1000,
        ]);

        $unitCost = (string) ($amount / 10);
        $qty = 10;

        $po = $this->withToken($this->token)->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'payment_type' => 'installments',
            'installment_count' => $installments,
            'installment_start_date' => now()->toDateString(),
            'items' => [
                ['part_id' => $part->id, 'quantity' => $qty, 'unit_cost' => (float) $unitCost],
            ],
        ]);
        $po->assertCreated();

        return (string) $po->json('installments.0.id');
    }
}
