<?php

namespace App\Services;

use App\Enums\InvoicePaymentType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SettlementPaymentMethod;
use App\Models\ContraSettlement;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInstallment;
use App\Models\SupplierInstallmentPayment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ContraSettlementService
{
    public function __construct(
        private AuditLogService $audit,
        private DashboardCacheService $dashboardCache,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function netBalance(Customer $customer): array
    {
        $customer->loadMissing('linkedSupplier');

        $customerBalance = (string) $customer->outstanding_balance;
        $supplier = $customer->linkedSupplier;
        $supplierDebt = $supplier !== null ? (string) $supplier->total_debt : '0.00';

        return $this->buildBalancePayload($customer, $supplier, $customerBalance, $supplierDebt);
    }

    /**
     * @return array<string, mixed>
     */
    public function netBalanceForSupplier(Supplier $supplier): array
    {
        $supplier->loadMissing('linkedCustomer');
        $customer = $supplier->linkedCustomer;

        if ($customer === null) {
            return $this->buildBalancePayload(null, $supplier, '0.00', (string) $supplier->total_debt);
        }

        return $this->buildBalancePayload(
            $customer,
            $supplier,
            (string) $customer->outstanding_balance,
            (string) $supplier->total_debt,
        );
    }

    /**
     * @param  array{amount?: float|string|null, notes?: ?string}  $data
     */
    public function offset(User $user, Customer $customer, array $data): ContraSettlement
    {
        if ($customer->linked_supplier_id === null) {
            throw new \InvalidArgumentException('Customer is not linked to a supplier.');
        }

        $offsetAmount = $this->resolveOffsetAmount($customer, $data['amount'] ?? null);

        if (bccomp($offsetAmount, '0', 2) <= 0) {
            throw new \InvalidArgumentException('Nothing to offset — both balances are zero.');
        }

        $settlement = DB::transaction(function () use ($user, $customer, $data, $offsetAmount) {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($lockedCustomer->linked_supplier_id);

            $customerBalance = (string) $lockedCustomer->outstanding_balance;
            $supplierDebt = (string) $supplier->total_debt;
            $maxOffset = $this->maxOffsetAmount($customerBalance, $supplierDebt);

            if (bccomp($maxOffset, '0', 2) <= 0) {
                throw new \InvalidArgumentException('Nothing to offset — both balances are zero.');
            }

            if (bccomp($offsetAmount, $maxOffset, 2) > 0) {
                throw new \InvalidArgumentException(
                    'Offset amount exceeds the maximum allowed ('.number_format((float) $maxOffset, 2).').',
                );
            }

            $noteSuffix = $data['notes'] ?? null;
            $settlement = ContraSettlement::query()->create([
                'customer_id' => $lockedCustomer->id,
                'supplier_id' => $supplier->id,
                'amount' => $offsetAmount,
                'notes' => $noteSuffix,
                'created_by' => $user->id,
                'created_at' => now(),
            ]);

            $memo = trim('Contra settlement '.$settlement->id.($noteSuffix ? ' — '.$noteSuffix : ''));

            $this->applyCustomerOffset($user, $lockedCustomer, $offsetAmount, $memo);
            $this->applySupplierOffset($user, $supplier, $offsetAmount, $memo);

            $this->audit->record(
                $user,
                'contra.settlement',
                'contra_settlement',
                $settlement->id,
                null,
                $settlement->fresh(['customer', 'supplier', 'creator'])?->toArray(),
            );

            return $settlement->load(['customer', 'supplier', 'creator']);
        });

        $this->dashboardCache->forgetAllSummaries();

        return $settlement;
    }

    public function history(string $customerId, int $perPage = 25): LengthAwarePaginator
    {
        return ContraSettlement::query()
            ->with(['supplier', 'creator'])
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    private function resolveOffsetAmount(Customer $customer, mixed $requested): string
    {
        $customer->loadMissing('linkedSupplier');

        if ($customer->linkedSupplier === null) {
            throw new \InvalidArgumentException('Customer is not linked to a supplier.');
        }

        $maxOffset = $this->maxOffsetAmount(
            (string) $customer->outstanding_balance,
            (string) $customer->linkedSupplier->total_debt,
        );

        if ($requested === null || $requested === '') {
            return $maxOffset;
        }

        return bcadd((string) $requested, '0', 2);
    }

    private function maxOffsetAmount(string $customerBalance, string $supplierDebt): string
    {
        if (bccomp($customerBalance, '0', 2) <= 0 || bccomp($supplierDebt, '0', 2) <= 0) {
            return '0.00';
        }

        return bccomp($customerBalance, $supplierDebt, 2) <= 0 ? $customerBalance : $supplierDebt;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBalancePayload(
        ?Customer $customer,
        ?Supplier $supplier,
        string $customerBalance,
        string $supplierDebt,
    ): array {
        $isLinked = $customer !== null && $supplier !== null && $customer->linked_supplier_id === $supplier->id;
        $maxOffset = $isLinked ? $this->maxOffsetAmount($customerBalance, $supplierDebt) : '0.00';
        $netComparison = bcsub($customerBalance, $supplierDebt, 2);

        if (bccomp($netComparison, '0', 2) > 0) {
            $netDirection = 'they_owe_us';
            $netAmount = $netComparison;
        } elseif (bccomp($netComparison, '0', 2) < 0) {
            $netDirection = 'we_owe_them';
            $netAmount = bcmul($netComparison, '-1', 2);
        } else {
            $netDirection = 'balanced';
            $netAmount = '0.00';
        }

        return [
            'is_linked' => $isLinked,
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'type' => $customer->type?->value,
                'outstanding_balance' => (float) $customerBalance,
            ] : null,
            'supplier' => $supplier ? [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'total_debt' => (float) $supplierDebt,
            ] : null,
            'customer_balance' => (float) $customerBalance,
            'supplier_debt' => (float) $supplierDebt,
            'net_amount' => (float) $netAmount,
            'net_direction' => $netDirection,
            'max_offset_amount' => (float) $maxOffset,
        ];
    }

    private function applyCustomerOffset(User $user, Customer $customer, string $amount, string $notes): void
    {
        CustomerPayment::query()->create([
            'customer_id' => $customer->id,
            'amount' => $amount,
            'payment_method' => SettlementPaymentMethod::Offset,
            'notes' => $notes,
            'created_by' => $user->id,
            'created_at' => now(),
        ]);

        $remaining = $amount;
        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('payment_type', InvoicePaymentType::Credit)
            ->where('is_paid', false)
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        foreach ($invoices as $invoice) {
            if (bccomp($remaining, '0', 2) <= 0) {
                break;
            }

            $due = bcsub((string) $invoice->total, (string) $invoice->amount_paid, 2);
            if (bccomp($due, '0', 2) <= 0) {
                continue;
            }

            $apply = bccomp($remaining, $due, 2) >= 0 ? $due : $remaining;
            $invoice->amount_paid = bcadd((string) $invoice->amount_paid, $apply, 2);

            if (bccomp((string) $invoice->amount_paid, (string) $invoice->total, 2) >= 0) {
                $invoice->is_paid = true;
                $invoice->paid_at = now();
            }

            $invoice->save();
            $remaining = bcsub($remaining, $apply, 2);
        }

        $customer->outstanding_balance = bcsub((string) $customer->outstanding_balance, $amount, 2);
        if (bccomp((string) $customer->outstanding_balance, '0', 2) < 0) {
            $customer->outstanding_balance = '0.00';
        }
        $customer->save();
    }

    private function applySupplierOffset(User $user, Supplier $supplier, string $amount, string $notes): void
    {
        $remaining = $amount;
        $method = SettlementPaymentMethod::Offset;
        $paidAt = now();

        $installments = SupplierInstallment::query()
            ->where('supplier_id', $supplier->id)
            ->where('is_paid', false)
            ->orderBy('due_date')
            ->orderBy('installment_no')
            ->lockForUpdate()
            ->get();

        foreach ($installments as $inst) {
            if (bccomp($remaining, '0', 2) <= 0) {
                break;
            }

            $due = $inst->balanceDue();
            if (bccomp($due, '0', 2) <= 0) {
                continue;
            }

            $apply = bccomp($remaining, $due, 2) >= 0 ? $due : $remaining;

            SupplierInstallmentPayment::query()->create([
                'installment_id' => $inst->id,
                'supplier_id' => $supplier->id,
                'po_id' => $inst->po_id,
                'amount' => $apply,
                'payment_method' => $method,
                'paid_by' => $user->id,
                'notes' => $notes,
                'paid_at' => $paidAt,
            ]);

            $inst->amount_paid = bcadd((string) $inst->amount_paid, $apply, 2);
            $inst->payment_method = $method;
            $inst->paid_by = $user->id;
            $inst->notes = $notes;

            if (bccomp($inst->balanceDue(), '0', 2) <= 0) {
                $inst->is_paid = true;
                $inst->paid_at = $paidAt;
            } else {
                $inst->is_paid = false;
                $inst->paid_at = null;
            }

            $inst->save();

            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($inst->po_id);
            $po->amount_paid = bcadd((string) $po->amount_paid, $apply, 2);

            if (bccomp((string) $po->amount_paid, (string) $po->total_amount, 2) >= 0) {
                $po->status = PurchaseOrderStatus::Settled->value;
            } elseif (bccomp((string) $po->amount_paid, '0', 2) > 0) {
                $po->status = PurchaseOrderStatus::Partial->value;
            }

            $po->save();
            $remaining = bcsub($remaining, $apply, 2);
        }

        $supplier->total_debt = bcsub((string) $supplier->total_debt, $amount, 2);
        if (bccomp((string) $supplier->total_debt, '0', 2) < 0) {
            $supplier->total_debt = '0.00';
        }
        $supplier->save();
    }
}
