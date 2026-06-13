<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBranchFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_newly_created_customer_appears_in_branch_filtered_list(): void
    {
        $this->seed(DatabaseSeeder::class);

        $branch = Branch::query()->firstOrFail();

        User::query()->create([
            'name' => 'Sales',
            'email' => 'sales-customer@example.com',
            'password' => 'password123',
            'role' => UserRole::Salesperson,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'sales-customer@example.com',
            'password' => 'password123',
        ])->json('token');

        $create = $this->withToken($token)->postJson('/api/v1/customers', [
            'name' => 'New Branch Customer',
            'type' => 'cash',
            'phone' => '01100000001',
        ])->assertCreated();

        $customerId = (string) $create->json('id');

        $this->withToken($token)
            ->getJson('/api/v1/customers?branch_id='.$branch->id.'&per_page=50')
            ->assertOk()
            ->assertJsonPath('data.0.id', $customerId);
    }

    public function test_customer_index_allows_catalog_sync_page_size(): void
    {
        $this->seed(DatabaseSeeder::class);

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/customers?per_page=500')
            ->assertOk();
    }

    public function test_admin_create_customer_with_branch_in_body_tags_branch(): void
    {
        $this->seed(DatabaseSeeder::class);

        $branch = Branch::query()->firstOrFail();

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $create = $this->withToken($token)->postJson('/api/v1/customers', [
            'name' => 'Admin Body Branch Customer',
            'type' => 'cash',
            'phone' => '01100000002',
            'branch_id' => $branch->id,
        ])->assertCreated();

        $customerId = (string) $create->json('id');
        $this->assertSame($branch->id, $create->json('branch_id'));

        $this->withToken($token)
            ->getJson('/api/v1/customers?branch_id='.$branch->id.'&per_page=50')
            ->assertOk()
            ->assertJsonPath('data.0.id', $customerId);
    }

    public function test_admin_create_customer_with_branch_query_param_tags_branch(): void
    {
        $this->seed(DatabaseSeeder::class);

        $branch = Branch::query()->firstOrFail();

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $create = $this->withToken($token)->postJson('/api/v1/customers?branch_id='.$branch->id, [
            'name' => 'Admin Query Branch Customer',
            'type' => 'cash',
            'phone' => '01100000003',
        ])->assertCreated();

        $this->assertSame($branch->id, $create->json('branch_id'));
    }

    public function test_admin_create_without_branch_on_post_visible_only_without_branch_filter(): void
    {
        $this->seed(DatabaseSeeder::class);

        $branch = Branch::query()->firstOrFail();

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $create = $this->withToken($token)->postJson('/api/v1/customers', [
            'name' => 'Admin No Branch On Post',
            'type' => 'cash',
            'phone' => '01100000004',
        ])->assertCreated();

        $customerId = (string) $create->json('id');

        $this->withToken($token)
            ->getJson('/api/v1/customers?branch_id='.$branch->id.'&per_page=50')
            ->assertOk()
            ->assertJsonMissing(['id' => $customerId]);

        $this->withToken($token)
            ->getJson('/api/v1/customers?per_page=50')
            ->assertOk()
            ->assertJsonFragment(['id' => $customerId]);
    }
}
