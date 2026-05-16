<?php

namespace App\Providers;

use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\PartRepositoryInterface;
use App\Repositories\Contracts\ProductReturnRepositoryInterface;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Repositories\Contracts\SaturdaySettlementRepositoryInterface;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use App\Repositories\Contracts\StockRepositoryInterface;
use App\Repositories\Contracts\StockTransferRepositoryInterface;
use App\Repositories\Contracts\SupplierInstallmentRepositoryInterface;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Repositories\Eloquent\AuditLogRepository;
use App\Repositories\Eloquent\BranchRepository;
use App\Repositories\Eloquent\CustomerRepository;
use App\Repositories\Eloquent\InvoiceRepository;
use App\Repositories\Eloquent\PartRepository;
use App\Repositories\Eloquent\ProductReturnRepository;
use App\Repositories\Eloquent\PurchaseOrderRepository;
use App\Repositories\Eloquent\SaturdaySettlementRepository;
use App\Repositories\Eloquent\StockMovementRepository;
use App\Repositories\Eloquent\StockRepository;
use App\Repositories\Eloquent\StockTransferRepository;
use App\Repositories\Eloquent\SupplierInstallmentRepository;
use App\Repositories\Eloquent\SupplierRepository;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditLogRepositoryInterface::class, AuditLogRepository::class);
        $this->app->singleton(BranchRepositoryInterface::class, BranchRepository::class);
        $this->app->singleton(CustomerRepositoryInterface::class, CustomerRepository::class);
        $this->app->singleton(InvoiceRepositoryInterface::class, InvoiceRepository::class);
        $this->app->singleton(PartRepositoryInterface::class, PartRepository::class);
        $this->app->singleton(ProductReturnRepositoryInterface::class, ProductReturnRepository::class);
        $this->app->singleton(PurchaseOrderRepositoryInterface::class, PurchaseOrderRepository::class);
        $this->app->singleton(SaturdaySettlementRepositoryInterface::class, SaturdaySettlementRepository::class);
        $this->app->singleton(StockMovementRepositoryInterface::class, StockMovementRepository::class);
        $this->app->singleton(StockRepositoryInterface::class, StockRepository::class);
        $this->app->singleton(StockTransferRepositoryInterface::class, StockTransferRepository::class);
        $this->app->singleton(SupplierInstallmentRepositoryInterface::class, SupplierInstallmentRepository::class);
        $this->app->singleton(SupplierRepositoryInterface::class, SupplierRepository::class);
    }

    public function boot(): void
    {
        JsonResource::withoutWrapping();

        Passport::enablePasswordGrant();

        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));
    }
}
