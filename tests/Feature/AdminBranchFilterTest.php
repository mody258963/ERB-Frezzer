<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Stock;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminBranchFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_summary_scopes_to_selected_branch(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);
        Cache::flush();

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $branchA = Branch::query()->firstOrFail();
        $branchB = Branch::query()->create([
            'name' => 'Second Branch',
            'address' => null,
            'phone' => null,
            'is_active' => true,
        ]);

        $part = Part::query()->create([
            'code' => 'BR-FILTER',
            'name' => 'Branch Filter Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 40,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        Stock::query()->create(['part_id' => $part->id, 'branch_id' => $branchA->id, 'quantity' => 5, 'average_cost' => 40]);
        Stock::query()->create(['part_id' => $part->id, 'branch_id' => $branchB->id, 'quantity' => 50, 'average_cost' => 40]);

        $allSummary = $this->withToken($token)->getJson('/api/v1/dashboard/summary')->assertOk()->json();
        $branchBSummary = $this->withToken($token)
            ->getJson('/api/v1/dashboard/summary?branch_id='.$branchB->id)
            ->assertOk()
            ->json();

        $this->assertEquals(2200.0, $allSummary['total_stock_value_cost']);
        $this->assertEquals(2000.0, $branchBSummary['total_stock_value_cost']);
        $this->assertEquals($branchB->id, $branchBSummary['branch_id']);
    }

    public function test_non_admin_cannot_override_branch_filter(): void
    {
        $this->seed(DatabaseSeeder::class);

        $assigned = Branch::query()->firstOrFail();
        $other = Branch::query()->create([
            'name' => 'Other Branch',
            'address' => null,
            'phone' => null,
            'is_active' => true,
        ]);

        User::query()->create([
            'name' => 'Sales',
            'email' => 'sales-branch@example.com',
            'password' => 'password123',
            'role' => UserRole::Salesperson,
            'branch_id' => $assigned->id,
            'is_active' => true,
        ]);

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'sales-branch@example.com',
            'password' => 'password123',
        ])->json('token');

        $summary = $this->withToken($token)
            ->getJson('/api/v1/dashboard/summary?branch_id='.$other->id)
            ->assertOk()
            ->json();

        $this->assertEquals($assigned->id, $summary['branch_id']);
    }
}
