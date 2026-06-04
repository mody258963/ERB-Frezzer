# Flutter Windows app — fixes (API alignment)

Guide for the **erd_rezzer** / FrostParts Windows client. The API is **ERB-Frezzer** (`https://api.tppower.shop/api/v1` or your server).

Related: [flutter-dev-updates-may-2026.md](./flutter-dev-updates-may-2026.md) (dashboard profit, financial reports, admin users/branches), [flutter-windows-recent-updates.md](./flutter-windows-recent-updates.md), [flutter-windows-pos-offline-guide.md](./flutter-windows-pos-offline-guide.md).

---

## 1. Receive purchase goods — use PATCH (not POST)

### Problem

```
HTTP 405 · POST · .../purchases/{id}/receive
The POST method is not supported. Supported methods: PATCH.
```

The app tries **POST**, then **PATCH**. The first call always fails in logs and may confuse users.

### Fix

**File:** `lib/data/repositories/purchase_repository.dart` (or equivalent)

**Before (wrong):**

```dart
Future<PurchaseOrder> receive(String id, {required String branchId}) async {
  try {
    final res = await _dio.post('/purchases/$id/receive', data: {'branch_id': branchId});
    return PurchaseOrder.fromJson(res.data);
  } catch (_) {
    final res = await _dio.patch('/purchases/$id/receive', data: {'branch_id': branchId});
    return PurchaseOrder.fromJson(res.data);
  }
}
```

**After (correct):**

```dart
Future<PurchaseOrder> receive(String id, {required String branchId}) async {
  final res = await _dio.patch(
    '/purchases/$id/receive',
    data: {'branch_id': branchId},
  );
  return PurchaseOrder.fromJson(res.data as Map<String, dynamic>);
}
```

**API:** `PATCH /api/v1/purchases/{id}/receive`  
Optional body: `{ "branch_id": "uuid" }`  
Roles: `admin`, `manager`, `warehouse`

> Note: The server may also accept `POST` after a backend deploy, but **PATCH** is the documented method — use it only.

---

## 2. Dashboard — supplier purchases & installment payments

### Problem

Creating a purchase (e.g. 100,000 EGP in 4 installments) or paying an installment did not change dashboard numbers — the app only showed sales (`weekly_revenue`).

### Fix

**Refresh** `GET /dashboard/summary` after create purchase, receive goods, or pay installment.

Bind these fields (not only sales):

| Field | When it changes |
|-------|-----------------|
| `total_supplier_debt` | PO created (↑) or installment paid (↓) |
| `weekly_purchases_ordered` | PO created this week |
| `weekly_supplier_payments` | Installment paid this week |
| `unpaid_installments_total` | Any unpaid installment balance |
| `total_stock_value_cost` | After **receive** purchase |

**Pay installment:** `POST /installments/{id}/pay` with `{ "payment_method": "cash" }` — hide Pay when `is_paid == true`.

**Payables detail:** `GET /dashboard/payables` → `upcoming_30_days`, `overdue`.

---

## 3. Hide “Receive” when PO already received

### Problem

Tapping **Receive** on an already received PO can add stock twice (older API) or return **422** `Purchase order already received.` (current API).

Your logs show `status=settled` and `received_at` set, but `canReceive=true` still.

### Fix

**File:** `lib/features/purchases/purchases_screen.dart` (and purchase model)

**Model** — parse from list/detail JSON:

```dart
class PurchaseOrder {
  // ...
  final DateTime? receivedAt;
  final String status; // pending | partial | settled | cancelled

  bool get canReceive =>
      receivedAt == null &&
      status != 'settled' &&
      status != 'cancelled';
}
```

```dart
factory PurchaseOrder.fromJson(Map<String, dynamic> json) {
  return PurchaseOrder(
    // ...
    receivedAt: json['received_at'] != null
        ? DateTime.parse(json['received_at'] as String)
        : null,
    status: json['status'] as String? ?? 'pending',
  );
}
```

**UI** — only show the button when allowed:

```dart
if (purchase.canReceive && userCanReceive) // role: admin, manager, warehouse
  TextButton(
    onPressed: () => _receivePurchase(purchase),
    child: const Text('Receive goods'),
  ),
```

**After successful receive:** reload the list so `received_at` and `status` update.

**Handle 422:**

```dart
} on DioException catch (e) {
  final msg = e.response?.data?['message'] as String?;
  if (e.response?.statusCode == 422 && msg?.contains('already received') == true) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('This order was already received.')),
    );
    await _loadPurchases();
    return;
  }
  rethrow;
}
```

---

## 4. Pay installment — disable when already paid

### Problem

```
HTTP 422 · POST · .../installments/{id}/pay
Installment already paid.
```

First tap succeeds (`is_paid: true`). Second tap on the **same row** fails because the list was not updated or `is_paid` was not read.

Logs showed `status=` empty on tap — map **`is_paid`**, not a generic `status`.

### Fix

**File:** `lib/data/models/supplier_installment.dart` (name may vary)

```dart
class SupplierInstallment {
  final String id;
  final bool isPaid;
  final int installmentNo;
  final double amount;
  // ...

  factory SupplierInstallment.fromJson(Map<String, dynamic> json) {
    return SupplierInstallment(
      id: json['id'] as String,
      isPaid: json['is_paid'] == true, // required
      installmentNo: json['installment_no'] as int? ?? 0,
      amount: (json['amount'] as num).toDouble(),
      // ...
    );
  }
}
```

**File:** `lib/features/installments/installments_screen.dart`

```dart
// Only show Pay for unpaid installments
if (!installment.isPaid)
  FilledButton(
    onPressed: _payingId == installment.id
        ? null
        : () => _payInstallment(installment),
    child: const Text('Pay'),
  )
else
  Text('Paid', style: TextStyle(color: Colors.green)),
```

**After successful pay:**

```dart
Future<void> _payInstallment(SupplierInstallment inst) async {
  setState(() => _payingId = inst.id);
  try {
    await _repo.pay(inst.id, paymentMethod: 'cash');
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Installment paid.')),
    );
    await _loadInstallments(); // refresh — Pay button must disappear
  } on DioException catch (e) {
    final msg = e.response?.data?['message'] as String?;
    if (e.response?.statusCode == 422 && msg?.contains('already paid') == true) {
      await _loadInstallments(); // sync UI with server
    }
    // show error snackbar...
  } finally {
    if (mounted) setState(() => _payingId = null);
  }
}
```

**API:** `POST /api/v1/installments/{id}/pay`  
Body: `{ "payment_method": "cash" }` (or `bank_transfer`, `check`)

---

## 5. Returns — stock + money back on dashboard

### Problem

- Approved returns did not always increase inventory.
- Cash refunded to the customer did not reduce dashboard sales/profit.

### Fix (API behaviour — refresh UI after approve)

**Approve:** `PATCH /api/v1/returns/{id}/approve`

**Important:** `reference_id` must be the **invoice UUID**, not the customer id.

```json
{
  "return_type": "customer_return",
  "reference_id": "<invoice-uuid>",
  "reference_type": "invoice",
  "customer_id": "<customer-uuid>",
  "branch_id": "<branch-uuid>",
  "items": [{ "part_id": "...", "quantity": 1, "unit_price": 120, "condition": "sellable" }]
}
```

After create/approve, invoice has `return_status`: `none` | `partial` | `returned`.  
You **cannot** return more quantity than sold, or return again when status is `returned` (422 + `failures`).

| Customer item `condition` | Resolution | Stock | Money off dashboard |
|---------------------------|------------|-------|---------------------|
| `sellable` | `refund_cash` | ✅ +qty | ✅ `weekly_customer_refunds` |
| `defective` | `writeoff` | ❌ scrap | ✅ refund (`total_value`) |
| `sellable` | `credit_note` | ✅ +qty | ✅ + lower credit balance |
| `sellable` | `restock` only | ✅ +qty | ❌ |

```json
{ "resolution": "refund_cash" }
```

Defective product but full money back to customer:

```json
{ "resolution": "writeoff", "items": [{ "condition": "defective", "unit_price": 120, "quantity": 1 }] }
```

**After approve (required in Flutter):**

```dart
await returnRepo.approve(id, resolution: selected);
await inventoryRepo.refresh(branchId);
await dashboardRepo.refreshSummary();
```

Dashboard fields: `weekly_customer_refunds`, `weekly_net_sales`, `weekly_profit`.

Full Arabic guide: [customer-returns-ar.md](./customer-returns-ar.md).

---

## 6. POS — line price override (optional `unit_price`)

**API:** `POST /api/v1/invoices`

```json
"items": [
  { "part_id": "uuid", "quantity": 2, "unit_price": 175.5 }
]
```

**Cart model:** default `unitPrice` from `part.sellPrice`; allow edit per line.

**Offline sync:** persist `unit_price` in `pending_invoice_items` and send it in the same JSON when syncing.

See [flutter-windows-recent-updates.md](./flutter-windows-recent-updates.md) §2.

---

## 7. Part image upload

**API:** `POST /api/v1/parts/{id}/image` — multipart field **`image`**, max **2 MB**.

Use **PATCH** is not used here — **POST** only.

```dart
final formData = FormData.fromMap({
  'image': await MultipartFile.fromFile(filePath, filename: fileName),
});
await dio.post('/parts/$partId/image', data: formData);
```

Do not call image upload when offline.

See [part-image-api.md](./part-image-api.md) and [flutter-add-part.md](./flutter-add-part.md) §6.

---

## 8. Settings — part categories

**API:** `GET/POST/PUT/DELETE /api/v1/part-categories`  
Admin/manager only. Online only.

See [flutter-windows-recent-updates.md](./flutter-windows-recent-updates.md) §4.

---

## 9. Base URL and login

Postman / app `baseUrl` must be the **ERB-Frezzer** API host, for example:

```text
https://api.tppower.shop/api/v1
```

**Login:** `POST /api/v1/auth/login`  
Body: `{ "email": "...", "password": "..." }`  
Response: `{ "token": "..." }`

If you see **404** on `auth/login`, the request is hitting the **wrong project** (e.g. TruckFund without these routes), not FrostParts API.

---

## 10. Checklist (copy for PR)

- [ ] `purchase_repository.dart` — receive uses **PATCH** only  
- [ ] `purchases_screen.dart` — `canReceive` from `received_at` + `status`  
- [ ] `supplier_installment` model — `is_paid` from JSON  
- [ ] `installments_screen.dart` — hide Pay when `is_paid`; refresh after pay  
- [ ] Returns approve — user picks `resolution`; refresh inventory  
- [ ] POS cart — optional `unit_price` on invoice lines + offline sync  
- [ ] Part image — `POST` multipart `image`  
- [ ] `baseUrl` ends with `/api/v1` (no double `/api/v1/api/v1`)

---

## 11. Quick reference — HTTP methods

| Action | Method | Path |
|--------|--------|------|
| Login | POST | `/auth/login` |
| Receive PO | **PATCH** | `/purchases/{id}/receive` |
| Pay installment | POST | `/installments/{id}/pay` |
| Approve return | PATCH | `/returns/{id}/approve` |
| Upload part image | POST | `/parts/{id}/image` |
| Create invoice | POST | `/invoices` |
| Create category | POST | `/part-categories` |
