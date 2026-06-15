# Flutter — decimal quantities & branch transfers (June 2026)

**Audience:** Flutter Windows POS/ERP (`erd_rezzer`)  
**Backend:** ERB-Frezzer API v1  
**Date:** June 2026

This document covers **meter/fractional selling**, **decimal stock everywhere**, and **branch transfer line valuation**. Logic for the app — not Dart implementation.

**Related:** [flutter-per-branch-isolation.md](./flutter-per-branch-isolation.md), [flutter-branch-switching-guide.md](./flutter-branch-switching-guide.md), [flutter-weighted-average-cost.md](./flutter-weighted-average-cost.md), [part-categories-units-api.md](./part-categories-units-api.md), [flutter-admin-transaction-edits.md](./flutter-admin-transaction-edits.md)

---

## 1. Summary — what changed

| Area | Change |
|------|--------|
| **Invoices (POS)** | Decimal `quantity` for meter/kg/liter parts (e.g. `0.5`, `0.25`) |
| **Purchases** | Same decimal rules on receive lines |
| **Returns** | Decimal return qty vs sold qty for fractional units |
| **Stock / inventory** | `quantity` stored and returned as **decimal** (4 dp) |
| **Transfers** | Multi-line, decimal qty, optional per-line `unit_cost` on create |
| **Stock errors** | `failures[].requested` / `available` are **numbers**, not integers |
| **Parts JSON** | Always includes `unit` + `unit_label` — use for POS input mode |

**Backend migration (production):** run `php artisan migrate --force` only. **Do not** `migrate:refresh` on production.

---

## 2. Which units allow decimals?

The API validates quantity **per part unit** (`part.unit`):

| `unit` value | Label | Decimal qty allowed? | POS examples |
|--------------|-------|----------------------|--------------|
| `m` | Meter | **Yes** | `0.25`, `0.5`, `1.75` |
| `kg` | Kilogram | **Yes** | `0.5`, `2.25` |
| `l` | Liter | **Yes** | `0.5`, `1.5` |
| `pc` | Piece | No — whole numbers only | `1`, `2`, `10` |
| `box`, `set`, `roll`, `pack` | — | No — whole numbers only | `1`, `2` |

**App logic:**

```
IF part.unit is m, kg, or l
  → quantity field: decimal keyboard, steps 0.25 / 0.5 / custom
ELSE
  → quantity field: integer only, min 1
```

Do not hard-code “meter only” in one screen — use `unit` from the part object everywhere (POS, purchases, transfers, returns).

---

## 3. Parse quantities as numbers (not integers)

All API quantity fields are now **JSON numbers** (floats). Existing integer stock (`10`) becomes `10.0` in responses.

| Endpoint / field | Type in JSON |
|------------------|--------------|
| `invoice_items.quantity` | number |
| `quantity_sold`, `quantity_remaining`, `quantity_available_for_return` | number |
| `stock.quantity`, `inventory` nested qty | number |
| `return_items.quantity` | number |
| `transfer items[].quantity` | number |
| `failures[].requested`, `failures[].available` | number |

**Flutter rule:** model all of these as `double` (or `num`), never `int`. Display with sensible formatting (e.g. `1.5 m`, strip trailing zeros for whole numbers).

---

## 4. POS / invoice create

```http
POST /api/v1/invoices
```

```json
{
  "customer_id": "uuid",
  "branch_id": "uuid",
  "payment_type": "cash",
  "items": [
    { "part_id": "uuid", "quantity": 0.5, "unit_price": 120.0 },
    { "part_id": "uuid", "quantity": 0.25, "unit_price": 80.0 }
  ]
}
```

**Logic:**

1. Load part from branch catalog; read `unit` and `unit_label`.
2. If fractional unit → allow decimal qty; line total = `unit_price × quantity` (server calculates the same).
3. If piece unit → block decimals in UI before submit (server returns **422** if you send `0.5` for `pc`).
4. On **422 insufficient stock**, read `failures[]`:

```json
{
  "message": "Insufficient stock for one or more lines.",
  "failures": [
    { "part_id": "uuid", "requested": 0.5, "available": 0.25 }
  ]
}
```

Show: “Requested 0.5 m, available 0.25 m” — use `unit_label` from the part for display.

**Branch:** non-admin users must sell from their assigned branch; admin must send the same `branch_id` on POST as on catalog GET (see [flutter-branch-switching-guide.md](./flutter-branch-switching-guide.md)).

---

## 5. Purchases

```http
POST /api/v1/purchases
```

```json
{
  "supplier_id": "uuid",
  "branch_id": "uuid",
  "payment_type": "immediate",
  "items": [
    { "part_id": "uuid", "quantity": 10.5, "unit_cost": 45.50 }
  ]
}
```

Same unit rules as invoices. For cable sold by the meter, receiving `10.5` m is valid.

---

## 6. Returns

Customer returns against an invoice support decimal quantities for `m` / `kg` / `l` parts.

**Logic:**

- `quantity_available_for_return` on invoice show/receipt is a **number** (may be `0.75`).
- User cannot return more than available (422 + `failures` if exceeded).
- Use the same decimal vs integer input rules as the original sale.

---

## 7. Branch transfers

### 7.1 Create (multi-line, decimal qty)

```http
POST /api/v1/transfers
```

```json
{
  "from_branch_id": "uuid",
  "to_branch_id": "uuid",
  "notes": "optional",
  "items": [
    { "part_id": "uuid-a", "quantity": 2.5, "unit_cost": 150.0 },
    { "part_id": "uuid-b", "quantity": 1.25, "unit_cost": 60.0 }
  ]
}
```

| Field | Required | Notes |
|-------|----------|--------|
| `from_branch_id` | Yes | Source branch |
| `to_branch_id` | Yes | Must differ from source |
| `items` | Yes | **One or more lines** in a single request |
| `items[].part_id` | Yes | |
| `items[].quantity` | Yes | Decimal OK for `m` / `kg` / `l` |
| `items[].unit_cost` | No | Override valuation for that line (see below) |
| `notes` | No | |

**Response (201):** each item includes `quantity`, `unit_cost` (null if omitted), and nested `part` with `unit` / `unit_label`.

**There is no PATCH to edit lines** after create. Set `unit_cost` on create, or leave it out and rely on complete-time valuation.

### 7.2 Complete

```http
PATCH /api/v1/transfers/{id}/complete
```

```json
{
  "valuation": "cost",
  "record_branch_charge": true
}
```

| Field | Values | Meaning |
|-------|--------|---------|
| `valuation` | `cost` (default) or `sell` | Basis for **inter-branch charge** amount |
| `record_branch_charge` | `true` / `false` (default true) | Create branch finance entry |

**How `unit_cost` on each line is used at complete:**

| `valuation` | Inter-branch charge uses | Destination WAC uses |
|-------------|--------------------------|----------------------|
| `cost` | Line `unit_cost` if set, else source average / catalog cost | Line `unit_cost` if set, else source WAC |
| `sell` | `part.sell_price × quantity` | Line `unit_cost` if set, else source WAC |

Example: two lines `2.5 × 150 + 1.25 × 60 = 450` inter-branch charge when `valuation: cost` and both lines have `unit_cost`.

### 7.3 Transfer UI checklist

- [ ] Add multiple parts to one transfer before submit
- [ ] Decimal qty input when `part.unit` is `m` / `kg` / `l`
- [ ] Optional “unit cost” column per line (admin/manager override)
- [ ] Complete screen: choose `cost` vs `sell` valuation
- [ ] After complete, refresh inventory for **both** branches

### 7.4 Edit pending transfer (admin)

Before `complete`, admin can fix quantities (e.g. 2 regulators → 1):

```http
PATCH /api/v1/transfers/{id}
```

Full guide: [flutter-admin-transaction-edits.md](./flutter-admin-transaction-edits.md).

---

## 8. Parts catalog & inventory — `unit` fields

`GET /api/v1/parts` and inventory endpoints (`GET /api/v1/inventory`, `GET /api/v1/inventory/{branchId}`) return:

```json
{
  "unit": "m",
  "unit_label": "Meter"
}
```

On inventory rows, the same fields appear on nested `part`.

**POS logic:** cache `unit` on the catalog model; when user adds a line, switch quantity widget mode from `unit` alone — do not infer from category name.

---

## 9. Inventory adjust (opening / manual stock)

```http
POST /api/v1/inventory/adjust
```

```json
{
  "part_id": "uuid",
  "branch_id": "uuid",
  "quantity_delta": 2.5,
  "unit_cost": 100
}
```

`quantity_delta` follows the same fractional rules (decimal for `m` / `kg` / `l`). Optional `unit_cost` when adding stock — see [flutter-weighted-average-cost.md](./flutter-weighted-average-cost.md).

---

## 10. User ↔ branch (reminder)

Branch-locked users (`manager`, `salesperson`, `warehouse`) only see and create data in **their** `branch_id`. Admin picks branch via dropdown + `?branch_id=` / header / body on every call.

See [flutter-per-branch-isolation.md](./flutter-per-branch-isolation.md) for full rules.

---

## 11. Flutter implementation checklist

### Data models
- [ ] Change quantity fields from `int` to `double` on invoice, stock, transfer, return, purchase models
- [ ] Add optional `unitCost` on transfer line model

### POS
- [ ] Read `part.unit` when adding a cart line
- [ ] Fractional units: decimal input, suggested steps `0.25`, `0.5`, `1`
- [ ] Piece units: integer stepper only
- [ ] Show `unit_label` next to quantity (e.g. `0.5 Meter`)

### Error handling
- [ ] Parse 422 `failures` as numbers for stock and return limit errors
- [ ] Map `part_id` in failures back to part name/code in the UI

### Transfers
- [ ] Multi-item cart before POST
- [ ] Optional per-line unit cost field
- [ ] Complete with `valuation: cost|sell`

### Sync / offline (if applicable)
- [ ] Pending invoice/transfer payloads store decimal quantities as numbers in local DB

---

## 12. Quick reference — endpoints

| Action | Method | Decimal qty | Notes |
|--------|--------|-------------|--------|
| Sell | `POST /invoices` | Yes (`m`,`kg`,`l`) | Optional `unit_price` override |
| Purchase | `POST /purchases` | Yes | `unit_cost` required per line |
| Return | `POST /returns` | Yes | Max = available on invoice |
| Transfer create | `POST /transfers` | Yes | Optional `items[].unit_cost` |
| Transfer complete | `PATCH /transfers/{id}/complete` | — | `valuation: cost\|sell` |
| Stock adjust | `POST /inventory/adjust` | Yes | `quantity_delta` |

---

## 13. Testing scenarios (QA)

1. Create meter part (`unit: m`), stock 10 m, sell 0.5 + 0.25 → stock 9.25 m, total correct.
2. Try sell `0.5` of a **piece** part → 422 validation.
3. Sell 0.5 m when only 0.25 m in stock → 422 with decimal `requested` / `available`.
4. Transfer 2.5 m with `unit_cost: 150`, complete → destination stock +2.5, branch charge 375 (cost basis).
5. Return 0.25 m from invoice that sold 0.5 m → `quantity_remaining` 0.25.
