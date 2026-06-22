<?php

namespace App\Services;

use App\Enums\SettlementPaymentMethod;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SupplierPaymentService
{
    public function __construct(
        private SupplierInstallmentAllocationService $allocation,
        private AuditLogService $audit,
        private DashboardCacheService $dashboardCache,
    ) {}

    /**
     * @param  array{payment_method: string, amount?: float|string|null, notes?: ?string}  $data
     * @return array{
     *     supplier: Supplier,
     *     amount: string,
     *     payment_method: SettlementPaymentMethod,
     *     notes: ?string,
     *     paid_at: \Carbon\CarbonInterface,
     *     allocations: list<\App\Models\SupplierInstallmentPayment>
     * }
     */
    public function pay(User $user, Supplier $supplier, array $data): array
    {
        $paymentAmount = $this->resolvePaymentAmount($supplier, $data['amount'] ?? null);

        if (bccomp($paymentAmount, '0', 2) <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $method = SettlementPaymentMethod::from($data['payment_method']);
        if ($method === SettlementPaymentMethod::Offset) {
            throw new \InvalidArgumentException('Use contra offset for linked-party offset payments.');
        }

        $notes = $data['notes'] ?? null;
        $paidAt = now();

        $result = DB::transaction(function () use ($user, $supplier, $paymentAmount, $method, $notes, $paidAt) {
            $lockedSupplier = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);
            $debt = (string) $lockedSupplier->total_debt;

            if (bccomp($debt, '0', 2) <= 0) {
                throw new \InvalidArgumentException('Supplier has no outstanding debt.');
            }

            if (bccomp($paymentAmount, $debt, 2) > 0) {
                throw new \InvalidArgumentException(
                    'Payment amount exceeds supplier debt ('.number_format((float) $debt, 2).').',
                );
            }

            $allocations = $this->allocation->allocate(
                $user,
                $lockedSupplier,
                $paymentAmount,
                $method,
                $notes,
                $paidAt,
            );

            $this->audit->record(
                $user,
                'supplier.payment',
                'supplier',
                $lockedSupplier->id,
                null,
                [
                    'amount' => $paymentAmount,
                    'payment_method' => $method->value,
                    'allocation_count' => count($allocations),
                ],
            );

            return [
                'supplier' => $lockedSupplier->fresh(),
                'amount' => $paymentAmount,
                'payment_method' => $method,
                'notes' => $notes,
                'paid_at' => $paidAt,
                'allocations' => $allocations,
            ];
        });

        $this->dashboardCache->forgetAllSummaries();

        return $result;
    }

    public function history(string $supplierId, int $perPage = 25): LengthAwarePaginator
    {
        return $this->allocation->paymentHistory($supplierId, $perPage);
    }

    private function resolvePaymentAmount(Supplier $supplier, mixed $requested): string
    {
        $debt = (string) $supplier->total_debt;

        if ($requested === null || $requested === '') {
            return $debt;
        }

        return bcadd((string) $requested, '0', 2);
    }
}
