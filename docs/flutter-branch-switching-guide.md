# Flutter — Branch switching (parts, warehouse, customers, suppliers)

**Audience:** Flutter Windows app (`erd_rezzer`) developers  
**Backend:** `https://api.tppower.shop/api/v1`  
**Date:** June 2026

This guide explains how **branch selection** works end-to-end: what the API expects on **GET** vs **POST**, why lists go empty after switching branches, and how to wire one global branch context in the app so create and list stay in sync.

---

## 1. The problem (what you see in logs)

Typical failure pattern from production:

```
GET  /parts?branch_id=019ebecf-…&per_page=500     → 200, data: []
POST /parts  body={code, name, …}                 → 422 branch_id is required
GET  /customers?branch_id=019ebecf-…              → 200, data: []
POST /customers body={name, type, …}              → 201 but branch_id: null
```

| Symptom | Cause |
|---------|--------|
| Parts list always empty | POST never succeeded (422), or you switched branch and that branch has no parts yet |
| Part create 422 | **POST** did not send `branch_id` (query, body, or header) while **GET** did |
| Customer saved but “wrong branch” | **POST** missing branch context; admin users have no assigned branch |
| Supplier missing after create | New suppliers are **global**; branch list only shows suppliers with purchase orders in that branch |
| Inventory empty | No parts/stock rows exist for the selected branch yet |
| POS `branchName=null` | App has `branchId` but did not resolve name from `GET /branches/active` |

**Root cause in the app:** branch is passed on **read** requests (`queryParameters`) but omitted on **write** requests (`POST`/`PUT`). The API treats GET and POST independently — both must carry the same branch context.

---

## 2. One rule for the whole app

```dart
/// Single source of truth — admin dropdown or user's assigned branch.
String? selectedBranchId;

Map<String, dynamic> branchQuery() => {
  if (selectedBranchId != null) 'branch_id': selectedBranchId,
};

Map<String, String> branchHeaders() => {
  if (selectedBranchId != null) 'X-Branch-Id': selectedBranchId!,
};
```

### Apply branch on **every** authenticated call

| HTTP method | Where to send `branch_id` |
|-------------|---------------------------|
| GET | `queryParameters: branchQuery()` |
| POST / PUT / PATCH | **All three work:** `queryParameters: branchQuery()`, and/or `'branch_id'` in JSON body, and/or `headers: branchHeaders()` |
| DELETE | Same as GET — use query or header |

**Recommended pattern:** use a Dio interceptor so developers cannot forget:

```dart
dio.interceptors.add(InterceptorsWrapper(
  onRequest: (options, handler) {
    final branchId = branchContext.selectedBranchId;
    if (branchId != null) {
      options.queryParameters['branch_id'] ??= branchId;
      options.headers['X-Branch-Id'] ??= branchId;
      if (options.method == 'POST' || options.method == 'PUT' || options.method == 'PATCH') {
        if (options.data is Map<String, dynamic>) {
          (options.data as Map<String, dynamic>)['branch_id'] ??= branchId;
        }
      }
    }
    handler.next(options);
  },
));
```

### When admin changes branch

1. Update `selectedBranchId`
2. Clear in-memory caches (parts, customers, inventory, suppliers)
3. Re-fetch the **current screen** and dashboard widgets
4. Update UI chip: `Branch: التاني` with clear (×) to reset

Non-admin users (`salesperson`, `warehouse`, `manager`): **hide** the dropdown; API always uses `user.branch_id` and ignores a different `branch_id`.

---

## 3. How the backend resolves branch

Middleware `branch.filter` runs on all authenticated routes.

```
Client sends branch_id (query | X-Branch-Id header | JSON body)
        ↓
Admin user     → use requested branch, or null = all branches
Non-admin user → always forced to user.branch_id (request ignored)
        ↓
resolved_branch_id stored on request
        ↓
Repositories / controllers read BranchVisibility::activeBranchId()
```

| Role | `?branch_id=` sent | Effective branch |
|------|-------------------|------------------|
| Admin | omitted | `null` → **all branches** (aggregated lists / totals) |
| Admin | `uuid` | that branch only |
| Salesperson | anything | always `user.branch_id` |

---

## 4. Entity-by-entity reference

### 4.1 Parts — **strictly per branch**

Each branch has its **own part catalog**. Same `code` in two branches = two different UUIDs.

| | GET (list) | POST (create) |
|---|-----------|---------------|
| **Filtered?** | Yes — `parts.branch_id = active branch` | Branch **required** |
| **Endpoint** | `GET /parts?branch_id={uuid}` | `POST /parts?branch_id={uuid}` |
| **If branch missing (admin)** | Returns parts from **all** branches | **422** `branch_id is required` |
| **Response field** | Each row has `branch_id` | `branch_id` must be non-null |

**Create example:**

```http
POST /api/v1/parts?branch_id=019ebecf-cc58-71e9-a7ff-f262147240b6
Authorization: Bearer …
Content-Type: application/json

{
  "code": "1351",
  "name": "جامد",
  "category_key": "compressor",
  "unit": "pc",
  "sell_price": 100,
  "min_stock": 30,
  "initial_quantity": 0,
  "is_active": true
}
```

**Flutter `part_repository.dart` (required):**

```dart
Future<Part> create(Map<String, dynamic> payload) async {
  final res = await dio.post(
    '/parts',
    queryParameters: branchQuery(),
    data: payload,
  );
  return Part.fromJson(res.data);
}
```

**After branch switch:** expect a **different** parts list. Branch B will not show Branch A’s parts.

See also: [flutter-add-part.md](./flutter-add-part.md), [flutter-parts-branch-warehouse.md](./flutter-parts-branch-warehouse.md).

---

### 4.2 Warehouse / inventory — **quantities per branch**

Warehouse = `stock` table (`part_id` + `branch_id` + `quantity`).

| | GET | POST adjust |
|---|-----|-------------|
| **Filtered?** | Yes — `stock.branch_id` | `branch_id` **required in body** |
| **Endpoints** | `GET /inventory?branch_id={uuid}` | `POST /inventory/adjust` |
| | `GET /inventory/{branchId}?branch_id={uuid}` | |
| **Notes** | Path `{branchId}` is the warehouse branch; still pass filter for admin | Part must belong to same branch |

**Creating a part** automatically creates a `stock` row (qty 0). Use `initial_quantity` on part create for opening stock.

**Inventory list empty** until at least one part exists **in that branch** with stock.

```http
POST /api/v1/inventory/adjust
{
  "part_id": "…",
  "branch_id": "019ebecf-…",
  "quantity_delta": 10,
  "unit_cost": 50,
  "reason": "Manual adjustment"
}
```

**Flutter:** POS and warehouse screens must use the **same** `selectedBranchId` as parts list.

---

### 4.3 Customers — **per branch**

Each customer belongs to **one branch** (`customers.branch_id`).

| | GET (list) | POST (create) |
|---|-----------|---------------|
| **Filtered?** | Yes — `customers.branch_id = active branch` | Sets `branch_id` from branch context |
| **Endpoint** | `GET /customers?branch_id={uuid}` | `POST /customers?branch_id={uuid}` |
| **Manager** | Always their branch (server-enforced) | Auto-tagged to their branch |

**Create — send branch for admin; manager is automatic:**

```http
POST /api/v1/customers?branch_id=019ebecf-cc58-71e9-a7ff-f262147240b6
{
  "name": "Ahmed",
  "type": "credit",
  "phone": "010…",
  "credit_limit": 5000,
  "settlement_cycle": "weekly"
}
```

**Flutter `customer_repository.dart`:**

```dart
Future<Customer> create(Map<String, dynamic> payload) async {
  final res = await dio.post(
    '/customers',
    queryParameters: branchQuery(),
    data: payload,
  );
  return Customer.fromJson(res.data);
}
```

**After branch switch:** customer list **changes** like parts and suppliers.

See: [flutter-customer-settlement-cycle.md](./flutter-customer-settlement-cycle.md) · [flutter-per-branch-isolation.md](./flutter-per-branch-isolation.md).

---

### 4.4 Suppliers — **per branch (like parts)**

Each supplier belongs to **one branch** (`suppliers.branch_id`). Branch A and Branch B each have their own supplier lists.

| | GET (list) | POST (create) |
|---|-----------|---------------|
| **Filtered?** | Yes — `suppliers.branch_id = active branch` | Branch **required** |
| **Endpoint** | `GET /suppliers?branch_id={uuid}` | `POST /suppliers?branch_id={uuid}` |
| **Debt view** | `GET /suppliers/{id}/debt?branch_id={uuid}` | POs/installments still scoped to branch |
| **Response field** | Each row has `branch_id` | `branch_id` must be non-null |

**Create supplier:**

```http
POST /api/v1/suppliers?branch_id=019ebecf-cc58-71e9-a7ff-f262147240b6
Authorization: Bearer …
Content-Type: application/json

{
  "name": "Compressor Co",
  "contact_person": "Ali",
  "phone": "010…"
}
```

**Flutter `supplier_repository.dart`:**

```dart
Future<Supplier> create(Map<String, dynamic> payload) async {
  final res = await dio.post(
    '/suppliers',
    queryParameters: branchQuery(),
    data: payload,
  );
  return Supplier.fromJson(res.data);
}
```

**Purchase orders:** `supplier_id` must belong to the same branch as `branch_id` on the PO — otherwise **422**.

**After branch switch:** expect a **different** supplier list, same as parts.

---

## 5. Quick comparison table

| Resource | List scoped to branch? | Create needs branch? | `branch_id` column |
|----------|------------------------|----------------------|--------------------|
| **Parts** | ✅ Yes | ✅ **Required** | `parts.branch_id` |
| **Inventory / stock** | ✅ Yes | ✅ Required on adjust | `stock.branch_id` |
| **Customers** | ✅ Yes | ✅ Auto from user/filter | `customers.branch_id` |
| **Suppliers** | ✅ Yes | ✅ **Required** | `suppliers.branch_id` |
| **Invoices / sales** | ✅ Yes | ✅ Required in body | `invoices.branch_id` |
| **Purchase orders** | ✅ Yes | ✅ Required in body | `purchase_orders.branch_id` |
| **Dashboard** | ✅ Yes | — | — |

---

## 6. Branch switch checklist (Flutter)

Use this when implementing or debugging branch dropdown:

- [ ] `selectedBranchId` stored in app-wide state (Provider / Riverpod / GetX)
- [ ] Dio interceptor OR every repository passes `branchQuery()` on **GET and POST**
- [ ] Admin dropdown populated from `GET /branches/active`
- [ ] Dropdown value validated — if ID not in list, reset to first branch (avoids red `DropdownButton` screen)
- [ ] On branch change: invalidate caches + refetch current screen
- [ ] **Parts screen:** list and create use same branch
- [ ] **Inventory screen:** uses same branch as parts
- [ ] **POS:** `GET /parts?branch_id=` + show branch name from branches list
- [ ] **Customers:** list and create use same branch (`branchQuery()` on POST)
- [ ] **Suppliers:** list and create use same branch (`branchQuery()` on POST)
- [ ] **Dashboard / reports:** all widgets pass `branch_id`

---

## 7. Common mistakes

### Mistake 1 — branch only on GET

```dart
// ❌ Wrong
dio.get('/parts', queryParameters: branchQuery());
dio.post('/parts', data: payload);

// ✅ Correct
dio.post('/parts', queryParameters: branchQuery(), data: payload);
```

This exact bug caused production 422 on part create.

### Mistake 2 — assuming customer list filters like parts

Switching from Main Branch to التاني **still shows the same customers**. Only parts/inventory/supplier-debt views change.

### Mistake 3 — supplier create without branch (same as parts)

```dart
// ❌ Wrong — 422 branch_id is required
dio.post('/suppliers', data: payload);

// ✅ Correct
dio.post('/suppliers', queryParameters: branchQuery(), data: payload);
```

Without branch on POST, supplier create fails and the branch list stays empty.

### Mistake 4 — admin with no branch selected on create

Admin without `branch_id` on POST:

- **Parts:** 422 error
- **Customers:** creates with `branch_id: null` (still appears in list)

Always require branch selection before opening “Add part” / prefer defaulting to first active branch.

### Mistake 5 — stale cache after branch change

Old parts list from Branch A shown while `selectedBranchId` is Branch B. Clear cache on every branch change.

### Mistake 6 — duplicate `/inventory/{id}?branch_id=` confusion

Both path and query refer to the same branch for admin. Use your `selectedBranchId` for both:

```dart
dio.get('/inventory/$branchId', queryParameters: branchQuery());
```

---

## 8. API examples by screen

### Parts screen (Branch التاني)

```dart
await dio.get('/parts', queryParameters: {'per_page': 50, ...branchQuery()});
await dio.get('/part-categories', queryParameters: {'active_only': true, ...branchQuery()});
await dio.post('/parts', queryParameters: branchQuery(), data: formData);
```

### Warehouse screen

```dart
await dio.get('/inventory/${selectedBranchId}', queryParameters: branchQuery());
```

### Customers screen

```dart
await dio.get('/customers', queryParameters: {'per_page': 50, ...branchQuery()});
await dio.post('/customers', queryParameters: branchQuery(), data: formData);
```

### Suppliers screen

```dart
await dio.get('/suppliers', queryParameters: branchQuery());
await dio.post('/suppliers', queryParameters: branchQuery(), data: formData);
await dio.post('/purchases', queryParameters: branchQuery(), data: {
  'supplier_id': id,
  'branch_id': selectedBranchId,
  ...
});
```

### POS

```dart
await dio.get('/parts', queryParameters: {'per_page': 50, ...branchQuery()});
await dio.get('/customers', queryParameters: {'per_page': 50, ...branchQuery()});
// Invoice create must include branch_id in body
```

---

## 9. Error reference

| HTTP | Message | Fix |
|------|---------|-----|
| 422 | `branch_id is required to create a part` | Add `?branch_id=` or body/header on **POST /parts** |
| 422 | `branch_id is required…` (supplier) | Add branch on **POST /suppliers** |
| 422 | `The code has already been taken` | Code exists **in that branch** — use another code or switch branch |
| 422 | `The supplier does not belong to the selected branch` | PO `branch_id` must match supplier's branch |
| 200 empty `data` | — | No rows for that branch yet, or nothing created successfully |
| Dropdown assertion | value not in items | Reload branches; reset `selectedBranchId` if stale |

---

## 10. Backend tests

```bash
php artisan test --filter=SupplierBranchTest
php artisan test --filter=PartBranchWarehouseTest
php artisan test --filter=CustomerBranchFilterTest
php artisan test --filter=AdminBranchFilterTest
```

---

## 11. Related docs

| Doc | Topic |
|-----|--------|
| [flutter-per-branch-isolation.md](./flutter-per-branch-isolation.md) | **Master guide** — manager lock, roles, full module map |
| [flutter-admin-branch-filter.md](./flutter-admin-branch-filter.md) | Dashboard & global branch filter |
| [flutter-add-part.md](./flutter-add-part.md) | Part form & POST payload |
| [flutter-parts-branch-warehouse.md](./flutter-parts-branch-warehouse.md) | Parts + stock model |
| [flutter-customer-settlement-cycle.md](./flutter-customer-settlement-cycle.md) | Daily vs weekly credit customers |
| [flutter-app-fixes.md](./flutter-app-fixes.md) | Known Flutter bug fixes checklist |

---

## 12. Summary for developers

1. **One `selectedBranchId`** drives the whole app.
2. **GET and POST must both carry branch** — use an interceptor or shared helper.
3. **Parts & inventory** are fully per-branch — switching branch = different catalog and warehouse.
4. **Customers** are per-branch — same rules as parts for list/create.
5. **Suppliers** are per-branch — same rules as parts (POST must include branch).
6. Empty lists after switching branch usually mean **that branch has no data yet**, not an API bug — create data with the correct branch on POST first.
