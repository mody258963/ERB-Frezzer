# Flutter developer guide — May 2026 API updates

For the **erd_rezzer** / FrostParts Windows client talking to **ERB-Frezzer** (`https://api.tppower.shop/api/v1` or your server).

Related: [flutter-dashboard-transactions-guide.md](./flutter-dashboard-transactions-guide.md) (**full transaction flows + dashboard fields**), [flutter-owner-cash-out.md](./flutter-owner-cash-out.md) (admin owner withdrawal), [flutter-supplier-installment-partial-pay.md](./flutter-supplier-installment-partial-pay.md) (pay installment with custom amount), [flutter-invoice-partial-return-reprint.md](./flutter-invoice-partial-return-reprint.md) (partial return + reprint), [flutter-mobile-inventory-intake.md](./flutter-mobile-inventory-intake.md) (mobile photo intake + dashboard/activity), [flutter-app-fixes.md](./flutter-app-fixes.md), [flutter-windows-recent-updates.md](./flutter-windows-recent-updates.md), [customer-returns-ar.md](./customer-returns-ar.md), [invoice-discount-accounting-ar.md](./invoice-discount-accounting-ar.md).

---

## 1. Dashboard — profit and returns

### Summary `GET /api/v1/dashboard/summary`

Optional query: `?branch_id={uuid}` (admin only; branch users are scoped automatically).

| Field | Meaning |
|-------|---------|
| `weekly_revenue` | Gross sales (sum of invoice `subtotal`, before discount) |
| `weekly_discount` | Invoice discounts this week |
| `weekly_customer_refunds` | Completed customer refunds (cash / defective refund / credit note) |
| `weekly_net_sales` | Invoice `total` minus refunds |
| `weekly_gross_profit` | Sum of `(unit_price − cost_price) × qty` |
| `weekly_profit` | Gross profit − discount − refunds |
| `total_supplier_debt` | All unpaid supplier balance (updates when you order or pay installments) |
| `weekly_supplier_payments` | Installments **paid this week** (`paid_at`) |
| `weekly_purchases_ordered` | Purchase orders **created this week** |
| `weekly_purchases_received` | POs **received into stock this week** |
| `unpaid_installments_total` | Sum of unpaid installment amounts |
| `unpaid_installments_count` | Count of unpaid installments |
| `overdue_installments_total` | Unpaid installments past `due_date` |

**UI:** Show **weekly profit** and **refunds**, not `weekly_revenue` alone.  
**Purchases:** After creating a 100k PO with 4 installments, show `total_supplier_debt` and `unpaid_installments_total`. When paying one installment (25k), refresh summary — `weekly_supplier_payments` and debt should update.  
Also use `GET /dashboard/payables` for upcoming/overdue installment lists.

### Sales breakdown `GET /api/v1/dashboard/sales`

Optional: `?branch_id={uuid}`.

New top-level **`totals`** object (same keys as financial report totals).

`by_branch[]` now includes:

- `branch_id`, `revenue`, `discount`, `customer_refunds`, `profit`, `total` (net invoice total)

---

## 2. Financial reports

### New endpoint `GET /api/v1/reports/financial`

Query:

| Param | Required | Notes |
|-------|----------|-------|
| `from` | No | `YYYY-MM-DD`; default = start of current month |
| `to` | No | `YYYY-MM-DD`; default = today |
| `branch_id` | No | Admin: any branch; others: forced to their branch |

Example:

```http
GET /api/v1/reports/financial?from=2026-05-01&to=2026-05-31
Authorization: Bearer {token}
```

Response (abbreviated):

```json
{
  "period": { "from": "2026-05-01", "to": "2026-05-31", "branch_id": null },
  "totals": {
    "revenue": 2400,
    "discount": 200,
    "customer_refunds": 120,
    "net_sales": 2080,
    "gross_profit": 700,
    "profit": 580
  },
  "returns": {
    "customer_count": 3,
    "customer_value": 120,
    "supplier_count": 0,
    "supplier_value": 0
  },
  "by_branch": [
    {
      "branch_id": "...",
      "name": "Main Branch",
      "revenue": 1200,
      "discount": 50,
      "customer_refunds": 60,
      "gross_profit": 400,
      "profit": 290
    }
  ]
}
```

**UI:** Use this for P&amp;L / reports screens. Keep `GET /reports/sales` for invoice line drill-down only.

Returns are counted by **approval date** (`completed_at`), not create date.

---

## 3. Customer returns (reminder)

- `reference_id` = **invoice UUID**, not customer id.
- `reference_type` = `invoice`
- Hide “Return” when `invoice.return_status == 'returned'`.
- Approve with `PATCH /returns/{id}/approve` and resolution `refund_cash`, `writeoff`, or `credit_note` for financial impact.

See [flutter-app-fixes.md](./flutter-app-fixes.md) § Returns.

---

## 4. Admin — branches and users

### Branch picker

`GET /api/v1/branches/active`

- **Admin:** all active branches.
- **Branch user:** only their branch.

Use with login / POS branch selector.

### User flags on login and `GET /auth/me`

```json
{
  "role": "admin",
  "branch_id": null,
  "can_select_branch": true,
  "accessible_branch_ids": null
}
```

Branch user example:

```json
{
  "role": "salesperson",
  "branch_id": "uuid",
  "can_select_branch": false,
  "accessible_branch_ids": ["uuid"]
}
```

- `can_select_branch: true` → show branch dropdown (load `/branches/active`).
- `accessible_branch_ids: null` → all branches (admin).
- Otherwise use the single id in the list.

### User management (admin only)

| Method | Path |
|--------|------|
| GET | `/users?branch_id=&role=` |
| POST | `/users` |
| GET | `/users/{id}` |
| PUT/PATCH | `/users/{id}` |
| DELETE | `/users/{id}` (sets `is_active: false`) |

Create body example:

```json
{
  "name": "Cashier 1",
  "email": "cashier1@shop.com",
  "password": "secure-password",
  "role": "salesperson",
  "branch_id": "branch-uuid"
}
```

`branch_id` required for `salesperson` and `warehouse`; optional for `admin` and `manager`.

### POS invoice branch

- **Admin** may post invoices to **any** `branch_id`.
- **Branch users** get **422** if `branch_id` ≠ their assigned branch.

---

## 5. Deploy checklist (backend)

On the server after pulling API changes:

```bash
php artisan migrate
php artisan cache:clear
```

If you use `migrate:fresh` in dev only, returns table includes `completed_at`.

---

## 6. Business capital (Settings)

Admin sets **owner / business capital** (e.g. 100,000 EGP) before or during operations. Used for financing overview (stock + receivables vs capital).

| Method | Path | Who |
|--------|------|-----|
| GET | `/settings/capital` | Any authenticated user (read) |
| PUT/PATCH | `/settings/capital` | **Admin only** |
| GET | `/settings/capital/adjustments` | **Admin only** (history) |

**Update body:**

```json
{
  "capital_amount": 100000,
  "reason": "Initial capital",
  "notes": "Owner funding EGP"
}
```

**Response** includes `financing_snapshot`:

| Field | Meaning |
|-------|---------|
| `inventory_at_cost` | Stock value at cost |
| `customer_receivables` | Outstanding customer balances |
| `supplier_debt` | Unpaid supplier debt |
| `deployed_capital` | Inventory + receivables |
| `estimated_available` | Rough cash left ≈ capital − deployed − supplier_debt |

**Dashboard** `GET /dashboard/summary` also returns:

- `business_capital`
- `capital_currency` (default `EGP`)
- `capital_estimated_available`

**Reports** `GET /reports/financial` includes a `capital` block with the same snapshot.

**Flutter Settings screen:** add “Business capital” form (admin only to save); managers can view.

---

## 7. Quick Dart checklist

- [ ] Dashboard cards: `weekly_profit`, `weekly_customer_refunds`, `weekly_net_sales`
- [ ] Reports screen: call `/reports/financial` with date range (+ branch filter for admin)
- [ ] Returns: invoice id as `reference_id`; respect `return_status`
- [ ] Settings (admin): user list + create via `/users`
- [ ] Settings (admin): business capital via `/settings/capital`
- [ ] POS: branch selector when `can_select_branch == true`
