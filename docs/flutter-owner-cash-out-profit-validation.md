# ⚠️ BREAKING — Owner cash out now uses profit margin (not capital)

**Audience:** Flutter (`erd_rezzer`) developers  
**Date:** June 2026  
**Arabic (client request):** السحب النقدي يُخصم من **هامش الربح** وليس من **رأس المال**

---

## What changed

| Before (old) | After (new) |
|--------------|-------------|
| Cash out reduced `business_capital` | **`business_capital` stays unchanged** |
| Max amount = `capital_amount` | Max amount = **`withdrawable_profit`** |
| Validated against capital | Validated against **realized profit − prior cash outs** |

---

## ⚠️ Developer warnings

1. **Do not** use `capital_amount` as the max for the cash-out form anymore.
2. **Do not** expect `business_capital` to drop after cash out — refresh `profit_withdrawal` / `withdrawable_profit` instead.
3. **Do not** subtract cash outs from capital in local UI math.
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
| `business_capital` | **Unchanged** by cash out |

### `POST /api/v1/settings/capital/cash-out`

```json
{
  "amount": 15000,
  "branch_id": "optional-branch-uuid",
  "reason": "Owner draw",
  "notes": "June"
}
```

**Success:** `capital.capital_amount` is the **same** as before the withdrawal.

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
