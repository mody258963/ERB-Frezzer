<?php

namespace Tests\Feature;

use App\Models\Branch;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Happy-path sweep of every api/v1 route (see php artisan route:list --path=api).
 *
 * PHPUnit uses sqlite :memory: (phpunit.xml), not your MySQL DATABASE from .env.
 *
 * Run: php artisan test tests/Feature/ApiV1EndpointsSmokeTest.php
 */
#[Group('smoke')]
class ApiV1EndpointsSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_registered_v1_routes_via_single_admin_flow(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->postJson('/api/v1/auth/login', ['email' => 'nope@example.com', 'password' => 'wrong'])
            ->assertStatus(422);

        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('status', 'ok');

        $this->getJson('/api/v1/branches')->assertUnauthorized();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $login->assertOk();
        $token = (string) $login->json('token');
        $this->assertNotSame('', $token);
        $this->assertDatabaseCount('oauth_access_tokens', 1);

        $auth = fn () => $this->withToken($token);

        $auth()->getJson('/api/v1/auth/me')->assertOk();

        /** @var non-empty-string $mainBranchId */
        $mainBranchId = (string) Branch::query()->value('id');

        $auth()->getJson('/api/v1/branches')->assertOk();
        $auth()->getJson("/api/v1/branches/{$mainBranchId}")->assertOk();

        $warehouse = $auth()->postJson('/api/v1/branches', [
            'name' => 'Smoke Warehouse',
            'phone' => null,
            'address' => null,
            'is_active' => true,
        ]);
        $warehouse->assertCreated();
        $warehouseBranchId = (string) $warehouse->json('id');

        $auth()->putJson("/api/v1/branches/{$warehouseBranchId}", [
            'name' => 'Smoke Warehouse Renamed',
        ])->assertOk();

        $primaryPart = $auth()->postJson('/api/v1/parts', [
            'code' => 'SMK-P1',
            'name' => 'Smoke primary part',
            'category' => 'Compressor',
            'unit' => 'pc',
            'sell_price' => 49.99,
            'cost_price' => 20,
            'min_stock' => 0,
            'is_active' => true,
        ]);
        $primaryPart->assertCreated();
        $partId = (string) $primaryPart->json('id');

        $discardPart = $auth()->postJson('/api/v1/parts', [
            'code' => 'SMK-DEL',
            'name' => 'Delete-only part',
            'category' => 'Seals',
            'unit' => 'pc',
            'sell_price' => 1,
            'cost_price' => 1,
            'min_stock' => 0,
            'is_active' => true,
        ]);
        $discardPart->assertCreated();
        $discardPartId = (string) $discardPart->json('id');

        $auth()->getJson('/api/v1/parts')->assertOk();
        $auth()->getJson("/api/v1/parts/{$partId}")->assertOk();
        $auth()->getJson("/api/v1/parts/{$partId}/analysis")->assertOk();

        $auth()->putJson("/api/v1/parts/{$discardPartId}", [
            'name' => 'Rename before delete',
            'sell_price' => 2,
            'cost_price' => 1,
            'min_stock' => 0,
            'is_active' => true,
        ])->assertOk();

        $auth()->deleteJson("/api/v1/parts/{$discardPartId}")->assertNoContent();

        $auth()->postJson('/api/v1/inventory/adjust', [
            'part_id' => $partId,
            'branch_id' => $mainBranchId,
            'quantity_delta' => 100,
            'reason' => 'smoke fixture',
        ])->assertOk();

        $auth()->postJson('/api/v1/inventory/adjust', [
            'part_id' => $partId,
            'branch_id' => $warehouseBranchId,
            'quantity_delta' => 20,
        ])->assertOk();

        $auth()->getJson('/api/v1/inventory')->assertOk();
        $auth()->getJson('/api/v1/inventory/low-stock')->assertOk();
        $auth()->getJson("/api/v1/inventory/{$mainBranchId}")->assertOk();

        $customerCashResponse = $auth()->postJson('/api/v1/customers', [
            'name' => 'Smoke Cash',
            'type' => 'cash',
        ]);
        $customerCashResponse->assertCreated();
        $customerCashId = (string) $customerCashResponse->json('id');

        $customerCreditResponse = $auth()->postJson('/api/v1/customers', [
            'name' => 'Smoke Credit',
            'type' => 'credit',
            'credit_limit' => 500000,
        ]);
        $customerCreditResponse->assertCreated();
        $customerCreditId = (string) $customerCreditResponse->json('id');

        $auth()->getJson('/api/v1/customers')->assertOk();
        $auth()->getJson("/api/v1/customers/{$customerCashId}")->assertOk();
        $auth()->getJson("/api/v1/customers/{$customerCreditId}/invoices")->assertOk();
        $auth()->putJson("/api/v1/customers/{$customerCashId}", ['name' => 'Smoke Cash Renamed'])->assertOk();

        $invoiceCashOneResponse = $auth()->postJson('/api/v1/invoices', [
            'customer_id' => $customerCashId,
            'branch_id' => $mainBranchId,
            'payment_type' => 'cash',
            'items' => [
                ['part_id' => $partId, 'quantity' => 2],
            ],
        ]);
        $invoiceCashOneResponse->assertCreated();

        $invoiceToCancelResponse = $auth()->postJson('/api/v1/invoices', [
            'customer_id' => $customerCashId,
            'branch_id' => $mainBranchId,
            'payment_type' => 'cash',
            'items' => [
                ['part_id' => $partId, 'quantity' => 1],
            ],
        ]);
        $invoiceToCancelResponse->assertCreated();

        $auth()->getJson('/api/v1/invoices')->assertOk();
        $auth()->getJson('/api/v1/invoices/pending-credit')->assertOk();

        $invoiceCreditResponse = $auth()->postJson('/api/v1/invoices', [
            'customer_id' => $customerCreditId,
            'branch_id' => $mainBranchId,
            'payment_type' => 'credit',
            'discount' => 0,
            'items' => [
                ['part_id' => $partId, 'quantity' => 1],
            ],
        ]);
        $invoiceCreditResponse->assertCreated();
        $invoiceCreditId = (string) $invoiceCreditResponse->json('id');

        $auth()->getJson("/api/v1/invoices/{$invoiceCreditId}")->assertOk();
        $auth()->getJson("/api/v1/customers/{$customerCreditId}/balance")->assertOk();

        $transferResp = $auth()->postJson('/api/v1/transfers', [
            'from_branch_id' => $mainBranchId,
            'to_branch_id' => $warehouseBranchId,
            'notes' => 'smoke transfer',
            'items' => [
                ['part_id' => $partId, 'quantity' => 3],
            ],
        ]);
        $transferResp->assertCreated();
        $transferId = (string) $transferResp->json('id');

        $auth()->getJson('/api/v1/transfers')->assertOk();
        $auth()->getJson("/api/v1/transfers/{$transferId}")->assertOk();

        $transferCancelResp = $auth()->postJson('/api/v1/transfers', [
            'from_branch_id' => $warehouseBranchId,
            'to_branch_id' => $mainBranchId,
            'items' => [
                ['part_id' => $partId, 'quantity' => 1],
            ],
        ]);
        $transferCancelResp->assertCreated();
        $transferCancelId = (string) $transferCancelResp->json('id');

        $auth()->patchJson("/api/v1/transfers/{$transferCancelId}/cancel")->assertOk();
        $auth()->patchJson("/api/v1/transfers/{$transferId}/complete")->assertOk();

        $supplierResp = $auth()->postJson('/api/v1/suppliers', [
            'name' => 'Smoke Supplier',
            'contact_person' => null,
            'phone' => null,
            'address' => null,
        ]);
        $supplierResp->assertCreated();
        $supplierId = (string) $supplierResp->json('id');

        $auth()->getJson('/api/v1/suppliers')->assertOk();
        $auth()->getJson("/api/v1/suppliers/{$supplierId}")->assertOk();
        $auth()->getJson("/api/v1/suppliers/{$supplierId}/debt")->assertOk();

        $purchaseKeepResp = $auth()->postJson('/api/v1/purchases', [
            'supplier_id' => $supplierId,
            'branch_id' => $mainBranchId,
            'payment_type' => 'immediate',
            'items' => [
                ['part_id' => $partId, 'quantity' => 2, 'unit_cost' => 15],
            ],
        ]);
        $purchaseKeepResp->assertCreated();
        $purchaseKeepId = (string) $purchaseKeepResp->json('id');

        $purchaseCancelResp = $auth()->postJson('/api/v1/purchases', [
            'supplier_id' => $supplierId,
            'branch_id' => $warehouseBranchId,
            'payment_type' => 'immediate',
            'items' => [
                ['part_id' => $partId, 'quantity' => 1, 'unit_cost' => 5],
            ],
        ]);
        $purchaseCancelResp->assertCreated();
        $purchaseCancelId = (string) $purchaseCancelResp->json('id');

        $auth()->getJson('/api/v1/purchases')->assertOk();
        $auth()->getJson("/api/v1/purchases/{$purchaseKeepId}")->assertOk();
        $auth()->patchJson("/api/v1/purchases/{$purchaseCancelId}/cancel")->assertOk();
        $auth()->patchJson("/api/v1/purchases/{$purchaseKeepId}/receive")->assertOk();

        $installmentId = (string) $purchaseKeepResp->json('installments.0.id');
        $this->assertNotSame('', $installmentId);

        $auth()->getJson('/api/v1/installments')->assertOk();
        $auth()->getJson('/api/v1/installments/overdue')->assertOk();
        $auth()->postJson("/api/v1/installments/{$installmentId}/pay", [
            'payment_method' => 'cash',
        ])->assertOk();

        $settlementResp = $auth()->postJson('/api/v1/settlements', [
            'customer_id' => $customerCreditId,
            'settlement_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);
        $settlementResp->assertCreated();
        $settlementId = (string) $settlementResp->json('id');

        $auth()->getJson('/api/v1/settlements')->assertOk();
        $auth()->getJson('/api/v1/settlements/upcoming')->assertOk();
        $auth()->getJson("/api/v1/settlements/{$settlementId}")->assertOk();

        $returnRejectResp = $auth()->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $invoiceCashOneResponse->json('id'),
            'reference_type' => 'invoice',
            'customer_id' => $customerCashId,
            'branch_id' => $mainBranchId,
            'reason' => 'smoke reject path',
            'items' => [
                ['part_id' => $partId, 'quantity' => 1, 'unit_price' => 10, 'condition' => 'sellable'],
            ],
        ]);
        $returnRejectResp->assertCreated();
        $returnRejectId = (string) $returnRejectResp->json('id');

        $auth()->getJson('/api/v1/returns')->assertOk();
        $auth()->getJson("/api/v1/returns/{$returnRejectId}")->assertOk();
        $auth()->patchJson("/api/v1/returns/{$returnRejectId}/reject", ['reason' => 'smoke'])->assertOk();

        $invoiceForRestockResp = $auth()->postJson('/api/v1/invoices', [
            'customer_id' => $customerCashId,
            'branch_id' => $mainBranchId,
            'payment_type' => 'cash',
            'items' => [
                ['part_id' => $partId, 'quantity' => 1],
            ],
        ]);
        $invoiceForRestockResp->assertCreated();

        $returnApproveResp = $auth()->postJson('/api/v1/returns', [
            'return_type' => 'customer_return',
            'reference_id' => $invoiceForRestockResp->json('id'),
            'reference_type' => 'invoice',
            'customer_id' => $customerCashId,
            'branch_id' => $mainBranchId,
            'items' => [
                ['part_id' => $partId, 'quantity' => 1, 'unit_price' => 1, 'condition' => 'sellable'],
            ],
        ]);
        $returnApproveResp->assertCreated();
        $returnApproveId = (string) $returnApproveResp->json('id');
        $auth()->patchJson("/api/v1/returns/{$returnApproveId}/approve", ['resolution' => 'restock'])->assertOk();

        $auth()->patchJson('/api/v1/invoices/'.$invoiceToCancelResponse->json('id').'/cancel')->assertOk();

        $auth()->getJson('/api/v1/dashboard/summary')->assertOk();
        $auth()->getJson('/api/v1/dashboard/inventory')->assertOk();
        $auth()->getJson('/api/v1/dashboard/receivables')->assertOk();
        $auth()->getJson('/api/v1/dashboard/payables')->assertOk();
        $auth()->getJson('/api/v1/dashboard/sales')->assertOk();
        $auth()->getJson('/api/v1/dashboard/activity')->assertOk();

        $auth()->getJson('/api/v1/reports/sales')->assertOk();
        $auth()->getJson('/api/v1/reports/inventory')->assertOk();
        $auth()->getJson('/api/v1/reports/customers')->assertOk();
        $auth()->getJson('/api/v1/reports/suppliers')->assertOk();
        $auth()->getJson('/api/v1/reports/returns')->assertOk();
        $auth()->getJson('/api/v1/reports/parts-sales-chart?year='.now()->year)->assertOk();
        $auth()->getJson('/api/v1/branch-finance/balances')->assertOk();
        $auth()->getJson('/api/v1/branch-finance/entries')->assertOk();

        $auth()->putJson("/api/v1/suppliers/{$supplierId}", ['name' => 'Smoke Supplier Ltd'])->assertOk();

        $auth()->deleteJson("/api/v1/customers/{$customerCashId}")->assertNoContent();
        $auth()->deleteJson("/api/v1/suppliers/{$supplierId}")->assertNoContent();

        $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->assertOk();

        $this->assertDatabaseCount('oauth_access_tokens', 1);
        $this->assertTrue(
            (bool) DB::table('oauth_access_tokens')->value('revoked'),
            'Access token should be revoked after logout.'
        );
    }
}
