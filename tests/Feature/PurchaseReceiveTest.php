<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Supplier;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReceiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_receive_rejects_duplicate_receive(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $branch = Branch::query()->firstOrFail();
        $supplier = Supplier::query()->create([
            'name' => 'S',
            'contact_person' => null,
            'phone' => null,
            'address' => null,
            'total_debt' => 0,
            'is_active' => true,
        ]);
        $part = Part::query()->create([
            'code' => 'PO-RX',
            'name' => 'PO Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 10,
            'cost_price' => 5,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        $po = $this->withToken($token)->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'payment_type' => 'immediate',
            'items' => [['part_id' => $part->id, 'quantity' => 5, 'unit_cost' => 10]],
        ]);
        $po->assertCreated();
        $poId = (string) $po->json('id');

        $this->withToken($token)->patchJson("/api/v1/purchases/{$poId}/receive")->assertOk();
        $this->withToken($token)->postJson("/api/v1/purchases/{$poId}/receive", [
            'branch_id' => $branch->id,
        ])->assertStatus(422);
    }

    public function test_post_receive_route_is_supported(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $branch = Branch::query()->firstOrFail();
        $supplier = Supplier::query()->create([
            'name' => 'S2',
            'contact_person' => null,
            'phone' => null,
            'address' => null,
            'total_debt' => 0,
            'is_active' => true,
        ]);
        $part = Part::query()->create([
            'code' => 'PO-POST',
            'name' => 'PO Part 2',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 10,
            'cost_price' => 5,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        $poId = (string) $this->withToken($token)->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'payment_type' => 'immediate',
            'items' => [['part_id' => $part->id, 'quantity' => 1, 'unit_cost' => 10]],
        ])->json('id');

        $this->withToken($token)->postJson("/api/v1/purchases/{$poId}/receive")->assertOk();
    }
}
