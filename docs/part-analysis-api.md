# Part / product analysis API

Analyze a **single part** (product): stock levels, sales, purchases, returns, profit, stock movements, and monthly sales trend.

**Base URL:** `{host}/api/v1`  
**Auth:** `Authorization: Bearer <token>` (Laravel Passport)

---

## Endpoint

```http
GET /parts/{id}/analysis
```

| Item | Value |
|------|--------|
| Method | `GET` |
| Path param | `id` — part UUID |
| Success | `200 OK` |
| Not found | `404` — unknown part |
| Unauthorized | `401` — missing/invalid token |

### Query parameters

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| `from` | date (`Y-m-d`) | No | Start of period (inclusive) for sales, purchases, returns, movements |
| `to` | date (`Y-m-d`) | No | End of period (inclusive). Must be `>= from` if both set |
| `branch_id` | UUID | No | Filter metrics to one branch |

**Branch scoping**

- Users **with** `branch_id` (e.g. salesperson, warehouse): always scoped to their branch; `branch_id` query is ignored.
- Users **without** `branch_id` (e.g. admin): optional `branch_id` filters all sections; omit for company-wide totals.

### Example requests

```http
GET /api/v1/parts/019e30c6-xxxx-xxxx-xxxx-xxxxxxxxxxxx/analysis
```

```http
GET /api/v1/parts/019e30c6-xxxx-xxxx-xxxx-xxxxxxxxxxxx/analysis?from=2026-01-01&to=2026-05-31
```

```http
GET /api/v1/parts/019e30c6-xxxx-xxxx-xxxx-xxxxxxxxxxxx/analysis?branch_id=019e30c6-yyyy-yyyy-yyyy-yyyyyyyyyyyy
```

**Postman:** collection folder **Parts** → **Part analysis**

---

## Response structure

Top-level keys:

| Key | Description |
|-----|-------------|
| `part` | Part master data |
| `period` | Echo of applied filters |
| `inventory` | Current stock snapshot |
| `sales` | Sales & profitability in period |
| `purchases` | Inbound PO lines (received orders) |
| `returns` | Approved/completed customer returns |
| `movements` | Stock movement summary + recent list |
| `sales_by_month` | Time series for charts |

### Example response

```json
{
  "part": {
    "id": "019e30c6-3fc9-7383-be2d-dd5bc8a4da40",
    "code": "BRK-001",
    "name": "Brake pad",
    "category": "Compressor",
    "unit": "pc",
    "sell_price": 150.0,
    "cost_price": 80.0,
    "min_stock": 5,
    "is_active": true,
    "created_at": "2026-05-01T10:00:00.000000Z",
    "updated_at": "2026-05-01T10:00:00.000000Z"
  },
  "period": {
    "from": "2026-01-01",
    "to": "2026-05-31",
    "branch_id": null
  },
  "inventory": {
    "total_quantity": 46,
    "min_stock": 5,
    "is_below_min_stock": false,
    "value_at_cost": 3680.0,
    "value_at_sell": 6900.0,
    "margin_per_unit": 70.0,
    "by_branch": [
      {
        "branch_id": "019e30c6-aaaa-aaaa-aaaa-aaaaaaaaaaaa",
        "branch_name": "Main",
        "quantity": 46
      }
    ]
  },
  "sales": {
    "units_sold": 120,
    "revenue": 18000.0,
    "invoice_count": 34,
    "estimated_cogs": 9600.0,
    "gross_profit": 8400.0,
    "gross_margin_percent": 46.67
  },
  "purchases": {
    "units_purchased": 200,
    "cost": 16000.0,
    "order_count": 8
  },
  "returns": {
    "units_returned": 3,
    "value": 450.0,
    "return_count": 2
  },
  "movements": {
    "by_type": [
      { "movement_type": "sale_out", "quantity": -120 },
      { "movement_type": "purchase_in", "quantity": 200 },
      { "movement_type": "adjustment", "quantity": 5 }
    ],
    "recent": [
      {
        "id": "uuid",
        "movement_type": "sale_out",
        "quantity": -2,
        "branch_id": "uuid",
        "branch_name": "Main",
        "reference_id": "invoice-uuid",
        "reference_type": "invoice",
        "notes": null,
        "created_by": "user-uuid",
        "created_by_name": "Sara",
        "created_at": "2026-05-16T12:00:00.000000Z"
      }
    ]
  },
  "sales_by_month": [
    { "month": "2026-03", "units_sold": 40, "revenue": 6000.0 },
    { "month": "2026-04", "units_sold": 50, "revenue": 7500.0 },
    { "month": "2026-05", "units_sold": 30, "revenue": 4500.0 }
  ]
}
```

---

## Field reference

### `part`

Same shape as `GET /parts/{id}` — see `PartTransformer`.

### `period`

| Field | Type | Notes |
|-------|------|--------|
| `from` | string \| null | Applied start date |
| `to` | string \| null | Applied end date |
| `branch_id` | string \| null | Effective branch filter after user scoping |

### `inventory` (current snapshot)

Not limited by `from`/`to` — reflects **live** stock.

| Field | Type | Description |
|-------|------|-------------|
| `total_quantity` | int | Sum of `by_branch[].quantity` |
| `min_stock` | int | From part master |
| `is_below_min_stock` | bool | `total_quantity < min_stock` |
| `value_at_cost` | float | `total_quantity × cost_price` |
| `value_at_sell` | float | `total_quantity × sell_price` |
| `margin_per_unit` | float | `sell_price − cost_price` |
| `by_branch` | array | Per-branch quantities |

### `sales` (period)

From `invoice_items` joined to active invoices (cancelled invoices are deleted and excluded).

| Field | Type | Description |
|-------|------|-------------|
| `units_sold` | int | Sum of line quantities |
| `revenue` | float | Sum of line `total` |
| `invoice_count` | int | Distinct invoices |
| `estimated_cogs` | float | `units_sold ×` current `part.cost_price` |
| `gross_profit` | float | `revenue − estimated_cogs` |
| `gross_margin_percent` | float | `(gross_profit / revenue) × 100`, or `0` if no revenue |

### `purchases` (period)

From `purchase_order_items` where PO status is `partial` or `settled` (received goods).

| Field | Type | Description |
|-------|------|-------------|
| `units_purchased` | int | Sum of quantities |
| `cost` | float | Sum of line totals |
| `order_count` | int | Distinct purchase orders |

### `returns` (period)

From `return_items` where return status is `approved` or `completed`.

| Field | Type | Description |
|-------|------|-------------|
| `units_returned` | int | Sum of quantities |
| `value` | float | Sum of line totals |
| `return_count` | int | Distinct returns |

### `movements`

| Field | Type | Description |
|-------|------|-------------|
| `by_type` | array | Net quantity per `movement_type` in period |
| `recent` | array | Last **25** movements (newest first) |

**Movement types** (`movement_type`):

| Value | Meaning |
|-------|---------|
| `purchase_in` | Stock from received PO |
| `sale_out` | Stock out on sale (negative qty) |
| `transfer_in` | Transfer into branch |
| `transfer_out` | Transfer out of branch |
| `return_in` | Return restocked |
| `return_out` | Return out |
| `adjustment` | Manual adjust / invoice cancel restore |

### `sales_by_month`

| Field | Type | Description |
|-------|------|-------------|
| `month` | string | `YYYY-MM` |
| `units_sold` | int | Units in that month |
| `revenue` | float | Revenue in that month |

Respects `from`, `to`, and branch filter. Useful for line/bar charts in Flutter or web.

---

## UI suggestions (Flutter / desktop)

**Route:** `/parts/:id/analysis` (online only)

```
┌─────────────────────────────────────────────────────────────┐
│  BRK-001 — Brake pad                    [from] [to] [Apply]   │
├─────────────────────────────────────────────────────────────┤
│  Stock: 46   Min: 5   Value (sell): 6,900   Low stock: No   │
│  Sold: 120   Revenue: 18,000   Profit: 8,400   Margin: 47%  │
├──────────────────────────┬──────────────────────────────────┤
│  Sales by month (chart)  │  Stock by branch                 │
├──────────────────────────┴──────────────────────────────────┤
│  Recent movements (table)                                     │
└─────────────────────────────────────────────────────────────┘
```

---

## Implementation (backend)

| File | Role |
|------|------|
| `routes/api.php` | `GET /parts/{id}/analysis` |
| `app/Http/Controllers/Api/V1/PartController.php` | `analysis()` |
| `app/Services/PartAnalysisService.php` | Aggregations |
| `app/Http/Resources/PartAnalysisResource.php` | JSON wrapper |
| `tests/Feature/PartAnalysisTest.php` | Feature tests |

Run tests:

```bash
php artisan test tests/Feature/PartAnalysisTest.php
```

---

## Related endpoints

| Need | Endpoint |
|------|----------|
| Part CRUD | `GET/POST/PUT/DELETE /parts` |
| All parts valuation | `GET /reports/inventory` |
| Branch stock list | `GET /inventory/{branchId}` |
| Sales report (invoices) | `GET /reports/sales` |

---

## Errors

| Status | Cause |
|--------|--------|
| `401` | No token or expired token |
| `404` | Part `id` does not exist |
| `422` | Invalid `from`/`to`/`branch_id` validation |

Validation example (`422`):

```json
{
  "message": "The to field must be a date after or equal to from.",
  "errors": {
    "to": ["The to field must be a date after or equal to from."]
  }
}
```
