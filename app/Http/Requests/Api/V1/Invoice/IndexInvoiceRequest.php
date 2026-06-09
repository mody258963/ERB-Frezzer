<?php

namespace App\Http\Requests\Api\V1\Invoice;

use App\Http\Requests\Api\V1\ApiFormRequest;

class IndexInvoiceRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return array_merge($this->perPageRules(), [
            'payment_type' => ['nullable', 'in:credit,cash'],
            'is_paid' => ['nullable'],
            'customer_id' => ['nullable', 'uuid'],
            'branch_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'payment_type' => $this->query('payment_type'),
            'is_paid' => $this->query('is_paid'),
            'customer_id' => $this->query('customer_id'),
            'branch_id' => $this->query('branch_id'),
            'from' => $this->query('from'),
            'to' => $this->query('to'),
        ];
    }
}
