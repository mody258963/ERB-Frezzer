# Flutter Dashboard — Real Cash Boxes (June 2026)

This update adds dashboard values that represent **only money that already happened in real life**.

---

## ملخص للمطور — النقد الداخل والخارج

| Label (AR) | API field | Scope |
|------------|-----------|--------|
| النقد في الصندوق | `cash_on_hand_realized` | **Snapshot** — لا يتغير مع تبويب اليوم/الأسبوع |
| نقد داخل | `period_cash_in_realized` | **الفترة المختارة** فقط |
| **نقد خارج** | `period_cash_out_realized` | **الفترة المختارة** فقط |
| صافي التدفق النقدي | `period_net_cash_flow_realized` | `نقد داخل − نقد خارج` |
| مستحق من العملاء | `must_collect_customers` | Snapshot (دين، ليس نقداً خرج أو دخل) |
| مستحق للموردين | `must_pay_suppliers` | Snapshot (دين، ليس نقد خارج) |

**استخدم `period_*` وليس `weekly_*`** — راجع [flutter-dashboard-period-filter.md](./flutter-dashboard-period-filter.md).

---

## Why this was added

Before this change, some teams used `capital_estimated_available` as if it were real cash-on-hand.
That was confusing because it included future obligations (receivables/payables), not only paid/collected cash.

Now the API exposes clear fields:

- **Must collect** (customers owe us)  
- **Must pay** (we owe suppliers)  
- **Cash on hand (realized)** = only actual cash in/out events

---

## API endpoints

### 1) Dashboard Summary (updated fields)

`GET /api/v1/dashboard/summary`

New / clarified fields in response:

- `must_collect_customers` (float): total credit money customers still owe
- `must_pay_suppliers` (float): total supplier debt still unpaid
- `cash_on_hand_realized` (float): real cash position from actual transactions
- `period_cash_in_realized` / `weekly_cash_in_realized` — cash-in for selected period
- `period_cash_out_realized` / `weekly_cash_out_realized` — cash-out for selected period
- `period_net_cash_flow_realized` / `weekly_net_cash_flow_realized` — in − out for selected period
- `legacy_estimated_available` (float): old formula (kept for backward compatibility)

`capital_estimated_available` now returns the same realized value as `cash_on_hand_realized`.

### 2) New endpoint for cash boxes

`GET /api/v1/dashboard/cash`

Same branch behavior as other dashboard endpoints (`?branch_id=` for admin, locked branch for non-admin).

```http
GET /api/v1/dashboard/cash?period=day|week|month&date=YYYY-MM-DD
```

Example response:

```json
{
  "period": {
    "key": "day",
    "from": "2026-06-19T00:00:00+00:00",
    "to": "2026-06-19T23:59:59+00:00",
    "anchor_date": "2026-06-19"
  },
  "must_collect_customers": 100.0,
  "must_pay_suppliers": 100.0,
  "cash_on_hand_realized": 250000.0,
  "lifetime_cash_in_realized": 75000.0,
  "lifetime_cash_out_realized": 25000.0,
  "period_cash_in_realized": 12000.0,
  "period_cash_out_realized": 5000.0,
  "period_net_cash_flow_realized": 7000.0
}
```

---

## النقد الخارج — how `period_cash_out_realized` is calculated

### Formula

```
period_cash_out_realized =
    supplier_payments_in_period
  + customer_refund_cash_outs_in_period
  + owner_cash_outs_in_period
```

```
period_net_cash_flow_realized = period_cash_in_realized − period_cash_out_realized
```

Tolerance when verifying in UI: ±0.01.

### Component 1 — Supplier payments (مدفوعات الموردين)

Sum of `supplier_installment_payments.amount` where:

| Rule | Value |
|------|--------|
| Date column | `paid_at` within `period.from` → `period.to` |
| Payment method | `cash`, `bank_transfer`, or `check` |
| Excluded | `offset` (مقاصة — no cash leaves drawer) |

**APIs that create these rows:**

- `POST /api/v1/installments/{id}/pay`
- `POST /api/v1/suppliers/{id}/payments` (lump-sum — preferred UX)

### Component 2 — Customer refund cash-outs (مرتجعات نقدية)

Sum of completed customer `returns.total_value` where:

| Rule | Value |
|------|--------|
| `return_type` | `customer_return` |
| `status` | `completed` |
| `resolution` | `refund_cash` or `writeoff` |
| Date | `completed_at` in period (fallback: `updated_at`) |

**Not included:** `credit_note` returns (balance adjustment only).

### Component 3 — Owner cash-out (سحب المالك)

Sum of `owner_cash_outs.amount` where `created_at` is in the period.

**API:** `POST /api/v1/settings/capital/cash-out`

---

## النقد الداخل — how `period_cash_in_realized` is calculated

For completeness (نقد داخل pairs with نقد خارج on the same card):

```
period_cash_in_realized =
    cash_invoice_totals_in_period
  + customer_payments_in_period
  + saturday_settlements_in_period
```

| Source | Date column | Excluded |
|--------|-------------|----------|
| Cash invoices (`payment_type = cash`) | `invoices.created_at` | — |
| Credit customer collections | `customer_payments.created_at` | `offset` |
| Saturday settlement | `saturday_settlements.created_at` | `offset` |

**APIs:** `POST /invoices` (cash), `POST /customers/{id}/payments`, `POST /settlements`

After any collection at POS, refresh `GET /dashboard/cash?period=day` so the drawer card updates.

---

## What does NOT count as نقد خارج

| Operation | Why excluded |
|-----------|----------------|
| `must_pay_suppliers` / `total_supplier_debt` | Debt not yet paid — not cash out |
| Creating purchase order | No payment yet |
| Credit invoice | No cash movement |
| Credit-note return | Balance only |
| Offset / مقاصة payment | No physical cash |
| Branch finance / stock transfer | Does not change main drawer |

**Do not compute:**

```dart
// WRONG — this is not period_cash_out_realized
(cashOnHand + mustCollect) - mustPay;
```

---

## Realized-cash logic (important)

### What increases realized cash-in

- Cash invoices (`payment_type = cash`)
- Customer payments collected from credit customers
- Saturday settlements with non-offset payment method

### What increases realized cash-out (النقد الخارج)

- Supplier installment payments (non-offset) — including lump-sum supplier pay
- Customer return cash refunds (`refund_cash`, `writeoff`)
- Owner cash-out

### What does NOT change realized cash-on-hand

- Creating purchase orders (debt created but no cash paid yet)
- Creating credit invoices (receivable created but no cash collected yet)
- Credit-note returns (balance adjustment without cash movement)

---

## Flutter UI mapping (recommended boxes)

Bind labels to `period.key`:

| `period.key` | نقد خارج label |
|--------------|----------------|
| `day` | نقد خارج — اليوم |
| `week` | نقد خارج — الأسبوع |
| `month` | نقد خارج — الشهر |

Use these cards:

1. `cash_on_hand_realized` → **النقد في الصندوق** (no period suffix)
2. `must_collect_customers` → **مستحق من العملاء**
3. `must_pay_suppliers` → **مستحق للموردين**
4. `period_net_cash_flow_realized` → **صافي التدفق النقدي**

Breakdown rows (same API response):

- `period_cash_in_realized` → **نقد داخل**
- `period_cash_out_realized` → **نقد خارج**

```dart
bool cashFlowConsistent(DashboardCash cash) {
  final expected = cash.periodCashIn - cash.periodCashOut;
  return (cash.periodNetCashFlow - expected).abs() < 0.02;
}
```

Refresh after: invoice, customer payment, supplier pay, return approve, owner cash-out.

---

## Migration / compatibility notes

- New Flutter screens should use `period_*` cash fields.
- If you still display `legacy_estimated_available`, label it clearly as **legacy estimate**, not real cash.

## Related docs

- [flutter-dashboard-period-numbers-validation.md](./flutter-dashboard-period-numbers-validation.md) — period vs snapshot, QA checklist
- [flutter-dashboard-period-filter.md](./flutter-dashboard-period-filter.md) — `period=day|week|month`
- [flutter-client-requests-june-2026-shop-owner.md](./flutter-client-requests-june-2026-shop-owner.md) — daily drawer after credit collection
