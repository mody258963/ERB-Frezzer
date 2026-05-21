<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\BranchFinanceController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InstallmentController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\PartCategoryController;
use App\Http\Controllers\Api\V1\PartController;
use App\Http\Controllers\Api\V1\PartUnitController;
use App\Http\Controllers\Api\V1\ProductReturnController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SettlementController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\SupplierController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::middleware('auth:api')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::get('/branches', [BranchController::class, 'index']);
        Route::post('/branches', [BranchController::class, 'store'])->middleware('role:admin');
        Route::get('/branches/{id}', [BranchController::class, 'show']);
        Route::put('/branches/{id}', [BranchController::class, 'update'])->middleware('role:admin');
        Route::delete('/branches/{id}', [BranchController::class, 'destroy'])->middleware('role:admin');

        Route::get('/part-categories', [PartCategoryController::class, 'index']);
        Route::post('/part-categories', [PartCategoryController::class, 'store'])->middleware('role:admin,manager');
        Route::put('/part-categories/{id}', [PartCategoryController::class, 'update'])->middleware('role:admin,manager');
        Route::delete('/part-categories/{id}', [PartCategoryController::class, 'destroy'])->middleware('role:admin');

        Route::get('/part-units', [PartUnitController::class, 'index']);

        Route::get('/parts', [PartController::class, 'index']);
        Route::post('/parts', [PartController::class, 'store'])->middleware('role:admin,manager');
        Route::get('/parts/{id}', [PartController::class, 'show']);
        Route::get('/parts/{id}/analysis', [PartController::class, 'analysis']);
        Route::put('/parts/{id}', [PartController::class, 'update'])->middleware('role:admin,manager');
        Route::delete('/parts/{id}', [PartController::class, 'destroy'])->middleware('role:admin');

        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock']);
        Route::get('/inventory/{branchId}', [InventoryController::class, 'byBranch']);
        Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])->middleware('role:admin,warehouse');

        Route::get('/transfers', [StockTransferController::class, 'index']);
        Route::post('/transfers', [StockTransferController::class, 'store'])->middleware('role:admin,manager,warehouse');
        Route::get('/transfers/{id}', [StockTransferController::class, 'show']);
        Route::patch('/transfers/{id}/complete', [StockTransferController::class, 'complete'])->middleware('role:admin,manager,warehouse');
        Route::patch('/transfers/{id}/cancel', [StockTransferController::class, 'cancel'])->middleware('role:admin,manager');

        Route::get('/branch-finance/balances', [BranchFinanceController::class, 'balances']);
        Route::get('/branch-finance/entries', [BranchFinanceController::class, 'index']);
        Route::get('/branch-finance/entries/{id}', [BranchFinanceController::class, 'show']);
        Route::post('/branch-finance/charges', [BranchFinanceController::class, 'storeCharge'])->middleware('role:admin,manager');
        Route::post('/branch-finance/payments', [BranchFinanceController::class, 'storePayment'])->middleware('role:admin,manager');
        Route::patch('/branch-finance/entries/{id}/settle', [BranchFinanceController::class, 'settle'])->middleware('role:admin,manager');

        Route::get('/customers', [CustomerController::class, 'index']);
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::get('/customers/{id}', [CustomerController::class, 'show']);
        Route::put('/customers/{id}', [CustomerController::class, 'update']);
        Route::delete('/customers/{id}', [CustomerController::class, 'destroy'])->middleware('role:admin');
        Route::get('/customers/{id}/invoices', [CustomerController::class, 'invoices']);
        Route::get('/customers/{id}/balance', [CustomerController::class, 'balance']);

        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::get('/invoices/pending-credit', [InvoiceController::class, 'pendingCredit']);
        Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
        Route::patch('/invoices/{id}/cancel', [InvoiceController::class, 'cancel'])->middleware('role:admin,manager');

        Route::get('/settlements', [SettlementController::class, 'index']);
        Route::post('/settlements', [SettlementController::class, 'store'])->middleware('role:admin,manager');
        Route::get('/settlements/upcoming', [SettlementController::class, 'upcoming']);
        Route::get('/settlements/{id}', [SettlementController::class, 'show']);

        Route::get('/suppliers', [SupplierController::class, 'index']);
        Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('role:admin,manager');
        Route::get('/suppliers/{id}', [SupplierController::class, 'show']);
        Route::put('/suppliers/{id}', [SupplierController::class, 'update'])->middleware('role:admin,manager');
        Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy'])->middleware('role:admin');
        Route::get('/suppliers/{id}/debt', [SupplierController::class, 'debt']);

        Route::get('/purchases', [PurchaseController::class, 'index']);
        Route::post('/purchases', [PurchaseController::class, 'store'])->middleware('role:admin,manager');
        Route::get('/purchases/{id}', [PurchaseController::class, 'show']);
        Route::patch('/purchases/{id}/receive', [PurchaseController::class, 'receive'])->middleware('role:admin,manager,warehouse');
        Route::patch('/purchases/{id}/cancel', [PurchaseController::class, 'cancel'])->middleware('role:admin,manager');

        Route::get('/installments', [InstallmentController::class, 'index']);
        Route::get('/installments/overdue', [InstallmentController::class, 'overdue']);
        Route::post('/installments/{id}/pay', [InstallmentController::class, 'pay'])->middleware('role:admin,manager');

        Route::get('/returns', [ProductReturnController::class, 'index']);
        Route::post('/returns', [ProductReturnController::class, 'store']);
        Route::get('/returns/{id}', [ProductReturnController::class, 'show']);
        Route::patch('/returns/{id}/approve', [ProductReturnController::class, 'approve'])->middleware('role:admin,manager');
        Route::patch('/returns/{id}/reject', [ProductReturnController::class, 'reject'])->middleware('role:admin,manager');

        Route::prefix('dashboard')->group(function () {
            Route::get('/summary', [DashboardController::class, 'summary']);
            Route::get('/inventory', [DashboardController::class, 'inventory']);
            Route::get('/receivables', [DashboardController::class, 'receivables']);
            Route::get('/payables', [DashboardController::class, 'payables']);
            Route::get('/sales', [DashboardController::class, 'sales']);
            Route::get('/activity', [DashboardController::class, 'activity']);
        });

        Route::prefix('reports')->group(function () {
            Route::get('/sales', [ReportController::class, 'sales']);
            Route::get('/inventory', [ReportController::class, 'inventory']);
            Route::get('/customers', [ReportController::class, 'customers']);
            Route::get('/suppliers', [ReportController::class, 'suppliers']);
            Route::get('/returns', [ReportController::class, 'returns']);
            Route::get('/parts-sales-chart', [ReportController::class, 'partsSalesChart']);
        });
    });
});
