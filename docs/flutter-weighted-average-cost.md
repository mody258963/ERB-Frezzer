# Flutter — weighted average cost (June 2026)

Part cost is **automatic per branch**. Do not ask users to type `cost_price` after the catalog exists — purchases update it.

## How it works

Each `stock` row (part + branch) has `average_cost`. When goods are received:

```
new average = (old qty × old average + new qty × purchase price) ÷ (old qty + new qty)
```

Example: 10 @ 100, then 10 @ 120 → **110** average.

## API changes

### Part create

`cost_price` is **optional** (defaults to `0`). Opening cost comes from the first purchase receive or a positive inventory adjust with `unit_cost`.

```http
POST /api/v1/parts
{ "code": "P-1", "name": "Filter", "sell_price": 200, "min_stock": 0, ... }
```

### Part update

**Do not** send `cost_price` on update — the server ignores it. Show cost as read-only in the UI.

### Purchases

Receiving a PO updates branch average cost from each line’s `unit_cost`:

```http
PATCH /api/v1/purchases/{id}/receive
```

### Inventory adjust (positive qty)

Optional `unit_cost` when adding stock manually:

```http
POST /api/v1/inventory/adjust
{
  "part_id": "uuid",
  "branch_id": "uuid",
  "quantity_delta": 5,
  "unit_cost": 100
}
```

If omitted, server uses current branch average (or part rollup).

### Dashboard inventory

`GET /api/v1/dashboard/inventory?branch_id=` rows include:

| Field | Meaning |
|-------|---------|
| `average_cost` | Branch WAC for that part |
| `value_at_cost` | `quantity × average_cost` |

`GET /api/v1/dashboard/summary` → `total_stock_value_cost` uses WAC, not manual catalog cost.

### Sales / profit

Invoice lines store `unit_cost` at sale time. Dashboard `weekly_gross_profit` uses that snapshot — later purchases do not rewrite old profit.

## UI guidance

1. **Remove** manual cost field from part edit screens (or show read-only `cost_price` rollup).
2. **Show** `average_cost` on warehouse/inventory views per branch.
3. **Purchase receive** is the normal way cost changes — no extra client logic.
4. With admin branch filter, inventory and costs follow `?branch_id=`.

## Related docs

- [flutter-admin-branch-filter.md](./flutter-admin-branch-filter.md) — branch scoping
- [architecture.md](./architecture.md) — server-side costing rules
