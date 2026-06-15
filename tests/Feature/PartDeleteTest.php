<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Stock;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PartCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_hides_part_from_catalog_list(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $branch = Branch::query()->firstOrFail();
        $part = Part::query()->create([
            'code' => 'DEL-001',
            'name' => 'To Delete',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 40,
            'min_stock' => 0,
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);

        $this->withToken($token)->getJson('/api/v1/parts?branch_id='.$branch->id)
            ->assertOk()
            ->assertJsonFragment(['code' => 'DEL-001']);

        $this->withToken($token)->deleteJson('/api/v1/parts/'.$part->id)
            ->assertNoContent();

        $this->assertFalse($part->fresh()->is_active);

        $list = $this->withToken($token)->getJson('/api/v1/parts?branch_id='.$branch->id);
        $list->assertOk();
        $codes = collect($list->json('data'))->pluck('code')->all();
        $this->assertNotContains('DEL-001', $codes);

        $this->withToken($token)->getJson('/api/v1/parts?branch_id='.$branch->id.'&include_inactive=1')
            ->assertOk()
            ->assertJsonFragment(['code' => 'DEL-001']);
    }

    public function test_delete_excludes_part_from_inventory_totals(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(PartCategorySeeder::class);

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $branch = Branch::query()->firstOrFail();
        $part = Part::query()->create([
            'code' => 'DEL-VAL',
            'name' => 'Valued Part',
            'category_id' => PartCategory::query()->where('key', 'compressor')->value('id'),
            'unit' => 'pc',
            'sell_price' => 100,
            'cost_price' => 40,
            'min_stock' => 0,
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);

        Stock::query()->create([
            'part_id' => $part->id,
            'branch_id' => $branch->id,
            'quantity' => 10,
            'average_cost' => 50,
        ]);

        $before = $this->withToken($token)->getJson('/api/v1/dashboard/summary?branch_id='.$branch->id);
        $before->assertOk();
        $this->assertEquals(500.0, (float) $before->json('total_stock_value_cost'));

        $this->withToken($token)->deleteJson('/api/v1/parts/'.$part->id)->assertNoContent();

        $after = $this->withToken($token)->getJson('/api/v1/dashboard/summary?branch_id='.$branch->id);
        $after->assertOk();
        $this->assertEquals(0.0, (float) $after->json('total_stock_value_cost'));

        $inventory = $this->withToken($token)->getJson('/api/v1/dashboard/inventory?branch_id='.$branch->id);
        $inventory->assertOk();
        $partIds = collect($inventory->json('data'))->pluck('part_id')->all();
        $this->assertNotContains($part->id, $partIds);
    }
}
