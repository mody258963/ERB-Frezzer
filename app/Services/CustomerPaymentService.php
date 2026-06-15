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

            $this->applyPaymentToInvoices($lockedCustomer, $paymentAmount);
            $this->reduceCustomerBalance($lockedCustomer, $paymentAmount);

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

    /**
     * @param  array{payment_method?: string, amount?: float|string|null, notes?: ?string}  $data
     */
    public function update(User $user, Customer $customer, CustomerPayment $payment, array $data): CustomerPayment
    {
        if ($customer->type !== CustomerType::Credit) {
            throw new \InvalidArgumentException('Partial payments apply to credit customers only.');
        }

        if ($payment->customer_id !== $customer->id) {
            throw new \InvalidArgumentException('Payment does not belong to this customer.');
        }

        $latestPaymentId = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('id');

        if ($latestPaymentId !== $payment->id) {
            throw new \InvalidArgumentException('Only the most recent payment can be edited.');
        }

        $newAmount = array_key_exists('amount', $data)
            ? bcadd((string) $data['amount'], '0', 2)
            : (string) $payment->amount;

        if (bccomp($newAmount, '0', 2) <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $updated = DB::transaction(function () use ($user, $customer, $payment, $data, $newAmount) {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $before = $payment->toArray();
            $oldAmount = (string) $payment->amount;

            $this->reversePaymentFromInvoices($lockedCustomer, $oldAmount);
            $lockedCustomer->outstanding_balance = bcadd((string) $lockedCustomer->outstanding_balance, $oldAmount, 2);
            $lockedCustomer->last_settled_at = null;
            $lockedCustomer->save();

            $balance = (string) $lockedCustomer->fresh()->outstanding_balance;
            if (bccomp($newAmount, $balance, 2) > 0) {
                throw new \InvalidArgumentException(
                    'Payment amount exceeds customer balance ('.number_format((float) $balance, 2).').',
                );
            }

            if (array_key_exists('payment_method', $data)) {
                $payment->payment_method = SettlementPaymentMethod::from($data['payment_method']);
            }

            if (array_key_exists('notes', $data)) {
                $payment->notes = $data['notes'];
            }

            $payment->amount = $newAmount;
            $payment->save();

            $this->applyPaymentToInvoices($lockedCustomer, $newAmount);
            $this->reduceCustomerBalance($lockedCustomer, $newAmount);

            $this->audit->record(
                $user,
                'customer.payment.update',
                'customer_payment',
                $payment->id,
                $before,
                $payment->fresh(['customer', 'creator'])?->toArray(),
            );

            return $payment->load(['customer', 'creator']);
        });

        $this->dashboardCache->forgetAllSummaries();

        return $updated;
    }

    public function history(string $customerId, int $perPage = 25): LengthAwarePaginator
    {
        return CustomerPayment::query()
            ->with('creator')
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    private function applyPaymentToInvoices(Customer $customer, string $paymentAmount): void
    {
        $remaining = $paymentAmount;
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
    }

    private function reversePaymentFromInvoices(Customer $customer, string $paymentAmount): void
    {
        $remaining = $paymentAmount;
        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('payment_type', InvoicePaymentType::Credit)
            ->where('amount_paid', '>', 0)
            ->orderByDesc('created_at')
            ->lockForUpdate()
            ->get();

        foreach ($invoices as $invoice) {
            if (bccomp($remaining, '0', 2) <= 0) {
                break;
            }

            $paid = (string) $invoice->amount_paid;
            $reverse = bccomp($remaining, $paid, 2) >= 0 ? $paid : $remaining;
            $invoice->amount_paid = bcsub($paid, $reverse, 2);

            if (bccomp((string) $invoice->amount_paid, '0', 2) <= 0) {
                $invoice->amount_paid = '0.00';
            }

            $invoice->is_paid = false;
            $invoice->paid_at = null;
            $invoice->save();

            $remaining = bcsub($remaining, $reverse, 2);
        }
    }

    private function reduceCustomerBalance(Customer $customer, string $paymentAmount): void
    {
        $balance = (string) $customer->outstanding_balance;
        $customer->outstanding_balance = bcsub($balance, $paymentAmount, 2);

        if (bccomp((string) $customer->outstanding_balance, '0', 2) < 0) {
            $customer->outstanding_balance = '0.00';
        }

        if (bccomp((string) $customer->outstanding_balance, '0', 2) <= 0) {
            $customer->last_settled_at = now();
        }

        $customer->save();
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
