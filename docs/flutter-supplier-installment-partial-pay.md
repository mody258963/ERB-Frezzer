# Flutter — pay supplier installment (custom amount)

Pay a supplier installment with **any amount up to the remaining balance** — full payment or partial (e.g. pay 10,000 EGP now, 15,000 later on a 25,000 installment).

**API:** `POST /api/v1/installments/{id}/pay`  
**Roles:** `admin`, `manager`  
**Related:** [flutter-dashboard-transactions-guide.md](./flutter-dashboard-transactions-guide.md) § B

**Database:** Columns and tables are in the main migration `2025_05_17_100007_create_purchase_orders_tables.php` (use `php artisan migrate:fresh --seed` — no separate migration file).

---

## 0. Database schema (for reference)

Created in `database/migrations/2025_05_17_100007_create_purchase_orders_tables.php`:

### `supplier_installments`

| Column | Type | Notes |
|--------|------|--------|
| `amount` | decimal(12,2) | Scheduled installment total |
| `amount_paid` | decimal(12,2) default 0 | Cumulative paid on this installment |
| `is_paid` | boolean | `true` when `amount_paid >= amount` |
| `paid_at` | timestamp nullable | Set when fully paid |

**Balance due (API only, not a DB column):** `amount - amount_paid` → JSON field `balance_due`.

### `supplier_installment_payments`

One row per payment (supports multiple partial pays on the same installment).

| Column | Type | Notes |
|--------|------|--------|
| `installment_id` | uuid FK | Which installment |
| `supplier_id` | uuid FK | Supplier |
| `po_id` | uuid FK | Purchase order |
| `amount` | decimal(12,2) | **This payment’s** amount |
| `payment_method` | string | `cash`, `bank_transfer`, `check` |
| `paid_by` | uuid FK | User who recorded payment |
| `paid_at` | timestamp | Used for `weekly_supplier_payments` on dashboard |

---

## 1. Installment fields (after `GET /installments` or supplier debt)

| Field | Meaning |
|-------|---------|
| `amount` | Scheduled installment total |
| `amount_paid` | Sum already paid on this installment |
| `balance_due` | Still owed = `amount - amount_paid` |
| `is_paid` | `true` only when `balance_due` = 0 |
| `due_date` | Due date |

Example — 25,000 installment, paid 10,000:

```json
{
  "id": "uuid",
  "installment_no": 1,
  "amount": 25000,
  "amount_paid": 10000,
  "balance_due": 15000,
  "is_paid": false,
  "due_date": "2026-06-15"
}
```

---

## 2. Pay with your amount

```http
POST /api/v1/installments/{installmentId}/pay
Authorization: Bearer <token>
Content-Type: application/json

{
  "payment_method": "cash",
  "amount": 10000,
  "notes": "Partial — paid at shop"
}
```

| Field | Required | Rules |
|-------|----------|--------|
| `payment_method` | Yes | `cash`, `bank_transfer`, `check` |
| `amount` | No | If omitted → pays **full** `balance_due`. If set → must be `> 0` and `≤ balance_due` |
| `notes` | No | Max 2000 chars |

**Success `200`:** updated installment resource (`amount_paid`, `balance_due`, `is_paid`).

### Examples

| Goal | Body |
|------|------|
| Pay 10,000 now | `{ "payment_method": "cash", "amount": 10000 }` |
| Pay everything left | `{ "payment_method": "cash" }` (no `amount`) |
| Bank transfer partial | `{ "payment_method": "bank_transfer", "amount": 5000.50 }` |

---

## 3. Flutter UI

```
┌─────────────────────────────────────────┐
│  Pay installment #2                     │
│  Supplier: AC Parts                     │
│  Due: 2026-06-15                        │
├─────────────────────────────────────────┤
│  Scheduled:     25,000.00 EGP           │
│  Already paid:  10,000.00 EGP           │
│  Balance due:   15,000.00 EGP           │
├─────────────────────────────────────────┤
│  Pay amount: [ 10000.00________ ]       │
│  [ Pay full balance (15,000) ]          │
│  Method: [ Cash ▼ ]                     │
│  Notes:  [ optional____________ ]       │
├─────────────────────────────────────────┤
│  [ Cancel ]              [ Pay ]        │
└─────────────────────────────────────────┘
```

```dart
Future<void> payInstallment({
  required String installmentId,
  required String paymentMethod,
  double? amount,
  String? notes,
}) async {
  await dio.post(
    '/installments/$installmentId/pay',
    data: {
      'payment_method': paymentMethod,
      if (amount != null) 'amount': amount,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
    },
  );
  await dashboardRepo.refreshSummary();
}
```

Validate before submit:

```dart
if (amount <= 0 || amount > balanceDue) {
  showError('Amount must be between 0.01 and $balanceDue');
  return;
}
```

---

## 4. Errors

| HTTP | Cause |
|------|--------|
| `422` | Validation (`amount` too high, invalid method) |
| `422` | Business rule — message explains (e.g. exceeds balance) |
| `403` | Not admin/manager |
| `401` | Re-login |

Overpayment example response (`422`):

```json
{
  "message": "Payment amount exceeds installment balance due (15000.00)."
}
```

---

## 5. Dashboard after pay

Refresh `GET /dashboard/summary`:

| Field | After partial 10k on 100k PO (4×25k) |
|-------|--------------------------------------|
| `weekly_supplier_payments` | +**actual paid** (10,000), not full installment |
| `unpaid_installments_total` | Remaining balances (90,000 if only one partial) |
| `total_supplier_debt` | Reduced by payment amount |

---

## 6. Multiple partial payments

Same installment can be paid many times until `is_paid == true`:

1. Pay 10,000 → `balance_due` 15,000, `is_paid` false  
2. Pay 15,000 → `is_paid` true  

Each payment is stored in `supplier_installment_payments` for reporting.

---

## 7. Checklist

- [ ] Show `amount`, `amount_paid`, `balance_due` on pay screen  
- [ ] Default pay field to `balance_due` or let user type less  
- [ ] “Pay full balance” omits `amount` or sends `balance_due`  
- [ ] Disable Pay when `is_paid` or `balance_due == 0`  
- [ ] Refresh dashboard + supplier debt after success  
- [ ] Handle 422 message for overpayment  

---

## 8. Backend & migrate:fresh

| Piece | Location |
|-------|----------|
| Schema | `database/migrations/2025_05_17_100007_create_purchase_orders_tables.php` |
| Pay logic | `app/Services/InstallmentPaymentService.php` |
| API | `POST /api/v1/installments/{id}/pay` with optional `amount` |

Local / staging reset:

```bash
php artisan migrate:fresh --seed
php artisan test --filter=InstallmentPartialPaymentTest
```

Tests: `tests/Feature/InstallmentPartialPaymentTest.php`
