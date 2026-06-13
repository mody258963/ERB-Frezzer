<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Stock;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartBranchWarehouseTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_part_tags_branch_and_creates_warehouse_stock_row(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);

        $branch = Branch::query()->firstOrFail();

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $create = $this->withToken($token)->postJson('/api/v1/parts?branch_id='.$branch->id, [
            'code' => 'BR-PART-1',
            'name' => 'Branch Part',
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 50,
            'min_stock' => 5,
            'initial_quantity' => 10,
        ])->assertCreated();

        $partId = (string) $create->json('id');
        $this->assertSame($branch->id, $create->json('branch_id'));

        $this->assertDatabaseHas('stock', [
            'part_id' => $partId,
            'branch_id' => $branch->id,
            'quantity' => 10,
        ]);
    }

    public function test_parts_list_scopes_to_branch(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);

        $branchA = Branch::query()->firstOrFail();
        $branchB = Branch::query()->create([
            'name' => 'Warehouse B',
            'address' => null,
            'phone' => null,
            'is_active' => true,
        ]);

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $partA = $this->withToken($token)->postJson('/api/v1/parts?branch_id='.$branchA->id, [
            'code' => 'A-001',
            'name' => 'Part A',
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 50,
            'min_stock' => 0,
        ])->assertCreated()->json('id');

        $partB = $this->withToken($token)->postJson('/api/v1/parts?branch_id='.$branchB->id, [
            'code' => 'B-001',
            'name' => 'Part B',
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 50,
            'min_stock' => 0,
        ])->assertCreated()->json('id');

        $response = $this->withToken($token)
            ->getJson('/api/v1/parts?branch_id='.$branchA->id)
            ->assertOk();

        $branchAList = $response->json('data') ?? $response->json();

        $branchAIds = collect($branchAList)->pluck('id')->all();
        $this->assertContains($partA, $branchAIds);
        $this->assertNotContains($partB, $branchAIds);
        foreach ($branchAList as $row) {
            $this->assertSame($branchA->id, $row['branch_id']);
        }
    }

    public function test_same_part_code_allowed_in_different_branches(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);

        $branchA = Branch::query()->firstOrFail();
        $branchB = Branch::query()->create([
            'name' => 'Other Branch',
            'address' => null,
            'phone' => null,
            'is_active' => true,
        ]);

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $payload = [
            'code' => 'SHARED-CODE',
            'name' => 'Shared Code Part',
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 50,
            'min_stock' => 0,
        ];

        $this->withToken($token)->postJson('/api/v1/parts?branch_id='.$branchA->id, $payload)->assertCreated();
        $this->withToken($token)->postJson('/api/v1/parts?branch_id='.$branchB->id, $payload)->assertCreated();
    }

    public function test_create_part_without_branch_returns_validation_error(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $this->withToken($token)->postJson('/api/v1/parts', [
            'code' => 'NO-BRANCH',
            'name' => 'Missing Branch',
            'category_key' => 'compressor',
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 50,
            'min_stock' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);
    }
}
