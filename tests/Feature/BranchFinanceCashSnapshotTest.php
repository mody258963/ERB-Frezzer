<?php

namespace Tests\Feature;

use App\Models\Branch;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BranchFinanceCashSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private Branch $creditorBranch;

    private Branch $debtorBranch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::flush();

        $this->token = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('token');

        $this->creditorBranch = Branch::query()->firstOrFail();
        $this->debtorBranch = Branch::query()->create([
            'name' => 'Cash Snapshot Debtor',
            'is_active' => true,
        ]);
    }

    public function test_inter_branch_payment_updates_per_branch_cash_boxes(): void
    {
        $creditorBefore = $this->dashboardSummary($this->creditorBranch->id);
        $debtorBefore = $this->dashboardSummary($this->debtorBranch->id);
        $orgBefore = $this->dashboardSummary();

        $this->withToken($this->token)->postJson('/api/v1/branch-finance/payments', [
            'creditor_branch_id' => $this->creditorBranch->id,
            'debtor_branch_id' => $this->debtorBranch->id,
            'amount' => 250,
        ])->assertCreated();

        $creditorAfter = $this->dashboardSummary($this->creditorBranch->id);
        $debtorAfter = $this->dashboardSummary($this->debtorBranch->id);
        $orgAfter = $this->dashboardSummary();

        $this->assertEqualsWithDelta(
            (float) $creditorBefore['cash_on_hand_realized'] + 250,
            (float) $creditorAfter['cash_on_hand_realized'],
            0.01,
        );
        $this->assertEqualsWithDelta(
            (float) $creditorBefore['period_cash_in_realized'] + 250,
            (float) $creditorAfter['period_cash_in_realized'],
            0.01,
        );

        $this->assertEqualsWithDelta(
            (float) $debtorBefore['cash_on_hand_realized'] - 250,
            (float) $debtorAfter['cash_on_hand_realized'],
            0.01,
        );
        $this->assertEqualsWithDelta(
            (float) $debtorBefore['period_cash_out_realized'] + 250,
            (float) $debtorAfter['period_cash_out_realized'],
            0.01,
        );

        $this->assertEqualsWithDelta(
            (float) $orgBefore['cash_on_hand_realized'],
            (float) $orgAfter['cash_on_hand_realized'],
            0.01,
        );
    }

    public function test_voided_inter_branch_payment_reverses_cash_effect(): void
    {
        $paymentId = (string) $this->withToken($this->token)->postJson('/api/v1/branch-finance/payments', [
            'creditor_branch_id' => $this->creditorBranch->id,
            'debtor_branch_id' => $this->debtorBranch->id,
            'amount' => 180,
        ])->assertCreated()->json('id');

        $creditorWithPayment = $this->dashboardSummary($this->creditorBranch->id);
        $debtorWithPayment = $this->dashboardSummary($this->debtorBranch->id);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/branch-finance/entries/{$paymentId}")
            ->assertNoContent();

        $creditorAfterVoid = $this->dashboardSummary($this->creditorBranch->id);
        $debtorAfterVoid = $this->dashboardSummary($this->debtorBranch->id);

        $this->assertEqualsWithDelta(
            (float) $creditorWithPayment['cash_on_hand_realized'] - 180,
            (float) $creditorAfterVoid['cash_on_hand_realized'],
            0.01,
        );
        $this->assertEqualsWithDelta(
            (float) $debtorWithPayment['cash_on_hand_realized'] + 180,
            (float) $debtorAfterVoid['cash_on_hand_realized'],
            0.01,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardSummary(?string $branchId = null): array
    {
        $url = '/api/v1/dashboard/summary';
        if ($branchId !== null) {
            $url .= '?branch_id='.$branchId;
        }

        return $this->withToken($this->token)->getJson($url)->assertOk()->json();
    }
}
