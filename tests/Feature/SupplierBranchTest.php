<?php

namespace Tests\Feature;

use App\Models\Branch;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierBranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_supplier_tags_branch(): void
    {
        $this->seed(DatabaseSeeder::class);

        $branch = Branch::query()->firstOrFail();

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $create = $this->withToken($token)->postJson('/api/v1/suppliers?branch_id='.$branch->id, [
            'name' => 'Branch Supplier',
            'phone' => '01000000001',
        ])->assertCreated();

        $this->assertSame($branch->id, $create->json('branch_id'));
    }

    public function test_suppliers_list_scopes_to_branch(): void
    {
        $this->seed(DatabaseSeeder::class);

        $branchA = Branch::query()->firstOrFail();
        $branchB = Branch::query()->create([
            'name' => 'Supplier Branch B',
            'address' => null,
            'phone' => null,
            'is_active' => true,
        ]);

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $supplierA = $this->withToken($token)->postJson('/api/v1/suppliers?branch_id='.$branchA->id, [
            'name' => 'Supplier A',
        ])->assertCreated()->json('id');

        $supplierB = $this->withToken($token)->postJson('/api/v1/suppliers?branch_id='.$branchB->id, [
            'name' => 'Supplier B',
        ])->assertCreated()->json('id');

        $response = $this->withToken($token)
            ->getJson('/api/v1/suppliers?branch_id='.$branchA->id)
            ->assertOk();

        $ids = collect($response->json('data') ?? $response->json())->pluck('id')->all();

        $this->assertContains($supplierA, $ids);
        $this->assertNotContains($supplierB, $ids);
    }

    public function test_create_supplier_without_branch_returns_validation_error(): void
    {
        $this->seed(DatabaseSeeder::class);

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $this->withToken($token)->postJson('/api/v1/suppliers', [
            'name' => 'No Branch Supplier',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);
    }
}
