<?php

namespace App\Services;

use App\Enums\CustomerType;
use App\Enums\InvoicePaymentType;
use App\Enums\SettlementPaymentMethod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SaturdaySettlement;
use App\Models\User;
use App\Repositories\Contracts\SaturdaySettlementRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SaturdaySettlementService
{
    public function __construct(
        private SaturdaySettlementRepositoryInterface $settlements,
        private AuditLogService $audit,
        private DashboardCacheService $dashboardCache,
    ) {}

    /**
     * @param  array{customer_id: string, settlement_date: string, payment_method: string, notes?: ?string}  $data
     */
    public function create(User $user, array $data): SaturdaySettlement
    {
        $settlement = DB::transaction(function () use ($user, $data) {
            $customer = Customer::query()->lockForUpdate()->findOrFail($data['customer_id']);
            if ($customer->type !== CustomerType::Credit) {
                throw new \InvalidArgumentException('Settlements apply to credit customers only.');
            }

            $unpaid = Invoice::query()
                ->where('customer_id', $customer->id)
                ->where('payment_type', InvoicePaymentType::Credit)
                ->where('is_paid', false)
                ->lockForUpdate()
                ->get();

            if ($unpaid->isEmpty()) {
                throw new \InvalidArgumentException('No unpaid credit invoices for this customer.');
            }

            $total = $unpaid->sum(fn (Invoice $i) => (float) $i->total);

            $settlement = $this->settlements->create([
                'settlement_date' => $data['settlement_date'],
                'customer_id' => $customer->id,
                'total_amount' => $total,
                'payment_method' => SettlementPaymentMethod::from($data['payment_method'])->value,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'created_at' => now(),
            ]);

            foreach ($unpaid as $inv) {
                $inv->is_paid = true;
                $inv->paid_at = now();
                $inv->settlement_id = $settlement->id;
                $inv->save();
            }

            $customer->outstanding_balance = '0';
            $customer->last_settled_at = Carbon::parse($data['settlement_date']);
            $customer->save();

            $fresh = $this->settlements->findWithInvoices($settlement->id);
            $this->audit->record($user, 'settlement.create', 'saturday_settlement', $settlement->id, null, $fresh?->toArray());

            return $settlement;
        });

        $this->dashboardCache->forgetAllSummaries();

        return $settlement;
    }
}
