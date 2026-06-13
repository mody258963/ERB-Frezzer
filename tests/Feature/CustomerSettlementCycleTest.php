<?php

namespace Tests\Feature;

use App\Enums\CustomerType;
use App\Enums\SettlementCycle;
use App\Models\Customer;
use App\Support\CustomerSettlementSchedule;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CustomerSettlementCycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_customer_can_be_created_with_daily_settlement_cycle(): void
    {
        $this->seed(DatabaseSeeder::class);

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $this->withToken($token)->postJson('/api/v1/customers', [
            'name' => 'Daily Credit Customer',
            'type' => 'credit',
            'phone' => '01100000001',
            'settlement_cycle' => 'daily',
        ])->assertCreated()
            ->assertJsonPath('settlement_cycle', 'daily');
    }

    public function test_credit_customer_defaults_to_weekly_settlement_cycle(): void
    {
        $this->seed(DatabaseSeeder::class);

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $this->withToken($token)->postJson('/api/v1/customers', [
            'name' => 'Weekly Credit Customer',
            'type' => 'credit',
            'phone' => '01100000002',
        ])->assertCreated()
            ->assertJsonPath('settlement_cycle', 'weekly');
    }

    public function test_upcoming_settlements_respects_daily_and_weekly_cycles(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-13 10:00:00')); // Saturday

        $this->seed(DatabaseSeeder::class);

        $token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $dailyCustomerId = (string) $this->withToken($token)->postJson('/api/v1/customers', [
            'name' => 'Daily Due',
            'type' => 'credit',
            'credit_limit' => 10000,
            'settlement_cycle' => 'daily',
        ])->json('id');

        $weeklyCustomerId = (string) $this->withToken($token)->postJson('/api/v1/customers', [
            'name' => 'Weekly Due',
            'type' => 'credit',
            'credit_limit' => 10000,
            'settlement_cycle' => 'weekly',
        ])->json('id');

        Customer::query()->findOrFail($dailyCustomerId)->update([
            'outstanding_balance' => 100,
            'last_settled_at' => Carbon::parse('2026-06-12 18:00:00'),
        ]);

        Customer::query()->findOrFail($weeklyCustomerId)->update([
            'outstanding_balance' => 200,
            'last_settled_at' => Carbon::parse('2026-06-13 09:00:00'),
        ]);

        $dailyUpcoming = $this->withToken($token)
            ->getJson('/api/v1/settlements/upcoming?settlement_cycle=daily')
            ->assertOk()
            ->json();

        $this->assertSame($dailyCustomerId, $dailyUpcoming[0]['customer_id']);

        $weeklyUpcoming = $this->withToken($token)
            ->getJson('/api/v1/settlements/upcoming?settlement_cycle=weekly')
            ->assertOk()
            ->json();

        $this->assertSame([], $weeklyUpcoming);

        Carbon::setTestNow(Carbon::parse('2026-06-20 10:00:00')); // next Saturday

        $weeklyUpcomingLater = $this->withToken($token)
            ->getJson('/api/v1/settlements/upcoming?settlement_cycle=weekly')
            ->assertOk()
            ->json();

        $this->assertSame($weeklyCustomerId, $weeklyUpcomingLater[0]['customer_id']);
    }

    public function test_schedule_helper_daily_vs_weekly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-13 12:00:00'));

        $daily = new Customer([
            'type' => CustomerType::Credit,
            'settlement_cycle' => SettlementCycle::Daily,
            'last_settled_at' => Carbon::parse('2026-06-13 08:00:00'),
        ]);
        $weekly = new Customer([
            'type' => CustomerType::Credit,
            'settlement_cycle' => SettlementCycle::Weekly,
            'last_settled_at' => Carbon::parse('2026-06-13 08:00:00'),
        ]);

        $this->assertFalse(CustomerSettlementSchedule::isDue($daily, 100));
        $this->assertFalse(CustomerSettlementSchedule::isDue($weekly, 100));

        Carbon::setTestNow(Carbon::parse('2026-06-14 12:00:00'));

        $this->assertTrue(CustomerSettlementSchedule::isDue($daily, 100));
        $this->assertFalse(CustomerSettlementSchedule::isDue($weekly, 100));
    }
}
