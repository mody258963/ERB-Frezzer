<?php

namespace App\Transformers;

use App\Models\CustomerPayment;

final class CustomerPaymentTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(CustomerPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'customer_id' => $payment->customer_id,
            'amount' => (float) $payment->amount,
            'payment_method' => $payment->payment_method?->value,
            'notes' => $payment->notes,
            'created_by' => $payment->creator ? [
                'id' => $payment->creator->id,
                'name' => $payment->creator->name,
            ] : null,
            'created_at' => $payment->created_at?->toIso8601String(),
        ];
    }
}
