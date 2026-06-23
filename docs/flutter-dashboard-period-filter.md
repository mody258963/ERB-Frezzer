# Flutter Dashboard — Day / Week / Month Filter

Filter dashboard metrics by **day**, **week**, or **month** using query params on existing dashboard endpoints.

---

## Query parameters

| Param | Values | Default | Meaning |
|-------|--------|---------|---------|
| `period` | `day`, `week`, `month` | `week` | Which time window to use |
| `date` | `YYYY-MM-DD` | today | Anchor date for the window |
| `branch_id` | UUID | — | Admin branch filter (same as before) |

### How windows are calculated

| `period` | Range |
|----------|--------|
| `day` | Start of selected day → end of selected day |
| `week` | **Business week:** Monday **09:00** → Saturday **23:59:59** (Sunday = completed week; Mon before 9 AM = previous week) |
| `month` | Start of month containing `date` → end of that month |

**Business week rules** (shop owner request, June 2026):

- Week **starts** Monday at **09:00** (server timezone).
- Week **ends** Friday at **23:59:59**.
- **Saturday / Sunday:** API returns the week that **ended last Friday**.
- **Monday before 09:00:** still in the **previous** business week.

Do **not** compute week boundaries in Flutter — read `period.from` and `period.to` from the response.

See [flutter-client-requests-june-2026-shop-owner.md](./flutter-client-requests-june-2026-shop-owner.md).

---

## Endpoints that support `period`

| Endpoint | Use |
|----------|-----|
| `GET /api/v1/dashboard/summary` | Main KPI cards |
| `GET /api/v1/dashboard/sales` | Sales breakdown |
| `GET /api/v1/dashboard/cash` | Realized cash boxes |

**Not filtered by period** (always current snapshot):

- `GET /dashboard/inventory`
- `GET /dashboard/receivables`
- `GET /dashboard/payables`

---

## Example requests

```http
GET /api/v1/dashboard/summary?period=day
GET /api/v1/dashboard/summary?period=week&branch_id={uuid}
GET /api/v1/dashboard/summary?period=month&date=2026-06-01
GET /api/v1/dashboard/sales?period=day
GET /api/v1/dashboard/cash?period=month
```

---

## Response shape

Every filtered response includes:

```json
{
  "period": {
    "key": "week",
    "from": "2026-06-16T00:00:00+03:00",
    "to": "2026-06-22T23:59:59+03:00",
    "anchor_date": "2026-06-16"
  }
}
```

### Summary — prefer `period_*` fields

| Field | Meaning |
|-------|---------|
| `period_revenue` | Invoice subtotals in selected window |
| `period_discount` | Discounts in window |
| `period_customer_refunds` | Refunds in window |
| `period_net_sales` | Net sales in window |
| `period_gross_profit` | Gross profit in window |
| `period_profit` | Profit after discount + refunds |
| `period_supplier_payments` | Supplier installments paid in window |
| `period_purchases_ordered` | POs created in window |
| `period_purchases_received` | POs received in window |
| `period_cash_in_realized` | Real cash-in in window |
| `period_cash_out_realized` | Real cash-out in window |
| `period_net_cash_flow_realized` | Cash-in − cash-out in window |

### Always current (not period-scoped)

| Field | Meaning |
|-------|---------|
| `total_receivables` | Credit customers still owe |
| `must_collect_customers` | Same — obligations to collect |
| `total_supplier_debt` / `must_pay_suppliers` | Still owe suppliers |
| `total_stock_value_cost` | Inventory value now |
| `cash_on_hand_realized` | Lifetime realized cash position |
| `business_capital` | Configured capital |

### Backward compatibility

`weekly_*` fields still exist and mirror `period_*` for the **selected** period.

- If `period=week` → `weekly_revenue` === `period_revenue`
- If `period=day` → `weekly_revenue` is **today’s** revenue (name is legacy)

**New UI should bind cards to `period_*`**, not `weekly_*`.

---

## Flutter UI recommendation

Segmented control on dashboard:

```
[ Today ] [ This week ] [ This month ]
```

Map to:

```dart
enum DashboardPeriod { day, week, month }

Future<DashboardSummary> loadSummary({
  required DashboardPeriod period,
  DateTime? anchorDate,
  String? branchId,
}) async {
  final res = await _dio.get('/dashboard/summary', queryParameters: {
    'period': period.name,
    if (anchorDate != null) 'date': _formatDate(anchorDate),
    if (branchId != null) 'branch_id': branchId,
  });
  return DashboardSummary.fromJson(res.data);
}
```

Refresh after any transaction that changes money:

- Invoice create
- Customer payment / settlement
- Supplier installment pay
- Purchase create / receive
- Return approve

---

## QA checklist

- [ ] `period=day` shows only today’s sales
- [ ] `period=month&date=...` shows that calendar month
- [ ] `must_collect_customers` unchanged when switching period (obligations, not period metrics)
- [ ] `cash_on_hand_realized` unchanged when switching period (lifetime cash)
- [ ] Sales screen uses same `period` param as summary

---

## See also

- [flutter-dashboard-period-numbers-validation.md](./flutter-dashboard-period-numbers-validation.md) — field mapping, formulas, screenshot QA, and common Flutter mistakes when binding day/week/month numbers
- [flutter-client-requests-june-2026-shop-owner.md](./flutter-client-requests-june-2026-shop-owner.md) — supplier lump-sum pay, business week Mon 9 AM–Fri, daily drawer
