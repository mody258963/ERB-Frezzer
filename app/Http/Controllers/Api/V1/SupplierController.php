<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierDebtResource;
use App\Http\Resources\SupplierResource;
use App\Models\PurchaseOrder;
use App\Models\SupplierInstallment;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends Controller
{
    public function __construct(
        private SupplierRepositoryInterface $suppliers
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return SupplierResource::collection(
            $this->suppliers->paginate($request->user(), (int) $request->query('per_page', 25))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'contact_person' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
        ]);

        return (new SupplierResource($this->suppliers->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): SupplierResource
    {
        $s = $this->suppliers->find($id);
        abort_if(! $s, 404);

        return new SupplierResource($s);
    }

    public function update(Request $request, string $id): SupplierResource
    {
        $s = $this->suppliers->find($id);
        abort_if(! $s, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'string'],
            'contact_person' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
        ]);

        return new SupplierResource($this->suppliers->update($s, $data));
    }

    public function destroy(string $id): JsonResponse
    {
        $s = $this->suppliers->find($id);
        abort_if(! $s, 404);
        $this->suppliers->update($s, ['is_active' => false]);

        return response()->json(null, 204);
    }

    public function debt(string $id): SupplierDebtResource
    {
        $s = $this->suppliers->find($id);
        abort_if(! $s, 404);

        $pos = PurchaseOrder::query()
            ->where('supplier_id', $id)
            ->with(['items.part', 'installments', 'branch', 'supplier', 'creator'])
            ->get();

        $installments = SupplierInstallment::query()
            ->where('supplier_id', $id)
            ->orderBy('due_date')
            ->get();

        return new SupplierDebtResource([
            'supplier' => $s,
            'purchase_orders' => $pos,
            'installments' => $installments,
        ]);
    }
}
