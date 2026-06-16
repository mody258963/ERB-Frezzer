# Flutter Dashboard — Real Cash Boxes (June 2026)

This update adds dashboard values that represent **only money that already happened in real life**.

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
- `weekly_cash_in_realized` (float): real cash-in this week
- `weekly_cash_out_realized` (float): real cash-out this week
- `weekly_net_cash_flow_realized` (float): weekly in - out
- `legacy_estimated_available` (float): old formula (kept for backward compatibility)

`capital_estimated_available` now returns the same realized value as `cash_on_hand_realized`.

### 2) New endpoint for cash boxes

`GET /api/v1/dashboard/cash`

Same branch behavior as other dashboard endpoints (`?branch_id=` for admin, locked branch for non-admin).

Example response:

```json
{
  "must_collect_customers": 100.0,
  "must_pay_suppliers": 100.0,
  "cash_on_hand_realized": 250000.0,
  "lifetime_cash_in_realized": 75000.0,
  "lifetime_cash_out_realized": 25000.0,
  "weekly_cash_in_realized": 12000.0,
  "weekly_cash_out_realized": 5000.0,
  "weekly_net_cash_flow_realized": 7000.0
}
```

---

## Realized-cash logic (important)

### What increases realized cash-in

- Cash invoices (`payment_type = cash`)
- Customer payments collected from credit customers
- Saturday settlements with non-offset payment method

### What increases realized cash-out

- Supplier installment payments (non-offset)
- Customer return cash refunds (`refund_cash`, `writeoff`)
- Owner cash-out

### What does NOT change realized cash-on-hand

- Creating purchase orders (debt created but no cash paid yet)
- Creating credit invoices (receivable created but no cash collected yet)
- Credit-note returns (balance adjustment without cash movement)

---

## Flutter UI mapping (recommended boxes)

Use these cards in dashboard:

1. `cash_on_hand_realized` → **Cash on hand**
2. `must_collect_customers` → **Must collect from customers**
3. `must_pay_suppliers` → **Must pay suppliers**
4. `weekly_net_cash_flow_realized` → **Weekly net cash flow**

Optional detail chips:

- `weekly_cash_in_realized`
- `weekly_cash_out_realized`

---

## Migration / compatibility notes

- Existing clients can continue reading old summary fields.
- New Flutter screens should switch to the realized cash fields above.
- If you still display `legacy_estimated_available`, label it clearly as **legacy estimate**, not real cash.
