<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Installment\PayInstallmentRequest;
use App\Http\Resources\SupplierInstallmentResource;
use App\Repositories\Contracts\SupplierInstallmentRepositoryInterface;
use App\Services\InstallmentPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InstallmentController extends Controller
{
    public function __construct(
        private SupplierInstallmentRepositoryInterface $installments,
        private InstallmentPaymentService $paymentService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'supplier_id' => $request->query('supplier_id'),
            'is_paid' => $request->query('is_paid'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];

        return SupplierInstallmentResource::collection(
            $this->installments->paginate($request->user(), $filters, (int) $request->query('per_page', 25))
        );
    }

    public function overdue(): AnonymousResourceCollection
    {
        return SupplierInstallmentResource::collection($this->installments->overdue());
    }

    public function pay(PayInstallmentRequest $request, string $id): SupplierInstallmentResource
    {
        return new SupplierInstallmentResource(
            $this->paymentService
                ->pay($request->user(), $this->installments->findOrFail($id), $request->validated())
                ->load(['supplier', 'purchaseOrder', 'paidByUser'])
        );
    }
}
