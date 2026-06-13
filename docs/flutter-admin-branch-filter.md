# Flutter — Admin branch filter (dashboard & whole app)

**Audience:** Flutter Windows (`erd_rezzer`) developers  
**Date:** June 2026

When an **admin** selects a branch from a dropdown, the entire app should scope data to that branch: warehouse stock, users, customers, invoices, purchases, dashboard numbers, reports, and owner cash-out profit.

Non-admin users (`manager`, `salesperson`, `warehouse`) **never** see the dropdown — they are always locked to their assigned `user.branch_id`.

---

## How it works (backend)

1. Middleware reads optional `?branch_id={uuid}` on **every authenticated API call**.
2. For **admin**: uses that branch when provided; omit param = **all branches**.
3. For **non-admin**: ignores `branch_id` query; always uses their assigned branch.

```
Admin + no branch_id     → all branches
Admin + branch_id=X      → only branch X
Salesperson              → always their branch (dropdown hidden)
```

---

## Flutter implementation

### 1. Branch dropdown (admin only)

```dart
if (user.role == 'admin') {
  // Dropdown: "All branches" + GET /branches/active
  // Store selectedBranchId in app state (nullable)
}
```

Place on **dashboard tab** (and optionally persist in shared app state so inventory/sales screens follow).

### 2. Pass `branch_id` on every API call

When `selectedBranchId != null`, append to query string:

```dart
Map<String, dynamic> branchQuery() => {
  if (selectedBranchId != null) 'branch_id': selectedBranchId,
};

// Example
dio.get('/dashboard/summary', queryParameters: branchQuery());
dio.get('/invoices', queryParameters: {...filters, ...branchQuery()});
dio.get('/inventory', queryParameters: branchQuery());
dio.get('/users', queryParameters: branchQuery());
```

**Rule:** Any screen that lists branch-specific data must include the same `branch_id` while the filter is active.

### 3. Refresh after branch change

When admin changes dropdown:

1. Update global `selectedBranchId`
2. Re-fetch dashboard summary, inventory, receivables, payables, sales
3. Re-fetch current screen list (invoices, customers, users, etc.)

---

## How parts, warehouse & suppliers work (important)

The shop **feels** like each branch has its own parts, warehouse, and suppliers. In the database there are two layers:

### 1. Branch-specific (quantities & transactions) — filtered by `?branch_id=`

| What you see | How it is stored |
|--------------|------------------|
| **Warehouse / stock qty** | `stock` table: one row per `part_id` + `branch_id` |
| **Sales** | `invoices.branch_id` |
| **Purchases** | `purchase_orders.branch_id` |
| **Supplier installments due** | Linked to PO → branch |
| **Returns** | `returns.branch_id` |

When admin picks a branch, **inventory, sales, purchases, payables, and profit** are scoped to that branch.

### 2. Branch-owned parts + warehouse stock

| Table | Has `branch_id`? | Meaning |
|-------|------------------|---------|
| `parts` | **Yes** | Each part belongs to **one branch** (`parts.branch_id`). Same `code` can exist in two branches as separate rows. |
| `stock` | **Yes** (with `part_id`) | **Warehouse quantity** for that part in that branch. Created automatically when a part is created. |
| `suppliers` | **No** | Shared supplier name; branch activity is on purchase orders |

So: **part definition** and **warehouse row** are both tied to a branch. Branch A and Branch B each have their own part catalogs and stock levels.

### 3. List APIs with branch filter

With `?branch_id=` active:

| Endpoint | Filter behaviour |
|----------|----------------|
| `GET /parts` | Parts where `parts.branch_id` = that branch |
| `GET /inventory` | Stock rows for that branch (`stock.branch_id`) |
| `GET /suppliers` | Suppliers with at least one **purchase order** in that branch |
| `GET /suppliers/{id}/debt` | POs + installments for that branch only |

### Create part (must include branch)

```http
POST /api/v1/parts?branch_id={uuid}
{
  "code": "COMP-001",
  "name": "...",
  "category_key": "compressor",
  "unit": "pc",
  "sell_price": 100,
  "cost_price": 50,
  "min_stock": 10,
  "initial_quantity": 25
}
```

- Server sets `parts.branch_id` from query/body/header branch filter.
- Server creates a **`stock`** row for that branch (warehouse).
- Optional `initial_quantity` adds opening stock in one call.

See [flutter-parts-branch-warehouse.md](./flutter-parts-branch-warehouse.md).

---

## What is filtered per branch (summary)

| Area | Scoped? | Notes |
|------|---------|--------|
| Dashboard summary | ✅ | Stock, receivables, payables, weekly profit |
| Inventory / warehouse | ✅ | Quantities per `stock.branch_id` |
| Parts list | ✅ | `parts.branch_id` |
| Suppliers list | ✅ | Suppliers with POs in that branch |
| Supplier debt view | ✅ | POs/installments for that branch |
| Invoices | ✅ | `invoices.branch_id` |
| Purchases / installments | ✅ | `purchase_orders.branch_id` |
| Returns | ✅ | `returns.branch_id` |
| Users list | ✅ | `users.branch_id` |
| Customers list | — | Shared catalog; all customers returned (`branch_id` stored when known; receivables stay branch-scoped) |
| Business capital (`capital_amount`) | ✅ | Stored per branch (`branches.capital_amount`); dashboard sums all branches when no filter |
| Owner cash-out profit limit | ✅ | Profit & withdrawals for selected branch |

### Receivables nuance

With a branch selected, `outstanding_balance` on dashboard/receivables is the **unpaid credit invoice balance in that branch only**, not the customer's global balance.

---

## API reference

### Dashboard (all accept `?branch_id=`)

```http
GET /api/v1/dashboard/summary?branch_id={uuid}
GET /api/v1/dashboard/inventory?branch_id={uuid}
GET /api/v1/dashboard/receivables?branch_id={uuid}
GET /api/v1/dashboard/payables?branch_id={uuid}
GET /api/v1/dashboard/sales?branch_id={uuid}
```

Response `summary` includes `"branch_id": "uuid"` or `null` (all branches).

### Reports

```http
GET /api/v1/reports/financial?from=&to=&branch_id={uuid}
GET /api/v1/reports/parts-sales-chart?branch_id={uuid}
```

### Capital / cash out (admin)

Each branch has its own `capital_amount`. With no branch filter, dashboard `business_capital` is the **sum** of all branches.

```http
GET /api/v1/settings/capital?branch_id={uuid}
PUT /api/v1/settings/capital
{ "capital_amount": 100000, "branch_id": "{uuid}", "reason": "Opening capital" }
POST /api/v1/settings/capital/cash-out
{ "amount": 5000, "branch_id": "{uuid}" }
```

See [flutter-owner-cash-out-profit-validation.md](./flutter-owner-cash-out-profit-validation.md).

### Branches list for dropdown

```http
GET /api/v1/branches/active
```

---

## UI mockup

```
┌──────────────────────────────────────────────┐
│  Dashboard                    [ Branch ▼ ]   │  ← admin only
│                               All branches │
│                               Main Shop    │
│                               Warehouse 2  │
├──────────────────────────────────────────────┤
│  Weekly profit: 12,500 EGP                   │
│  Stock value:    8,200 EGP  (this branch)    │
│  Receivables:    3,000 EGP  (this branch)    │
└──────────────────────────────────────────────┘
```

Show a chip when filtered: `Branch: Main Shop` with clear (×) to reset to all branches.

---

## ⚠️ Developer warnings

1. **Admin without `branch_id`** = aggregated numbers across all branches — do not mix with branch-filtered screens in the same view without clearing state.
2. **Non-admin** must not send a different `branch_id` — API ignores it; do not show the dropdown.
3. After creating an invoice/PO, pass the same `branch_id` filter when refreshing lists.
4. **Customers** list is not branch-filtered — new customers appear immediately even if `branch_id` was not sent on POST. Receivables and invoices remain branch-scoped.

---

## Tests

```bash
php artisan test --filter=AdminBranchFilterTest
```

---

## Related

- [flutter-dashboard-transactions-guide.md](./flutter-dashboard-transactions-guide.md)
- [architecture.md](./architecture.md)
