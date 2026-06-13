<?php

namespace App\Services;

use App\Enums\CustomerType;
use App\Enums\InvoicePaymentType;
use App\Enums\SettlementPaymentMethod;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerPaymentService
{
    public function __construct(
        private AuditLogService $audit,
        private DashboardCacheService $dashboardCache,
    ) {}

    /**
     * @param  array{payment_method: string, amount?: float|string|null, notes?: ?string}  $data
     */
    public function collect(User $user, Customer $customer, array $data): CustomerPayment
    {
        if ($customer->type !== CustomerType::Credit) {
            throw new \InvalidArgumentException('Partial payments apply to credit customers only.');
        }

        $paymentAmount = $this->resolvePaymentAmount($customer, $data['amount'] ?? null);

        if (bccomp($paymentAmount, '0', 2) <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $payment = DB::transaction(function () use ($user, $customer, $data, $paymentAmount) {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            $balance = (string) $lockedCustomer->outstanding_balance;
            if (bccomp($paymentAmount, $balance, 2) > 0) {
                throw new \InvalidArgumentException(
                    'Payment amount exceeds customer balance ('.number_format((float) $balance, 2).').',
                );
            }

            $payment = CustomerPayment::query()->create([
                'customer_id' => $lockedCustomer->id,
                'amount' => $paymentAmount,
                'payment_method' => SettlementPaymentMethod::from($data['payment_method']),
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'created_at' => now(),
            ]);

            $remaining = $paymentAmount;
            $invoices = Invoice::query()
                ->where('customer_id', $lockedCustomer->id)
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

            $lockedCustomer->outstanding_balance = bcsub($balance, $paymentAmount, 2);
            if (bccomp((string) $lockedCustomer->outstanding_balance, '0', 2) < 0) {
                $lockedCustomer->outstanding_balance = '0.00';
            }
            if (bccomp((string) $lockedCustomer->outstanding_balance, '0', 2) <= 0) {
                $lockedCustomer->last_settled_at = now();
            }
            $lockedCustomer->save();

            $this->audit->record(
                $user,
                'customer.payment',
                'customer_payment',
                $payment->id,
                null,
                $payment->fresh(['customer', 'creator'])?->toArray(),
            );

            return $payment->load(['customer', 'creator']);
        });

        $this->dashboardCache->forgetAllSummaries();

        return $payment;
    }

    public function history(string $customerId, int $perPage = 25): LengthAwarePaginator
    {
        return CustomerPayment::query()
            ->with('creator')
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    private function resolvePaymentAmount(Customer $customer, mixed $requested): string
    {
        $balance = (string) $customer->outstanding_balance;

        if ($requested === null || $requested === '') {
            return $balance;
        }

        return bcadd((string) $requested, '0', 2);
    }
}
