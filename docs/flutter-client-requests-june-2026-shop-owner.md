# Flutter — Shop owner requests (June 2026)

**Audience:** `erd_rezzer` Flutter team  
**Backend:** ERB-Frezzer `/api/v1`  
**Date:** June 2026

Three requests from the shop owner (WhatsApp). Backend support is implemented; this doc is the Flutter integration guide.

---

## ملخص بالعربي

| # | طلب العميل | الحالة على الخادم | ماذا يفعل Flutter |
|---|------------|-------------------|-------------------|
| 1 | فواتير المورد في قسم واحد لكل مورد + الدفع من الإجمالي | **جديد** `POST /suppliers/{id}/payments` | شاشة دائنون مجمّعة + حوار دفع من `total_debt` |
| 2 | الأسبوع من الإثنين 9 ص حتى نهاية الجمعة | **محدّث** `period=week` | لا تحسب الأسبوع في التطبيق — اقرأ `period.from` / `period.to` |
| 3 | تحصيل الآجل يظهر في مبيعات/درج اليوم | **موجود** | بعد التحصيل حدّث `GET /dashboard/cash?period=day` |

---

## English summary

| # | Client request | Backend | Flutter action |
|---|----------------|---------|----------------|
| 1 | Supplier invoices grouped per supplier; pay from total | **New** `POST /suppliers/{id}/payments` | Grouped payables UI + pay dialog against `total_debt` |
| 2 | Week = Mon 9 AM → Fri end | **Updated** `period=week` | Do not compute week client-side; use API `period` object |
| 3 | Credit collections in daily drawer | **Already supported** | Refresh `GET /dashboard/cash?period=day` after collect |

---

## 1. Supplier payables — grouped + lump-sum payment

### Client quote

> عاوز فواتير المورد تكون لوحدها فحته واحده وانزل من اجمالى المبلغ مش كل فاتوره لوحدها

**Translation:** Show each supplier in one section; pay any amount against their **total debt** — not one invoice/installment at a time.

### New APIs

#### Grouped payables (all suppliers with debt)

```http
GET /api/v1/dashboard/payables/by-supplier
```

Response:

```json
{
  "suppliers": [
    {
      "supplier": { "id": "...", "name": "...", "total_debt": 30000.0 },
      "purchase_orders": [ ... ],
      "installments": [ ... ]
    }
  ],
  "total_supplier_debt": 30000.0
}
```

Use this instead of N calls to `GET /suppliers/{id}/debt`.

#### Pay supplier (lump sum — FIFO across installments)

```http
POST /api/v1/suppliers/{id}/payments
Authorization: Bearer <token>
Content-Type: application/json

{
  "payment_method": "cash",
  "amount": 30000,
  "notes": "optional"
}
```

| Field | Required | Notes |
|-------|----------|--------|
| `payment_method` | Yes | `cash`, `bank_transfer`, `check` |
| `amount` | No | Omit = pay full `total_debt` |
| `notes` | No | Stored on allocation rows |

**Success `201`:**

```json
{
  "supplier_id": "uuid",
  "supplier_name": "Supplier A",
  "amount": 30000.0,
  "payment_method": "cash",
  "notes": null,
  "paid_at": "2026-06-19T12:00:00+00:00",
  "total_debt_after": 0.0,
  "allocations": [
    {
      "id": "uuid",
      "installment_id": "uuid",
      "installment_no": 1,
      "po_id": "uuid",
      "amount": 15000.0,
      "payment_method": "cash",
      "paid_at": "...",
      "notes": null
    }
  ]
}
```

Server allocates **oldest due installment first** (same as contra offset).

#### Payment history

```http
GET /api/v1/suppliers/{id}/payments?per_page=25
```

Paginated list of cash/bank/check allocation rows (excludes offset/مقاصة).

### Recommended UI

```
┌─ دائنون — موردون ─────────────────────────┐
│ روماني الرياض          11,999.98 ج.م  [دفع] │
│   ▼ تفاصيل (اختياري): فواتير / أقساط      │
├──────────────────────────────────────────┤
│ أبو سيف لوجسون          1,645.00 ج.م  [دفع] │
└──────────────────────────────────────────┘
```

**Pay dialog:**

- Show `supplier.total_debt`
- Amount field (default = full debt)
- Payment method
- On success: refresh `by-supplier`, dashboard summary, and `GET /dashboard/cash?period=day`

**Legacy:** `POST /installments/{id}/pay` still works for edge cases. **Preferred UX** is supplier-level pay.

See also [flutter-supplier-installment-partial-pay.md](./flutter-supplier-installment-partial-pay.md).

---

## 2. Business week — Monday 9 AM to Friday night

### Client quote

> بدايه الاسبوع الاتنين من الساعه 9 ونهايته السبت بعد الساعه 12 بليل — عشان هو الاسبوع انتهي يوم الجمعه

**Translation:** Week starts **Monday 09:00** and ends **Friday 23:59:59** (Saturday midnight = end of Friday). The shop week ends on Friday.

### Backend behavior (`period=week`)

| Rule | Behavior |
|------|----------|
| Start | Monday **09:00** (app timezone) |
| End | Friday **23:59:59** |
| Saturday / Sunday | Show the **completed** week that ended last Friday |
| Monday before 09:00 | Still in **previous** business week |

Example response:

```json
{
  "period": {
    "key": "week",
    "from": "2026-06-15T09:00:00+00:00",
    "to": "2026-06-19T23:59:59+00:00",
    "anchor_date": "2026-06-18"
  }
}
```

### Flutter rules

1. **Do not** compute week boundaries in Dart — always use `period.from` / `period.to` from the API.
2. Label: **هذا الأسبوع (الإثنين 9 ص – الجمعة)** / “This week (Mon 9 AM – Fri)”.
3. Pass `period=week` on summary, sales, and cash when the week tab is selected.

Config on server: `config/business.php` → `week_start_hour` (default `9`).

**Note:** Customer Saturday settlement reminders use a **different** week rule — see [flutter-customer-settlement-cycle.md](./flutter-customer-settlement-cycle.md).

---

## 3. Credit payments in daily drawer

### Client quote

> لما حد يديني دفعه من الحسابات الاجل تنزل وانا بعمل مبيعات اليوم بحيث تكون معايه الدرج مظبوط ولو خرجت فلوس بردو تنزل من الحساب

**Translation:** When a credit customer pays, it must show in **today’s** cash so the drawer matches reality. Money going out must also reduce today’s cash.

### Already in API

After `POST /customers/{id}/payments` or `POST /settlements`, refresh:

```http
GET /api/v1/dashboard/cash?period=day
```

| Field | Meaning |
|-------|---------|
| `period_cash_in_realized` | Cash sales + credit collections + settlements today |
| `period_cash_out_realized` | Supplier payments + refunds + owner cash-out today |
| `period_net_cash_flow_realized` | In − out today |
| `cash_on_hand_realized` | Lifetime drawer balance (snapshot) |

### POS flow

```dart
await api.collectCustomerPayment(customerId, amount: 500, paymentMethod: 'cash');
await api.loadDashboardCash(period: DashboardPeriod.day);
// Update drawer card: period_net_cash_flow_realized + breakdown
```

### Do NOT use for drawer reconciliation

- `reports/financial` `totals.net_sales` — invoice revenue, **not** cash collected today
- `(cash_on_hand + receivables - payables)` — not net cash flow

See [flutter-dashboard-period-numbers-validation.md](./flutter-dashboard-period-numbers-validation.md) § Net cash flow.

---

## QA checklist

### Supplier grouped pay

- [ ] `GET /dashboard/payables/by-supplier` shows one row per supplier with debt
- [ ] `total_supplier_debt` matches dashboard `must_pay_suppliers`
- [ ] Pay 10,000 against supplier with 30,000 debt → `total_debt_after` = 20,000
- [ ] Full pay clears debt; allocations array shows FIFO slices
- [ ] Pay amount > `total_debt` → 422
- [ ] After pay, `period_cash_out_realized` increases on `period=day`

### Business week

- [ ] Week tab sends `period=week`
- [ ] Date header matches API `period.from`–`period.to` (Mon 9 AM – Fri end)
- [ ] Sale Monday 08:00 **not** in current week revenue
- [ ] Sale Wednesday **in** current week revenue
- [ ] Saturday view shows week that ended Friday

### Daily drawer

- [ ] After credit collection, `period_cash_in_realized` increases
- [ ] After supplier pay / owner cash-out, `period_cash_out_realized` increases
- [ ] `period_net_cash_flow = period_cash_in - period_cash_out` (±0.01)
- [ ] Drawer UI refreshes after every cash movement

---

## Related docs

- [flutter-dashboard-period-filter.md](./flutter-dashboard-period-filter.md)
- [flutter-dashboard-period-numbers-validation.md](./flutter-dashboard-period-numbers-validation.md)
- [flutter-supplier-installment-partial-pay.md](./flutter-supplier-installment-partial-pay.md)
- [flutter-dashboard-real-cash-boxes.md](./flutter-dashboard-real-cash-boxes.md)
- [flutter-client-notes-june-2026.md](./flutter-client-notes-june-2026.md)
