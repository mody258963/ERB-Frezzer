# Flutter — owner cash out (admin)

> **⚠️ Updated June 2026:** Cash out now deducts from **profit margin**, not `business_capital`.  
> **Follow [flutter-owner-cash-out-profit-validation.md](./flutter-owner-cash-out-profit-validation.md)** — this file is kept for API shape reference only.

When the **owner/admin takes money out** of the business (personal withdrawal, draw, etc.), record it with **cash out**. This **reduces withdrawable profit** (not capital) and keeps an audit trail.

**Roles:** `admin` only  
**Related:** [flutter-dashboard-transactions-guide.md](./flutter-dashboard-transactions-guide.md), [flutter-dev-updates-may-2026.md](./flutter-dev-updates-may-2026.md)

**Database:** `owner_cash_outs` + `capital_adjustments.type` in `2025_05_17_100010_create_company_settings_tables.php` (`migrate:fresh`).

---

## 1. What it does

| Action | Effect |
|--------|--------|
| `POST /settings/capital/cash-out` | Owner withdraws `amount` from recorded capital |
| `company_settings.capital_amount` | Decreases by `amount` |
| `owner_cash_outs` | New row (history) |
| `capital_adjustments` | Row with `type: owner_cash_out`, `change_amount` negative |
| Dashboard `business_capital` | Updates after refresh |

**Rule:** `amount` cannot exceed current `capital_amount` (422 if too high).

This is **not** paying a supplier — use `POST /installments/{id}/pay` for that.

---

## 2. API

### Record cash out

```http
POST /api/v1/settings/capital/cash-out
Authorization: Bearer <admin token>
Content-Type: application/json

{
  "amount": 15000,
  "reason": "Owner personal withdrawal",
  "notes": "June draw"
}
```

| Field | Required | Notes |
|-------|----------|--------|
| `amount` | Yes | Number > 0, ≤ current capital |
| `reason` | No | Shown in history |
| `notes` | No | Optional memo |

**Success `200`:**

```json
{
  "cash_out": {
    "id": "uuid",
    "amount": 15000,
    "reason": "Owner personal withdrawal",
    "notes": "June draw",
    "created_by": { "id": "uuid", "name": "Admin" },
    "created_at": "2026-06-05T12:00:00+00:00"
  },
  "capital": {
    "capital_amount": 85000,
    "currency": "EGP",
    "financing_snapshot": {
      "inventory_at_cost": 1000,
      "customer_receivables": 0,
      "supplier_debt": 0,
      "deployed_capital": 1000,
      "estimated_available": 84000
    }
  }
}
```

### List cash outs

```http
GET /api/v1/settings/capital/cash-outs?per_page=25
```

Paginated `data[]` with same shape as `cash_out` above.

### Read current capital (before form)

```http
GET /api/v1/settings/capital
```

Use `capital_amount` as max for the amount field.

### Capital adjustment history (includes cash outs)

```http
GET /api/v1/settings/capital/adjustments
```

Cash outs appear with `type: "owner_cash_out"` and negative `change_amount`.

---

## 3. Flutter UI (admin)

```
┌─────────────────────────────────────────┐
│  Owner cash out                         │
├─────────────────────────────────────────┤
│  Business capital:  100,000.00 EGP      │
│  Est. available:   99,000.00 EGP      │
├─────────────────────────────────────────┤
│  Amount *     [ 15000.00___________ ]   │
│  Reason       [ Personal draw_____ ]    │
│  Notes        [ optional_________ ]     │
├─────────────────────────────────────────┤
│  [ Cancel ]              [ Confirm ]    │
└─────────────────────────────────────────┘
```

```dart
Future<void> recordOwnerCashOut({
  required double amount,
  String? reason,
  String? notes,
}) async {
  final res = await dio.post('/settings/capital/cash-out', data: {
    'amount': amount,
    if (reason != null && reason.isNotEmpty) 'reason': reason,
    if (notes != null && notes.isNotEmpty) 'notes': notes,
  });

  final capital = res.data['capital'] as Map<String, dynamic>;
  // update local capital state
  await dashboardRepo.refreshSummary();
}
```

Validate client-side: `0 < amount <= capitalAmount`.

After success, refresh:

- `GET /settings/capital`
- `GET /dashboard/summary` → `business_capital`, `capital_estimated_available`
- Optional: `GET /dashboard/activity` → `owner.cash_out` event

---

## 4. Errors

| HTTP | Cause |
|------|--------|
| `403` | Not admin |
| `422` | Amount > capital or validation failed |

Example:

```json
{
  "message": "Cash out amount exceeds business capital (100000.00)."
}
```

---

## 5. Dashboard & activity

| Field | After 15k cash out from 100k capital |
|-------|--------------------------------------|
| `business_capital` | 85,000 |
| `capital_estimated_available` | Recalculated from snapshot |

Activity feed (`GET /dashboard/activity`): action `owner.cash_out`.

---

## 6. Checklist

- [ ] Screen visible only for `user.role == admin`
- [ ] Load `GET /settings/capital` for max amount
- [ ] `POST /settings/capital/cash-out` on confirm
- [ ] Refresh dashboard summary
- [ ] Optional history screen: `GET /settings/capital/cash-outs`
- [ ] Handle 422 over-capital message

---

## 7. Tests

```bash
php artisan test --filter=OwnerCashOutTest
```
