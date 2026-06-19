# Flutter — رأس المال (business capital) definition update

**Audience:** `erd_rezzer` Flutter team  
**Backend:** ERB-Frezzer `/api/v1`  
**Date:** June 2026

---

## ملخص بالعربي

**رأس المال** على لوحة التحكم = **قيمة المخزون (بالتكلفة)** + **النقد الفعلي في الدرج**.

| المكوّن | الحقل في الـ API | المعنى |
|---------|------------------|--------|
| مخزون | `total_stock_value_cost` | مجموع (الكمية × متوسط التكلفة) للقطع النشطة |
| نقد | `cash_on_hand_realized` | النقد الحقيقي: رصيد افتتاحي + تحصيل − مدفوعات |
| **رأس المال** | `business_capital` | **محسوب تلقائياً** = مخزون + نقد |

**ما يعدّله الأدمن يدوياً** ليس رأس المال الكلي، بل **رصيد النقد الافتتاحي** (`opening_cash_balance`) عبر:

`PUT /api/v1/settings/capital` → `capital_amount`

استخدم هذا عندما يعدّ الأدمن الصندوق في بداية اليوم أو يصحّح فرقاً بين النظام والنقد الفعلي.

---

## English summary

| Field | Meaning |
|-------|---------|
| `opening_cash_balance` | Admin-set **opening cash** (stored per branch). Same value as `capital_amount` in settings API (backward compatible). |
| `cash_on_hand_realized` | Opening cash + lifetime cash in − lifetime cash out |
| `total_stock_value_cost` | Inventory at weighted average cost |
| `business_capital` | **Computed:** `total_stock_value_cost + cash_on_hand_realized` |

Admin **does not** type total capital directly anymore on the dashboard — they adjust **opening cash** when the drawer count differs from the system.

---

## API changes

### `GET /api/v1/dashboard/summary`

| Field | Change |
|-------|--------|
| `business_capital` | Now **inventory + cash** (was: stored branch total only) |
| `opening_cash_balance` | **New** — admin opening cash for scope (branch or org) |
| `capital_amount` | **Removed from summary** — use `opening_cash_balance` |

Formula (always true):

```
business_capital ≈ total_stock_value_cost + cash_on_hand_realized
```

### `GET /api/v1/settings/capital`

| Field | Meaning |
|-------|---------|
| `opening_cash_balance` | **New** — what admin last set |
| `capital_amount` | **Same as opening cash** (kept for old clients) |
| `business_capital` | **New** — computed total (stock + cash) |
| `financing_snapshot.business_capital` | Same computed total |
| `financing_snapshot.inventory_at_cost` | Stock component |
| `financing_snapshot.cash_on_hand_realized` | Cash component |
| `financing_snapshot.estimated_available` | **Legacy formula** — do not use for UI |

### `PUT /api/v1/settings/capital`

Body unchanged:

```json
{
  "capital_amount": 500000,
  "branch_id": "uuid-if-multi-branch",
  "reason": "Opening drawer count",
  "notes": "optional"
}
```

**Semantics:** sets **opening cash balance**, not total رأس المال.

After save, `business_capital` on dashboard updates because cash component changes.

### `GET /api/v1/reports/financial`

| Field | Meaning |
|-------|---------|
| `capital.business_capital` | Computed total |
| `capital.opening_cash_balance` | Admin opening cash |
| `capital.capital_amount` | **Alias of `business_capital`** in reports (for chart compatibility) |

---

## Flutter UI (recommended)

### Dashboard card — رأس المال

```
رأس المال:  {business_capital} ج.م
  ├ مخزون:  {total_stock_value_cost}
  └ نقد:    {cash_on_hand_realized}
```

### Settings screen — تعديل رصيد النقد الافتتاحي

- Label: **رصيد النقد الافتتاحي** (not "رأس المال الكلي")
- Show read-only breakdown below form:
  - مخزون حالي
  - نقد محسوب
  - رأس المال = مجموع الاثنين

### Do NOT

- Treat `PUT capital` as setting total رأس المال while ignoring inventory
- Show `financing_snapshot.estimated_available` as cash on hand (use `cash_on_hand_realized`)
- Expect `business_capital` to stay fixed when stock or cash transactions occur

### DO refresh after

- Inventory adjust, sale, purchase receive, transfer
- Customer/supplier payment
- `PUT /settings/capital` (opening cash change)
- Owner cash-out (reduces cash → reduces `business_capital`)

---

## What does NOT change رأس المال

- Credit invoices (receivable only — not cash yet)
- Purchase orders before payment
- Branch finance between branches
- `must_collect_customers` / `must_pay_suppliers` (separate obligation boxes)

---

## Migration checklist

- [ ] Dashboard رأس المال card uses `business_capital` with stock + cash subtitle
- [ ] Settings form labeled "opening cash" / رصيد نقد افتتاحي
- [ ] Read `opening_cash_balance` from summary/settings
- [ ] Remove UI that equated `capital_amount` with total business wealth
- [ ] Financial report uses `capital.business_capital` for total

---

## Common Flutter crashes after this change

If you only see `PointerRouter` / `GestureBinding` at the bottom of the stack trace, scroll up — the real error is **above** frame #11.

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| `type 'Null' is not a subtype of type 'num'` on dashboard | Reading removed field `capital_amount` from `GET /dashboard/summary` | Use `opening_cash_balance` and `business_capital` |
| Crash on capital save button tap | Parsing `business_capital` as `int` or `!` on nullable JSON | Use `num` → `toDouble()`, defaults `?? 0` |
| Cash-out screen crash | `profit_withdrawal` null or nested path wrong | Always read `profit_withdrawal.withdrawable_profit` from `GET /settings/capital` |
| UI shows wrong capital after cash out | Old logic kept `business_capital` fixed | Refresh summary; expect lower `cash_on_hand_realized`, unchanged `opening_cash_balance` |

**Paste for backend support:** full exception line + frames #0–#15 + which screen/button was tapped.

---

## Related docs

- [flutter-dashboard-real-cash-boxes.md](./flutter-dashboard-real-cash-boxes.md)
- [flutter-owner-cash-out-profit-validation.md](./flutter-owner-cash-out-profit-validation.md)
- [flutter-dashboard-transactions-guide.md](./flutter-dashboard-transactions-guide.md)
