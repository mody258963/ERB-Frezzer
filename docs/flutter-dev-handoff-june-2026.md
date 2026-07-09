# Flutter dev handoff — June 2026 (shop owner + cash boxes)

**Audience:** `erd_rezzer` Flutter team  
**Backend:** ERB-Frezzer `/api/v1`  
**Date:** June 2026

Single-page brief for what to build or change in the app **now**. Detailed API notes live in the linked docs below.

---

## ملخص سريع

| # | الموضوع | الحالة على الخادم | ماذا يفعل Flutter |
|---|---------|-------------------|-------------------|
| 1 | دفع المورد من الإجمالي (مجمّع) | **جاهز** | `GET /dashboard/payables/by-supplier` + `POST /suppliers/{id}/payments` |
| 2 | الأسبوع التجاري | **جاهز** | لا تحسب الأسبوع في Dart — استخدم `period.from` / `period.to` |
| 3 | تحصيل الآجل في درج اليوم | **جاهز** | بعد التحصيل: `GET /dashboard/cash?period=day` |
| 4 | دفع بين الفروع → صندوق النقد | **جاهز (جديد)** | حدّث صناديق النقد لكل فرع بعد `POST /branch-finance/payments` |

---

## Priority checklist

| Priority | Task | Done? |
|----------|------|-------|
| **High** | Hide dashboard, reports, and capital for `warehouse` / `salesperson` (use `can_*` flags from `/auth/me`) | ☐ |
| **High** | Grouped supplier payables UI + lump-sum pay | ☐ |
| **High** | Refresh per-branch cash after inter-branch payment | ☐ |
| **Medium** | Remove client-side week math; bind UI to API `period` | ☐ |
| **Medium** | Refresh daily drawer after credit collection / settlement | ☐ |
| **Low** | Payment edit/void on branch finance → refresh both branch cash screens | ☐ |

---

## Role permissions (warehouse + salesperson)

After login, read **`GET /api/v1/auth/me`** — new boolean flags:

| Field | `admin` | `manager` | `salesperson` | `warehouse` |
|-------|---------|-----------|---------------|-------------|
| `can_access_all_branches` | ✓ | ✗ | ✗ | ✗ |
| `can_select_branch` | ✓ | ✗ | ✗ | ✗ |
| `can_view_dashboard` | ✓ | ✓ | ✗ | ✗ |
| `can_view_capital` | ✓ | ✓ | ✗ | ✗ |
| `can_view_reports` | ✓ | ✓ | ✗ | ✗ |
| `accessible_branch_ids` | all active branches | `[branch_id]` | `[branch_id]` | `[branch_id]` |
| `can_cash_out_profit` | ✓ | ✗ | ✗ | ✗ |
| `can_pay_suppliers` | ✓ | ✓ | ✓ | ✓ |
| `can_collect_customer_payments` | ✓ | ✓ | ✓ | ✓ |
| `can_approve_returns` | ✓ | ✓ | ✓ | ✓ |
| `can_create_purchases` | ✓ | ✓ | ✗ | ✓ |

### Client feedback — warehouse & salesperson (July 2026)

| Client report | Backend fix | Flutter |
|---------------|-------------|---------|
| Can't collect credit customer payments | `POST /customers/{id}/payments`, `POST /settlements` → salesperson + warehouse | `can_collect_customer_payments` |
| Can't pay suppliers | `POST /suppliers/{id}/payments` → salesperson + warehouse | `can_pay_suppliers` + `GET /suppliers/payables/by-supplier` |
| Can't approve return + cash refund | `PATCH /returns/{id}/approve` `{ "resolution": "refund_cash" }` → salesperson + warehouse | `can_approve_returns` |
| Supply permit — products missing | `POST /purchases` → warehouse; parts list includes stock in branch | `can_create_purchases`; refresh `GET /parts` |
| Issues on all accounts | Role middleware bug fixed (`admin,manager,...` now checks every role) | Redeploy backend |

### Flutter UI rules

- **Hide** dashboard screens, reports, KPI cards, capital settings, and owner cash-out when the matching `can_*` flag is `false`.
- **403** on blocked routes is expected if the app calls them anyway.

### Supplier pay (warehouse + salesperson — fixed)

```http
POST /api/v1/suppliers/{supplierId}/payments
POST /api/v1/installments/{id}/pay
```

Grouped payables (not under `/dashboard` — works for all roles):

```http
GET /api/v1/suppliers/payables/by-supplier
```

Legacy dashboard path still works for **admin/manager only**:

```http
GET /api/v1/dashboard/payables/by-supplier
```

### Blocked for warehouse + salesperson (403)

- `GET /api/v1/dashboard/*` (all dashboard endpoints)
- `GET /api/v1/reports/*` (all report endpoints)
- `GET /api/v1/settings/capital`
- `POST /api/v1/settings/capital/cash-out` (admin only)

### Admin — all branches

| Field | Value |
|-------|--------|
| `can_access_all_branches` | `true` |
| `can_select_branch` | `true` |
| `accessible_branch_ids` | UUIDs of all **active** branches |

**API usage:**

- No `branch_id` → aggregated data across all branches (dashboard, lists).
- `?branch_id={uuid}` or header `X-Branch-Id: {uuid}` → filter to one branch.
- `branch_id` in POST body for invoices, purchases, parts, etc. — admin may use **any** active branch.

Non-admin roles (`manager`, `salesperson`, `warehouse`) are **locked** to `user.branch_id`; the server ignores a different `branch_id`.

See [flutter-per-branch-isolation.md](./flutter-per-branch-isolation.md).

---

## 1. Supplier payables — grouped + lump-sum payment

**Client ask:** One section per supplier; pay any amount against **total debt**, not one installment at a time.

### APIs

```http
GET /api/v1/suppliers/payables/by-supplier
```

Admin/manager may also use `GET /api/v1/dashboard/payables/by-supplier`.

```http
POST /api/v1/suppliers/{supplierId}/payments
Content-Type: application/json

{
  "payment_method": "cash",
  "amount": 1500.00,
  "notes": "optional"
}
```

Server allocates FIFO across open installments. Refresh payables list and dashboard cash after pay.

**Detail:** [flutter-client-requests-june-2026-shop-owner.md](./flutter-client-requests-june-2026-shop-owner.md) §1

---

## 2. Business week (dashboard period)

**Rule on server:** Monday **09:00** → Saturday **23:59:59** (app timezone).

| Situation | Behavior |
|-----------|----------|
| Saturday during the day | Still inside the **current** business week |
| Sunday | API returns the week that **ended last Saturday** |
| Monday before 09:00 | Still in the **previous** business week |

### Flutter rules

1. **Never** compute week boundaries in Dart.
2. Always read `period.from`, `period.to`, `period.key` from the API response.
3. Pass `period=week` on `GET /dashboard/summary`, `/dashboard/sales`, `/dashboard/cash`.
4. UI label: **هذا الأسبوع (الإثنين 9 ص – السبت)** / “This week (Mon 9 AM – Sat)”.

```http
GET /api/v1/dashboard/summary?period=week&date=2026-06-18
```

Example `period` in response:

```json
{
  "period": {
    "key": "week",
    "from": "2026-06-15T09:00:00+00:00",
    "to": "2026-06-20T23:59:59+00:00",
    "anchor_date": "2026-06-18"
  }
}
```

> Customer Saturday **settlement reminders** use a different week rule — see [flutter-customer-settlement-cycle.md](./flutter-customer-settlement-cycle.md).

**Detail:** [flutter-client-requests-june-2026-shop-owner.md](./flutter-client-requests-june-2026-shop-owner.md) §2, [flutter-dashboard-period-filter.md](./flutter-dashboard-period-filter.md)

---

## 3. Credit collections in the daily drawer

**Client ask:** When a credit customer pays, today’s drawer must match reality.

After `POST /customers/{id}/payments` or `POST /settlements`:

```http
GET /api/v1/dashboard/cash?period=day&branch_id={branchId}
```

| Field | Meaning |
|-------|---------|
| `period_cash_in_realized` | Cash sales + credit collections + settlements **today** |
| `period_cash_out_realized` | Supplier payments + refunds + owner cash-out + inter-branch payments sent **today** |
| `period_net_cash_flow_realized` | In − out today |
| `cash_on_hand_realized` | Lifetime drawer balance (snapshot — not period-scoped) |

Use `period_*` fields, not legacy `weekly_*` aliases.

**Detail:** [flutter-client-requests-june-2026-shop-owner.md](./flutter-client-requests-june-2026-shop-owner.md) §3

---

## 4. Inter-branch payment → cash box (new)

**Client ask:** When branch A pays branch B, deduct cash from A’s drawer and add to B’s drawer.

### API (unchanged path — new cash behavior)

```http
POST /api/v1/branch-finance/payments

{
  "creditor_branch_id": "uuid-receiving",
  "debtor_branch_id": "uuid-paying",
  "amount": 500.00,
  "notes": "optional"
}
```

| Branch role | Field in request | Cash effect |
|-------------|------------------|-------------|
| **Paying** | `debtor_branch_id` | `period_cash_out_realized` ↑, `cash_on_hand_realized` ↓ |
| **Receiving** | `creditor_branch_id` | `period_cash_in_realized` ↑, `cash_on_hand_realized` ↑ |

- **Org-wide** dashboard (no `branch_id`): totals **net to zero** — cash moves between branches only.
- **Per-branch** (`?branch_id=`): each branch sees its own in/out.
- **Void** or **edit** a payment → cash effect reverses or adjusts.

### After payment — refresh

```http
GET /api/v1/branch-finance/balances
GET /api/v1/dashboard/cash?branch_id={debtorBranchId}&period=day
GET /api/v1/dashboard/cash?branch_id={creditorBranchId}&period=day
```

Show on UI:

- Paying branch → **نقد خارج** (inter-branch payment sent)
- Receiving branch → **نقد داخل** (inter-branch payment received)

**Detail:** [flutter-client-requests-june-2026-shop-owner.md](./flutter-client-requests-june-2026-shop-owner.md) §4, [flutter-branch-transaction-edits.md](./flutter-branch-transaction-edits.md), [flutter-dashboard-real-cash-boxes.md](./flutter-dashboard-real-cash-boxes.md)

---

## Cash box formulas (reference)

### Cash in (`period_cash_in_realized`)

```
cash invoices
+ customer payments (not offset)
+ saturday settlements (not offset)
+ inter-branch payments received (creditor branch)
```

### Cash out (`period_cash_out_realized`)

```
supplier installment payments (not offset)
+ customer refund cash-outs
+ owner cash-outs
+ inter-branch payments sent (debtor branch)
```

### Net

```
period_net_cash_flow_realized = period_cash_in_realized − period_cash_out_realized
```

Tolerance when verifying in UI: ±0.01.

**Detail:** [flutter-dashboard-real-cash-boxes.md](./flutter-dashboard-real-cash-boxes.md)

---

## Dashboard endpoints (period + branch)

| Endpoint | Params |
|----------|--------|
| `GET /dashboard/summary` | `period`, `date`, `branch_id` |
| `GET /dashboard/cash` | `period`, `date`, `branch_id` |
| `GET /dashboard/sales` | `period`, `date`, `branch_id` |

Default `period` is `week`. Admin may pass `branch_id`; non-admin is locked to their branch.

**Detail:** [flutter-dashboard-period-filter.md](./flutter-dashboard-period-filter.md)

---

## QA smoke tests (shop owner items)

- [ ] Grouped payables list shows suppliers with `total_debt`
- [ ] Lump-sum supplier pay reduces debt; dashboard `period_cash_out_realized` updates (cash method)
- [ ] Week tab shows API `period.from`–`period.to`; label mentions Mon 9 AM – Sat
- [ ] Credit collection today increases `period_cash_in_realized` on `GET /dashboard/cash?period=day`
- [ ] Inter-branch payment: paying branch cash ↓, receiving branch cash ↑; org total unchanged
- [ ] Void inter-branch payment reverses both branch cash boxes

---

## Related docs (full June release)

| Topic | Document |
|-------|----------|
| **This handoff** | [flutter-dev-handoff-june-2026.md](./flutter-dev-handoff-june-2026.md) |
| Shop owner requests (detailed) | [flutter-client-requests-june-2026-shop-owner.md](./flutter-client-requests-june-2026-shop-owner.md) |
| June release index | [flutter-june-2026-windows-updates.md](./flutter-june-2026-windows-updates.md) |
| Real cash boxes | [flutter-dashboard-real-cash-boxes.md](./flutter-dashboard-real-cash-boxes.md) |
| Period filter | [flutter-dashboard-period-filter.md](./flutter-dashboard-period-filter.md) |
| Business capital | [flutter-business-capital-definition.md](./flutter-business-capital-definition.md) |
| Branch finance API | [branch-finance-api.md](./branch-finance-api.md) |
| Branch transaction edits | [flutter-branch-transaction-edits.md](./flutter-branch-transaction-edits.md) |

---

## Older but still valid

- [flutter-windows-recent-updates.md](./flutter-windows-recent-updates.md) — master changelog
- [flutter-dev-updates-may-2026.md](./flutter-dev-updates-may-2026.md) — dashboard & financial reports
