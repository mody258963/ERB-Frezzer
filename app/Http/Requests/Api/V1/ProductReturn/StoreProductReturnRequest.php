<?php

namespace App\Http\Requests\Api\V1\ProductReturn;

use App\Enums\ReturnReferenceType;
use App\Enums\ReturnType;
use App\Http\Requests\Api\V1\ApiFormRequest;
use App\Services\ReturnQuantityValidator;

class StoreProductReturnRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'return_type' => ['required', 'in:customer_return,supplier_return'],
            'reference_id' => ['required', 'uuid'],
            'reference_type' => ['required', 'in:invoice,purchase_order'],
            'customer_id' => ['nullable', 'uuid'],
            'supplier_id' => ['nullable', 'uuid'],
            'branch_id' => ['required', 'uuid'],
            'reason' => ['nullable', 'string'],
            'attachment_url' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric'],
            'items.*.condition' => ['required', 'in:sellable,defective'],
        ];
    }

    /**
     * @return array{header: array<string, mixed>, items: list<array<string, mixed>>, total_value: string}
     */
    public function payload(ReturnQuantityValidator $returnQuantities): array
    {
        $data = $this->validated();
        $items = [];
        $totalValue = '0';

        foreach ($data['items'] as $row) {
            $lineTotal = bcmul((string) $row['unit_price'], (string) $row['quantity'], 2);
            $totalValue = bcadd($totalValue, $lineTotal, 2);
            $items[] = [
                'part_id' => $row['part_id'],
                'quantity' => $row['quantity'],
                'unit_price' => (string) $row['unit_price'],
                'condition' => $row['condition'],
                'total' => $lineTotal,
            ];
        }

        if ($data['return_type'] === ReturnType::CustomerReturn->value
            && $data['reference_type'] === ReturnReferenceType::Invoice->value) {
            $returnQuantities->assertCustomerInvoiceReturn($data['reference_id'], $items);
        }

        if ($data['return_type'] === ReturnType::SupplierReturn->value
            && $data['reference_type'] === ReturnReferenceType::PurchaseOrder->value) {
            $returnQuantities->assertSupplierPurchaseReturn($data['reference_id'], $items);
        }

        return [
            'header' => [
                'return_type' => $data['return_type'],
                'reference_id' => $data['reference_id'],
                'reference_type' => $data['reference_type'],
                'customer_id' => $data['customer_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'branch_id' => $data['branch_id'],
                'reason' => $data['reason'] ?? null,
                'attachment_url' => $data['attachment_url'] ?? null,
            ],
            'items' => $items,
            'total_value' => $totalValue,
        ];
    }
}
