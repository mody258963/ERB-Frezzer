# ⚠️ BREAKING — Owner cash out now uses profit margin (not capital)

**Audience:** Flutter (`erd_rezzer`) developers  
**Date:** June 2026  
**Arabic (client request):** السحب النقدي يُخصم من **هامش الربح** وليس من **رأس المال**

---

## What changed

| Before (old) | After (new) |
|--------------|-------------|
| Cash out reduced stored `capital_amount` | **`opening_cash_balance` stays unchanged** |
| Max amount = `capital_amount` | Max amount = **`withdrawable_profit`** |
| Validated against capital | Validated against **realized profit − prior cash outs** |

**June 2026 capital definition:** `business_capital` = مخزون + نقد. Cash out removes physical cash, so **`business_capital` and `cash_on_hand_realized` decrease**, but admin **opening cash** (`opening_cash_balance` / `capital_amount` in settings) does **not** change. See [flutter-business-capital-definition.md](./flutter-business-capital-definition.md).

---

## ⚠️ Developer warnings

1. **Do not** use `capital_amount` / `opening_cash_balance` as the max for the cash-out form.
2. **Do not** expect `opening_cash_balance` to drop after cash out — it is admin-set only.
3. **Do** expect `business_capital` and `cash_on_hand_realized` to drop (cash left the drawer).
4. **Do not** subtract cash outs from `opening_cash_balance` in local UI math.
4. **Do** show a clear label: *"سحب من الأرباح"* / *"Withdraw from profit"*.
5. If `withdrawable_profit` is `0`, disable the confirm button and explain why.

---

## API fields

### `GET /api/v1/settings/capital`

New block:

```json
{
  "capital_amount": 100000,
  "profit_withdrawal": {
    "realized_profit": 20000,
    "total_withdrawn": 15000,
    "withdrawable_profit": 5000,
    "branch_id": null
  }
}
```

| Field | Meaning |
|-------|---------|
| `realized_profit` | All-time net profit (same formula as financial report `profit`) |
| `total_withdrawn` | Sum of previous owner cash outs (scoped to `branch_id` when set) |
| `withdrawable_profit` | `realized_profit − total_withdrawn` (floor 0) |
| `branch_id` | `null` = company-wide; UUID = branch-scoped profit |

Optional query (admin branch filter): `?branch_id={uuid}`

### `GET /api/v1/dashboard/summary`

Also exposes:

| Field | Meaning |
|-------|---------|
| `withdrawable_profit` | Same as above |
| `realized_profit` | All-time profit |
| `total_owner_cash_outs` | Sum withdrawn so far |
| `opening_cash_balance` | **Unchanged** by cash out (admin field) |
| `business_capital` | **Decreases** (cash component leaves drawer) |
| `cash_on_hand_realized` | **Decreases** by cash-out amount |

### `POST /api/v1/settings/capital/cash-out`

```json
{
  "amount": 15000,
  "branch_id": "optional-branch-uuid",
  "reason": "Owner draw",
  "notes": "June"
}
```

**Success:** `capital.opening_cash_balance` and `capital.capital_amount` are the **same** as before. `capital.business_capital` and `financing_snapshot.cash_on_hand_realized` are **lower**.

**422 example:**

```json
{
  "message": "Cash out amount exceeds withdrawable profit (5,000.00). Owner draws are deducted from profit margin, not business capital."
}
```

Or validation on `amount` field when using form request.

---

## Flutter UI checklist

- [ ] Load `GET /settings/capital` → read `profit_withdrawal.withdrawable_profit`
- [ ] Show **Withdrawable profit** prominently (not only capital)
- [ ] Validate: `0 < amount <= withdrawable_profit`
- [ ] After success: refresh capital **and** dashboard summary
- [ ] Do **not** show "capital reduced by X" — show "profit withdrawn: X"
- [ ] When admin branch dropdown is active, pass same `?branch_id=` on capital + cash-out calls

---

## Tests

```bash
php artisan test --filter=OwnerCashOutTest
```

---

## Related

- [flutter-owner-cash-out.md](./flutter-owner-cash-out.md) — **outdated** capital-deduction behaviour; follow **this doc** instead
- [flutter-admin-branch-filter.md](./flutter-admin-branch-filter.md) — branch dropdown + `?branch_id=`
