# Flutter — Per-branch data isolation (full app model)

**Audience:** Flutter Windows app (`erd_rezzer`) developers  
**Backend:** ERB-Frezzer API v1  
**Date:** June 2026

This document is the **master reference** for how the shop is split by branch. A manager assigned to **Branch C** must only see Branch C data — parts, warehouse, customers, suppliers, sales, purchases, dashboard, reports. The API enforces this **server-side**; the Flutter app must mirror the same rules in UI and API calls.

---

## 1. Core idea

```
┌─────────────────────────────────────────────────────────────┐
│  Branch A          Branch B          Branch C               │
│  ├─ parts          ├─ parts          ├─ parts               │
│  ├─ stock          ├─ stock          ├─ stock               │
│  ├─ customers      ├─ customers      ├─ customers           │
│  ├─ suppliers      ├─ suppliers      ├─ suppliers           │
│  ├─ invoices       ├─ invoices       ├─ invoices            │
│  └─ purchases      └─ purchases      └─ purchases           │
└─────────────────────────────────────────────────────────────┘
         ▲                    ▲                    ▲
         │                    │                    │
    Manager A            Manager B            Manager C
    (locked)             (locked)             (locked)
```

Each branch is a **separate business island**. Same part code or supplier name in two branches = **two different records** (different UUIDs).

**Admin** can view one branch, switch branches, or see **all branches** aggregated.

---

## 2. Roles & what they see

| Role | `user.branch_id` | Branch dropdown | Effective data scope |
|------|------------------|-----------------|----------------------|
| **admin** | usually `null` | Yes — All branches / Branch A / B / C | Selected branch, or **all** if none selected |
| **manager** | required (e.g. Branch C) | **Hidden** | **Always Branch C only** |
| **salesperson** | required | **Hidden** | **Always their branch only** |
| **warehouse** | required | **Hidden** | **Always their branch only** |

### Server lock (cannot be bypassed)

If a manager sends `GET /parts?branch_id=<other-branch-uuid>`, the API **ignores** it and still returns only their assigned branch. Tested in `AdminBranchFilterTest`.

Non-admin users **must not** show a branch picker. Showing one gives a false impression they can switch branches.

---

## 3. Login — read these fields first

After `POST /auth/login` or `GET /auth/me`:

```json
{
  "id": "…",
  "name": "Ahmed",
  "role": "manager",
  "branch_id": "019ebecf-cc58-71e9-a7ff-f262147240b6",
  "can_select_branch": false,
  "accessible_branch_ids": ["019ebecf-cc58-71e9-a7ff-f262147240b6"],
  "branch": {
    "id": "019ebecf-cc58-71e9-a7ff-f262147240b6",
    "name": "التاني"
  }
}
```

| Field | Flutter usage |
|-------|-----------------|
| `branch_id` | Default branch context for all API calls (non-admin) |
| `can_select_branch` | `true` only for admin → show branch dropdown |
| `accessible_branch_ids` | `null` = admin (all branches); else single UUID list |
| `branch.name` | Show in app bar: `Branch: التاني` |

```dart
class AuthUser {
  final String role;
  final String? branchId;
  final bool canSelectBranch;
  final List<String>? accessibleBranchIds;

  bool get isAdmin => role == 'admin';
  bool get isBranchLocked => !canSelectBranch && branchId != null;
}
```

---

## 4. How the API resolves branch (every request)

Middleware `branch.filter` runs on all authenticated routes.

```
Request: ?branch_id=  OR  X-Branch-Id header  OR  JSON body branch_id
                    ↓
         resolveBranchId(user, requested)
                    ↓
    Non-admin  →  always user.branch_id  (request ignored)
    Admin      →  requested branch, or null = all branches
                    ↓
         resolved_branch_id on request
                    ↓
    Repositories filter lists / tag creates
```

### Three ways to send branch (admin + creates)

```dart
// 1. Query (most common for GET)
dio.get('/parts', queryParameters: {'branch_id': branchId});

// 2. Header (works on any method)
options.headers['X-Branch-Id'] = branchId;

// 3. JSON body (POST/PUT)
{'branch_id': branchId, ...otherFields}
```

**Rule:** For **create** endpoints, use the **same branch** on POST as on GET. See [flutter-branch-switching-guide.md](./flutter-branch-switching-guide.md).

---

## 5. Module-by-module reference

### Branch-owned catalog (filtered by `*.branch_id`)

| Module | List endpoint | Create needs branch? | DB column |
|--------|---------------|----------------------|-----------|
| **Parts** | `GET /parts` | ✅ Required | `parts.branch_id` |
| **Suppliers** | `GET /suppliers` | ✅ Required | `suppliers.branch_id` |
| **Customers** | `GET /customers` | ✅ Auto from user/filter | `customers.branch_id` |

Manager at Branch C → lists return **only** rows where `branch_id = C`.

### Branch-owned transactions (filtered by `branch_id` on transaction)

| Module | List endpoint | Create `branch_id` |
|--------|---------------|-------------------|
| **Invoices / sales** | `GET /invoices` | Required in body |
| **Purchase orders** | `GET /purchases` | Required in body |
| **Returns** | `GET /returns` | Required in body |
| **Stock / warehouse** | `GET /inventory` | Required on adjust |
| **Stock transfers** | `GET /transfers` | From/to branch in body |

### Branch-scoped via related records

| Module | How branch filter applies |
|--------|---------------------------|
| **Supplier installments** | PO `purchase_orders.branch_id` |
| **Settlements (credit)** | Customer `customers.branch_id` |
| **Supplier debt** | POs + installments for active branch |
| **Dashboard** | All widgets use active branch |
| **Reports** | `?branch_id=` on financial, sales, parts chart |

### Users & branches (admin screens)

| Module | Scope |
|--------|--------|
| **Users** (`GET /users`) | Filtered by active branch (admin) |
| **Branches active** (`GET /branches/active`) | Non-admin: **only their branch** |
| **Branches list** (`GET /branches`) | Admin: all branches |

### Shared reference data (same for all branches)

| Module | Notes |
|--------|--------|
| **Part categories** | `GET /part-categories` — global lookup (compressor, seals, …) |
| **Part units** | `GET /part-units` — global enum (pc, kg, …) |

These are **not** duplicated per branch. Parts **use** categories; each part row is still per branch.

### Admin-only / capital

| Module | Branch scope |
|--------|--------------|
| **Business capital** | Per branch (`branches.capital_amount`) |
| **Owner cash-out** | Per branch |
| **Branch finance** | Inter-branch ledger; staff see entries involving their branch |

---

## 6. Flutter app architecture

### 6.1 Branch context service

```dart
class BranchContext {
  BranchContext(this._auth);

  final AuthService _auth;
  String? _adminSelectedBranchId; // null = all branches (admin only)

  String? get effectiveBranchId {
    if (_auth.user.isBranchLocked) {
      return _auth.user.branchId;
    }
    return _adminSelectedBranchId;
  }

  Map<String, dynamic> queryParams() => {
    if (effectiveBranchId != null) 'branch_id': effectiveBranchId!,
  };
}
```

### 6.2 Dio interceptor (recommended)

```dart
dio.interceptors.add(InterceptorsWrapper(
  onRequest: (options, handler) {
    final branchId = branchContext.effectiveBranchId;
    if (branchId != null) {
      options.queryParameters['branch_id'] ??= branchId;
      options.headers['X-Branch-Id'] ??= branchId;
      if (options.data is Map<String, dynamic>) {
        (options.data as Map<String, dynamic>)['branch_id'] ??= branchId;
      }
    }
    handler.next(options);
  },
));
```

For **branch-locked users**, `effectiveBranchId` is always `user.branch_id` — interceptor adds it automatically.

For **admin with no selection**, omit `branch_id` → API returns aggregated / all-branch data.

### 6.3 UI rules

| User | Branch dropdown | App bar label |
|------|-----------------|---------------|
| Admin | Show on dashboard + settings | `All branches` or `Branch: Main` |
| Manager / sales / warehouse | **Never show** | `Branch: {user.branch.name}` (read-only) |

```dart
if (user.canSelectBranch) {
  BranchFilterDropdown(
    branches: branches,
    value: selectedBranchId,
    onChanged: (id) {
      setState(() => selectedBranchId = id);
      ref.invalidate(allBranchScopedProviders);
    },
  );
} else if (user.branch != null) {
  Chip(label: Text('Branch: ${user.branch!.name}'));
}
```

### 6.4 After login bootstrap

```dart
Future<void> onLoginSuccess(User user) async {
  if (user.isBranchLocked) {
    branchContext.lockTo(user.branchId!);
    // No branch dropdown; all screens use user.branchId
  } else {
    await loadBranches(); // GET /branches/active
    restoreSavedAdminFilter(); // optional SharedPreferences
  }
  await syncCatalog(); // parts, customers, suppliers for effective branch
}
```

---

## 7. Create operations checklist

Every **create** must tag the active branch. Server sets `branch_id` from resolved filter.

| Screen | POST endpoint | Branch source |
|--------|---------------|---------------|
| Add part | `POST /parts` | Query + body (required) |
| Add supplier | `POST /suppliers` | Query + body (required) |
| Add customer | `POST /customers` | Query + body (manager auto) |
| New invoice / POS | `POST /invoices` | `branch_id` in body |
| New purchase | `POST /purchases` | `branch_id` in body + supplier same branch |
| Stock adjust | `POST /inventory/adjust` | `branch_id` in body |
| Return | `POST /returns` | `branch_id` in body |

**Manager:** branch is implicit (`user.branch_id`) — still send on POST for consistency.

**Admin:** must select a branch before create, or POST returns **422** (parts/suppliers) or saves `branch_id: null` (customers — hidden from branch-filtered lists).

---

## 8. Screen-by-screen (what manager at Branch C sees)

| App screen | Data shown | Empty when |
|------------|------------|------------|
| Dashboard | Branch C KPIs only | New branch, no activity |
| Parts | Branch C catalog | No parts created in C |
| Warehouse | Branch C stock rows | No parts in C |
| Customers | Branch C customers | No customers registered in C |
| Suppliers | Branch C suppliers | No suppliers in C |
| POS | Branch C parts + customers | Same as above |
| Invoices | Branch C sales | No sales yet |
| Purchases | Branch C POs | No purchases |
| Installments | Branch C PO installments | No credit POs |
| Returns | Branch C returns | None filed |
| Reports | Branch C numbers | No data in period |
| Users | N/A (admin only) | — |

Manager **never** sees Branch A or B data, even if they know another branch UUID.

---

## 9. Admin vs manager — same API, different UX

| Action | Admin | Manager (Branch C) |
|--------|-------|---------------------|
| Switch branch | Dropdown → `?branch_id=` | Not possible |
| View all branches | Omit `branch_id` on dashboard | Not possible |
| Create part | Must pick branch first | Auto Branch C |
| List parts | Filtered to selection | Always Branch C |
| `GET /branches/active` | All active branches | **Only Branch C** |

---

## 10. Common Flutter mistakes

### ❌ Showing branch dropdown to manager

Causes confusion and bugs when saved filter disagrees with server lock.

### ❌ `branch_id` on GET but not POST

Parts/suppliers return **422**; lists stay empty after “successful” UI flow.

### ❌ Using customer `branch_id` from API as dropdown value

Use `GET /branches/active` for dropdown items, not customer fields.

### ❌ Caching data across branch switch (admin)

When admin changes branch, **invalidate all** list providers and re-fetch.

### ❌ Assuming shared global catalog

Parts, suppliers, and customers are **per branch** (customers filtered by `customers.branch_id` when branch context is active).

---

## 11. Verify with test users

Seed / create users:

| Email (example) | Role | Branch |
|-----------------|------|--------|
| `admin@example.com` | admin | none |
| `manager@branch-c.example.com` | manager | Branch C |

**Manager test:**

1. Login → `can_select_branch: false`, `branch_id` = C  
2. `GET /parts` → only Branch C parts  
3. `GET /parts?branch_id=<branch-A>` → **still** only Branch C (server ignores)  
4. Create part → appears in list without picking branch in UI  
5. `GET /branches/active` → **one** branch (C)

**Admin test:**

1. No filter → dashboard aggregates all branches  
2. Select Branch C → same lists as manager  
3. Create part with `?branch_id=C` → tagged to C  

Backend tests:

```bash
php artisan test --filter=AdminBranchFilterTest
php artisan test --filter=PartBranchWarehouseTest
php artisan test --filter=SupplierBranchTest
php artisan test --filter=CustomerBranchFilterTest
```

---

## 12. Quick reference — is it branch-scoped?

| ✅ Per branch | ⚠️ Shared reference | 🔒 Admin-only |
|--------------|---------------------|---------------|
| Parts | Part categories | Users CRUD |
| Stock / inventory | Part units | Capital settings |
| Customers | | All branches list |
| Suppliers | | |
| Invoices | | |
| Purchases | | |
| Returns | | |
| Transfers (involving branch) | | |
| Dashboard | | |
| Reports (with filter) | | |
| Installments | | |
| Settlements | | |

---

## 13. Related docs

| Document | Topic |
|----------|--------|
| [flutter-branch-switching-guide.md](./flutter-branch-switching-guide.md) | GET vs POST, branch param, Dio patterns |
| [flutter-admin-branch-filter.md](./flutter-admin-branch-filter.md) | Admin dropdown on dashboard |
| [flutter-parts-branch-warehouse.md](./flutter-parts-branch-warehouse.md) | Parts + stock model |
| [flutter-customer-settlement-cycle.md](./flutter-customer-settlement-cycle.md) | Credit settlement daily/weekly |
| [flutter-app-fixes.md](./flutter-app-fixes.md) | Known API alignment fixes |

---

## 14. Summary for developers

1. **One branch = one isolated shop** — parts, stock, customers, suppliers, sales, purchases.  
2. **Manager / sales / warehouse** are **locked** to `user.branch_id` — hide branch dropdown.  
3. **Admin** picks branch (or all) — pass `branch_id` on every call when filtered.  
4. Use **`can_select_branch`** and **`accessible_branch_ids`** from login — do not hard-code roles.  
5. **POST must carry branch** same as GET for creates.  
6. **Categories & units** are the only shared catalogs; everything else is branch data.
