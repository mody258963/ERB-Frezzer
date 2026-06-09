# ERB-Frezzer / FrostParts — Project Architecture

Backend API for a **multi-branch auto-parts shop** (inventory, sales, purchases, credit customers, supplier installments, returns, dashboard, and reports). The primary client is a **Flutter Windows app** (`erd_rezzer`).

**API base:** `/api/v1`  
**Auth:** Laravel Passport (OAuth2 password grant) — Bearer token on protected routes.

---

## Table of contents

1. [High-level overview](#high-level-overview)
2. [Request lifecycle](#request-lifecycle)
3. [Folder structure](#folder-structure)
4. [Layers in detail](#layers-in-detail)
5. [Business domains](#business-domains)
6. [Financial model](#financial-model)
7. [Authentication & roles](#authentication--roles)
8. [Database & models](#database--models)
9. [Background jobs & schedule](#background-jobs--schedule)
10. [How to add a new feature](#how-to-add-a-new-feature)
11. [Related documentation](#related-documentation)

---

## High-level overview

The app follows a **layered architecture** inspired by clean architecture / repository pattern:

```
┌─────────────────────────────────────────────────────────────────┐
│  Flutter client (erd_rezzer)                                    │
└────────────────────────────┬────────────────────────────────────┘
                             │ HTTPS JSON
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  routes/api.php          → URL → Controller action              │
│  Middleware              → auth:api, role:admin,manager,...    │
└────────────────────────────┬────────────────────────────────────┘
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  Form Requests           → validate & shape input               │
│  Controllers (thin)        → delegate, return Resources           │
└────────────────────────────┬────────────────────────────────────┘
                             ▼
┌──────────────────────┐  ┌──────────────────────┐
│  Services            │  │  Repositories         │
│  Business rules      │  │  DB queries           │
│  Transactions        │  │  Pagination         │
└──────────┬───────────┘  └──────────┬───────────┘
           │                         │
           └────────────┬────────────┘
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│  Eloquent Models  →  MySQL / SQLite                               │
└────────────────────────────┬────────────────────────────────────┘
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  Resources + Transformers  →  JSON response to client           │
└─────────────────────────────────────────────────────────────────┘
```

**Rule of thumb**

| Layer | Should do | Should not do |
|-------|-----------|---------------|
| Controller | Wire HTTP to service/repo, return JSON | Heavy business logic, raw SQL |
| Form Request | Validation rules, defaults, filters | Database writes |
| Service | Business rules, `DB::transaction`, side effects | HTTP concerns |
| Repository | Queries, create/update via Eloquent | Invoice pricing rules, profit math |
| Transformer | Map model → API field names/types | Business logic |

---

## Request lifecycle

Example: **collect partial payment from a credit customer**

```
POST /api/v1/customers/{id}/payments
Authorization: Bearer <token>
Body: { "payment_method": "cash", "amount": 300 }
```

1. **Route** (`routes/api.php`) → `CustomerController@collectPayment` with `role:admin,manager`.
2. **Middleware** `auth:api` validates Passport token; `role` checks `UserRole`.
3. **Form Request** `CollectCustomerPaymentRequest` validates `payment_method`, `amount`, `notes`.
4. **Controller** loads customer via `CustomerRepository::findOrFail`, calls `CustomerPaymentService::collect`.
5. **Service** (inside a DB transaction):
   - Locks customer row
   - Creates `CustomerPayment` record
   - Applies amount FIFO to oldest unpaid credit invoices (`amount_paid`, `is_paid`)
   - Reduces `customer.outstanding_balance`
   - Writes audit log
   - Clears dashboard cache
6. **Resource** `CustomerPaymentResource` → `CustomerPaymentTransformer` → JSON `201`.

---

## Folder structure

```
app/
├── Console/Commands/          # Scheduled CLI (low stock, overdue installments, …)
├── Enums/                     # Backed PHP enums (payment types, return resolution, roles)
├── Exceptions/                # Domain exceptions → JSON 422 handlers
├── Http/
│   ├── Controllers/
│   │   ├── Concerns/          # Shared controller traits (e.g. resolveOrFail)
│   │   └── Api/V1/            # One controller per API area
│   ├── Middleware/            # EnsureRole
│   ├── Requests/Api/V1/         # Form Request classes (validation)
│   └── Resources/             # JsonResource wrappers (delegate to Transformers)
├── Models/                    # Eloquent models (UUID primary keys)
├── Providers/
│   └── AppServiceProvider.php # Repository interface → implementation bindings
├── Repositories/
│   ├── Contracts/             # Interfaces
│   └── Eloquent/              # Implementations
├── Services/                  # Business logic
├── Support/                   # Helpers (BranchVisibility, PartLookupResolver)
└── Transformers/              # Pure array mapping for API responses

database/migrations/           # Schema
docs/                          # API guides for Flutter + this architecture doc
routes/api.php                 # All v1 API routes
tests/Feature/                 # Integration tests per domain
```

Repository bindings are registered in `AppServiceProvider::register()`:

```php
$this->app->singleton(CustomerRepositoryInterface::class, CustomerRepository::class);
// … all repository pairs
```

Controllers and services receive interfaces via **constructor injection**.

---

## Layers in detail

### Routes (`routes/api.php`)

- Public: `GET /health`, `POST /auth/login`
- Protected group: `middleware('auth:api')`
- Sensitive actions: extra `middleware('role:admin,manager')` etc.

### Form Requests (`app/Http/Requests/Api/V1/`)

Extend `ApiFormRequest` (base class with `perPage()` helper).

- **Store/Update requests** — field rules per endpoint
- **Index requests** — optional filters + `per_page`
- Some requests expose helper methods, e.g. `IndexCustomerRequest::filters()`, `StoreProductReturnRequest::payload()`

Controllers type-hint the request instead of calling `$request->validate([...])`.

### Controllers (`app/Http/Controllers/Api/V1/`)

Thin classes. Typical action:

```php
public function store(StoreCustomerRequest $request): JsonResponse
{
    return (new CustomerResource($this->customers->create($request->validated())))
        ->response()
        ->setStatusCode(201);
}
```

`ResolvesRepositoryModels` trait provides `resolveOrFail($model)` for consistent 404 responses.

### Repositories (`app/Repositories/`)

Most Eloquent repositories extend **`BaseRepository`** (`app/Repositories/Eloquent/BaseRepository.php`), which provides shared helpers:

| Protected helper | Purpose |
|------------------|---------|
| `modelClass()` | Abstract — each repo declares its Eloquent model |
| `defaultRelations()` | Optional eager-load list for find/update |
| `newQuery()` | Fresh Eloquent builder for the model |
| `findById()` / `findByIdOrFail()` | Load one record by UUID |
| `findByIdWith()` | Load with explicit relations |
| `createRecord()` / `updateRecord()` / `saveRecord()` | Persist changes |
| `createWithItems()` | Parent + child rows (invoices, POs, returns, transfers) |
| `nextSequentialNumber()` | Auto-numbering (`INV-0001`, `PO-001`, …) |

Concrete repos implement their **interface** and only add domain-specific queries (`paginate`, `debtSnapshot`, `overdue`, etc.). Repositories that do not fit CRUD (e.g. `StockRepository`, `AuditLogRepository`) stay standalone.

| Interface | Implementation | Main responsibility |
|-----------|------------------|---------------------|
| `CustomerRepositoryInterface` | `CustomerRepository` | Customers CRUD, invoices, unpaid credit |
| `SupplierRepositoryInterface` | `SupplierRepository` | Suppliers CRUD, debt snapshot |
| `InvoiceRepositoryInterface` | `InvoiceRepository` | Invoice list, create with items |
| `PurchaseOrderRepositoryInterface` | `PurchaseOrderRepository` | Purchase orders |
| `SupplierInstallmentRepositoryInterface` | `SupplierInstallmentRepository` | Installments list, overdue |
| `SaturdaySettlementRepositoryInterface` | `SaturdaySettlementRepository` | Credit settlements |
| `ProductReturnRepositoryInterface` | `ProductReturnRepository` | Returns CRUD |
| `StockRepositoryInterface` | `StockRepository` | Stock levels, low-stock alerts |
| `StockTransferRepositoryInterface` | `StockTransferRepository` | Inter-branch transfers |
| `PartRepositoryInterface` | `PartRepository` | Parts catalog |
| `BranchRepositoryInterface` | `BranchRepository` | Branches |
| `BranchFinancialEntryRepositoryInterface` | `BranchFinancialEntryRepository` | Inter-branch charges/payments |
| `UserRepositoryInterface` | `UserRepository` | Users |
| `AuditLogRepositoryInterface` | `AuditLogRepository` | Audit trail |

Common methods: `paginate()`, `find()`, `findOrFail()`, `create()`, `update()`.

### Services (`app/Services/`)

| Service | Purpose |
|---------|---------|
| `InvoiceService` | Create/cancel invoices, stock out, credit balance |
| `PurchaseOrderService` | Create PO, receive goods, cancel |
| `InstallmentPaymentService` | Pay supplier installments (full or partial) |
| `CustomerPaymentService` | Collect from credit customers (partial, FIFO) |
| `SaturdaySettlementService` | Settle all open credit invoices for one customer |
| `ContraSettlementService` | Net balance + offset when customer ↔ supplier linked |
| `ReturnService` | Approve/reject returns (stock + financial impact) |
| `ReturnQuantityValidator` | Enforce return qty ≤ invoice/PO qty |
| `InventoryService` | Manual stock adjustments |
| `StockTransferService` | Complete/cancel transfers, optional branch charge |
| `BranchFinanceService` | Inter-branch charges and payments |
| `CapitalService` | Business capital, owner cash-out |
| `FinancialMetricsService` | Revenue, profit, refunds, supplier weekly stats |
| `DashboardQueryService` | Aggregates dashboard endpoints |
| `DashboardCacheService` | Cache keys for summary (5 min TTL) |
| `ReportQueryService` | Financial and aging reports |
| `AuthService` | Login, logout |
| `AuditLogService` | Record who changed what |
| `PartAnalysisService` | Per-part sales/inventory analytics |
| `PartImageService` | Upload/delete part images |
| `PartSalesChartService` | Chart data for reports |

Services use **database transactions** for money and stock changes.

### Resources & Transformers

- **Resource** (`app/Http/Resources/`) — Laravel `JsonResource`; thin wrapper.
- **Transformer** (`app/Transformers/`) — static `transform()` returns the exact JSON shape.

Example: `CustomerResource` calls `CustomerTransformer::transform($model)`.

`JsonResource::withoutWrapping()` is enabled — responses are **not** wrapped in `{ "data": … }` unless a specific resource adds it.

### Enums (`app/Enums/`)

Important domain values as backed enums, e.g.:

- `UserRole` — admin, manager, salesperson, warehouse
- `InvoicePaymentType` — cash, credit
- `CustomerType` — cash, credit
- `ReturnResolution` — restock, refund_cash, credit_note, writeoff, …
- `SettlementPaymentMethod` — cash, bank_transfer, check, offset

Models cast DB columns to these enums.

### Support (`app/Support/`)

- **`BranchVisibility`** — non-admin users only see their branch in queries/reports.
- **`PartLookupResolver`** — resolve `category_id` from `category_key` when creating parts.

---

## Business domains

### 1. Catalog & inventory

| Concept | Models | Key endpoints |
|---------|--------|---------------|
| Part categories | `PartCategory` | `GET/POST /part-categories` |
| Parts | `Part` | `GET/POST /parts`, images, analysis |
| Stock per branch | `Stock` | `GET /inventory`, adjust |
| Stock movements | `StockMovement` | Written on sale, purchase receive, return, adjust |
| Transfers | `StockTransfer` | `POST /transfers`, complete moves qty between branches |

**Flow — sale:** `InvoiceService` reduces `stock.quantity`, creates `StockMovement` (SaleOut).

**Flow — receive PO:** `PurchaseOrderService` increases stock at branch.

### 2. Sales (invoices)

| Concept | Models | Key endpoints |
|---------|--------|---------------|
| Invoice | `Invoice`, `InvoiceItem` | `POST /invoices` |
| Cash | `payment_type: cash` | Paid immediately |
| Credit | `payment_type: credit` | Increases `customer.outstanding_balance` |

Credit limit is checked on create. Partial payments and Saturday settlement reduce balance.

### 3. Customers & collections

| Concept | Models | Key endpoints |
|---------|--------|---------------|
| Customer | `Customer` | CRUD |
| Partial payment | `CustomerPayment` | `POST /customers/{id}/payments` |
| Saturday settlement | `SaturdaySettlement` | `POST /settlements` (pays all open credit) |
| Balance | — | `GET /customers/{id}/balance` |

Payments apply **FIFO** to oldest unpaid credit invoices.

### 4. Suppliers & purchases

| Concept | Models | Key endpoints |
|---------|--------|---------------|
| Supplier | `Supplier` | CRUD, debt view |
| Purchase order | `PurchaseOrder`, `PurchaseOrderItem` | `POST /purchases` |
| Installments | `SupplierInstallment` | Auto-created when `payment_type: installments` |
| Pay installment | `SupplierInstallmentPayment` | `POST /installments/{id}/pay` |

Receive PO → stock in. Pay installment → reduces `supplier.total_debt`.

### 5. Returns

| Concept | Models | Key endpoints |
|---------|--------|---------------|
| Return | `ProductReturn`, `ReturnItem` | `POST /returns` |
| Approve | — | `PATCH /returns/{id}/approve` with `resolution` |

Customer return resolutions affect stock and dashboard profit differently (see [Financial model](#financial-model)).

### 6. Linked customer + supplier (contra / مقاصة)

When the same person is both customer and supplier:

- Link: `customers.linked_supplier_id`
- Net balance: `GET /customers/{id}/linked-balance`
- Offset: `POST /customers/{id}/offset-supplier`

Creates offset payments on both sides (`payment_method: offset`).

### 7. Branch finance

When stock is transferred between branches, an optional **inter-branch charge** can be recorded.

- `BranchFinancialEntry` — charge or payment between branches
- `GET /branch-finance/balances` — who owes whom

### 8. Owner capital

- `CompanySetting.capital_amount` — recorded business capital
- `POST /settings/capital/cash-out` — owner withdraws cash
- Dashboard shows `business_capital` and rough `capital_estimated_available`

### 9. Dashboard & reports

| Endpoint | Service | What it shows |
|----------|---------|---------------|
| `GET /dashboard/summary` | `DashboardQueryService` | Receivables, payables, stock value, **this week** profit |
| `GET /dashboard/sales` | `DashboardQueryService` | Breakdown by category/branch |
| `GET /dashboard/receivables` | — | Credit customers with balance |
| `GET /dashboard/payables` | — | Due/overdue installments |
| `GET /reports/financial` | `ReportQueryService` | P&L for custom date range |

Dashboard summary is **cached 5 minutes**; cache is cleared after any money/stock change.

---

## Financial model

The system does **not** use a full double-entry ledger. It tracks:

1. **Balances** — `outstanding_balance`, `total_debt`, stock at cost, capital  
2. **Performance metrics** — revenue and profit from invoices and returns  

### Profit calculation (`FinancialMetricsService`)

For a date range (dashboard = **current week**):

| Field | Formula |
|-------|---------|
| `revenue` | Sum of invoice `subtotal` |
| `discount` | Sum of invoice discounts |
| `customer_refunds` | Sum of completed customer return `total_value` (refund_cash, writeoff, credit_note) |
| `net_sales` | Invoice totals − customer refunds |
| `gross_profit` | Σ `(unit_price − cost_price) × qty` on invoice lines |
| `profit` | gross_profit − discount − **margin lost on returns** |

**Important:** Return profit impact uses **margin only** `(sell − cost) × qty`, not full sale price — so returns are not double-counted against profit and net sales.

### Estimated available cash (`CapitalService::financingSnapshot`)

```
capital − inventory_at_cost − receivables − supplier_debt
```

This is approximate, not a bank ledger.

---

## Authentication & roles

### Login

```
POST /api/v1/auth/login
{ "email": "...", "password": "..." }
→ { "token", "token_type", "expires_in", "user" }
```

Implemented in `AuthService` using Laravel Passport `createToken('flutter')`.

### Roles (`UserRole`)

| Role | Typical access |
|------|----------------|
| `admin` | Everything, all branches, capital, users |
| `manager` | Operations, payments, purchases, returns |
| `salesperson` | Sales, customers (branch-scoped) |
| `warehouse` | Inventory, receive PO, transfers (branch-scoped) |

Non-admin users with `branch_id` are restricted via `BranchVisibility` on queries and reports.

---

## Database & models

- **Primary keys:** UUID strings (`HasUuids` on models)
- **Money:** `decimal(2)` columns; calculations often use `bcadd` / `bcsub` for precision
- **Soft deactivation:** many entities use `is_active = false` instead of hard delete

### Core entity relationships (simplified)

```
Branch ──┬── Stock ── Part
         ├── Invoice ── Customer
         ├── PurchaseOrder ── Supplier
         └── StockTransfer

Customer ── linked_supplier_id ── Supplier
Invoice ── InvoiceItem ── Part
PurchaseOrder ── SupplierInstallment
CustomerPayment / SaturdaySettlement → reduces Invoice balance
ProductReturn ── ReturnItem ── Part
```

### Migrations (order)

1. Users, branches, parts, categories  
2. Customers, suppliers  
3. Stock, transfers, movements  
4. Invoices, settlements  
5. Purchase orders, installments  
6. Returns, audit logs  
7. Company settings, capital, cash-out  
8. Branch financial entries, OAuth (Passport)  
9. Customer payments, contra settlements (recent)

Fresh install: `php artisan migrate:fresh --seed`

---

## Background jobs & schedule

Defined in `bootstrap/app.php`:

| Command | Schedule | Purpose |
|---------|----------|---------|
| `frostparts:dashboard-warm` | Every 5 min | Pre-warm dashboard cache |
| `frostparts:overdue-installments` | Daily midnight | Overdue installment handling |
| `frostparts:settlement-reminder` | Friday 18:00 | Credit settlement reminder |
| `frostparts:low-stock-digest` | Daily 08:00 | Low stock notifications |

---

## How to add a new feature

Follow this order:

1. **Migration** — new table/columns if needed  
2. **Model** + **Enum** — if new statuses/types  
3. **Repository interface + Eloquent class** — register in `AppServiceProvider`  
4. **Service** — business logic and transactions  
5. **Form Request** — validation under `app/Http/Requests/Api/V1/{Domain}/`  
6. **Transformer** + **Resource** — JSON shape  
7. **Controller** — thin action methods  
8. **Route** in `routes/api.php` with correct `role` middleware  
9. **Feature test** in `tests/Feature/`  

Example checklist for `POST /widgets`:

```
routes/api.php
  → WidgetController@store
    → StoreWidgetRequest (validates)
    → WidgetService::create()
      → WidgetRepository::create()
    → WidgetResource
      → WidgetTransformer
```

---

## Related documentation

| Doc | Topic |
|-----|--------|
| [flutter-dashboard-transactions-guide.md](./flutter-dashboard-transactions-guide.md) | Dashboard fields & transaction recipes |
| [flutter-client-notes-june-2026.md](./flutter-client-notes-june-2026.md) | Partial payments, returns profit, contra settlement |
| [flutter-owner-cash-out.md](./flutter-owner-cash-out.md) | Owner capital withdrawal |
| [customer-returns-ar.md](./customer-returns-ar.md) | Return resolutions (Arabic) |
| [branch-finance-api.md](./branch-finance-api.md) | Inter-branch finance API |

---

## Quick reference — main API groups

```
/auth/*              Login, logout, me
/users/*             User management (admin)
/branches/*          Branches
/parts/*             Parts catalog
/part-categories/*   Categories
/inventory/*         Stock levels & adjust
/transfers/*         Inter-branch stock transfers
/customers/*         Customers, balance, payments, contra
/invoices/*          Sales
/settlements/*       Saturday credit settlements
/suppliers/*         Suppliers & debt
/purchases/*         Purchase orders
/installments/*      Supplier installment payments
/returns/*           Product returns
/settings/capital/*  Business capital
/branch-finance/*    Inter-branch charges
/dashboard/*         Live shop overview
/reports/*           Historical reports & charts
```

---

*Last updated: June 2026 — reflects repository + Form Request architecture refactor.*
