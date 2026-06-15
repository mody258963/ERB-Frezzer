# Flutter — admin transaction edits (June 2026)

**Audience:** Flutter Windows POS/ERP (`erd_rezzer`)  
**Backend:** ERB-Frezzer API v1

Admin users can **correct mistakes before stock moves** (transfers) or **fix the latest customer payment amount** after collection.

**Related:** [flutter-june-2026-windows-updates.md](./flutter-june-2026-windows-updates.md), [flutter-decimal-quantities-and-transfers.md](./flutter-decimal-quantities-and-transfers.md), [flutter-per-branch-isolation.md](./flutter-per-branch-isolation.md)

---

## 1. Who can edit what

| Transaction | Edit allowed? | Role | When |
|-------------|---------------|------|------|
| **Stock transfer** | Yes | **Admin only** | Status = `pending` (before complete) |
| **Stock transfer** | No | — | After `complete` or `cancel` |
| **Customer payment** | Yes | **Admin only** | **Most recent** payment for that customer |
| **Customer payment** | No | — | Older payments (only latest editable) |
| **Invoice / purchase / return** | No edit endpoint | — | Use cancel + recreate where supported |

**App logic:**

```
showTransferEdit = user.role == 'admin' && transfer.status == 'pending'
showPaymentEdit  = user.role == 'admin' && payment.id == latestPaymentIdForCustomer
```

---

## 2. Edit pending transfer

**Scenario:** User created a transfer with 2 regulators but meant 1. Stock has **not** moved yet.

### Request

```http
PATCH /api/v1/transfers/{transfer_id}
Authorization: Bearer {admin_token}
Content-Type: application/json
```

```json
{
  "notes": "Reduced to one regulator",
  "items": [
    { "part_id": "uuid", "quantity": 1, "unit_cost": 150.0 }
  ]
}
```

`PUT /api/v1/transfers/{transfer_id}` — same body.

| Field | Required | Notes |
|-------|----------|--------|
| `items` | No* | **Full replacement** when sent — not a delta per line |
| `items[].part_id` | Yes (with items) | |
| `items[].quantity` | Yes (with items) | Decimals OK for `m` / `kg` / `l` |
| `items[].unit_cost` | No | Optional line valuation at complete |
| `notes` | No | Memo only |

\* Omit `items` to update `notes` only.

### Response (200)

Same shape as `GET /transfers/{id}`:

```json
{
  "id": "uuid",
  "from_branch_id": "uuid",
  "to_branch_id": "uuid",
  "status": "pending",
  "notes": "Reduced to one regulator",
  "items": [
    {
      "id": "line-uuid",
      "part_id": "uuid",
      "quantity": 1,
      "unit_cost": 150,
      "part": {
        "code": "REG-001",
        "name": "Regulator",
        "unit": "pc",
        "unit_label": "Piece"
      }
    }
  ]
}
```

### Rules

- Transfer must stay **`pending`**. Completed → **422** `Only pending transfers can be edited.`
- **`items` replaces all lines** — include every part that should remain on the transfer.
- **Branches cannot change** after create — cancel and create a new transfer if wrong branch.
- After save, user still taps **Complete** → `PATCH /transfers/{id}/complete`.

### Screen flow

1. Transfers list → open detail.
2. If `pending` + admin → show **Edit**.
3. Edit screen: lines table (part, qty, optional unit cost), notes field.
4. **Save** → `PATCH /transfers/{id}` with full `items` array.
5. Back to detail → **Complete** when ready.

---

## 3. Edit latest customer payment

**Scenario:** Admin recorded payment **100** EGP but customer paid **80**.

### Request

```http
PATCH /api/v1/customers/{customer_id}/payments/{payment_id}
Authorization: Bearer {admin_token}
```

```json
{
  "amount": 80,
  "payment_method": "cash",
  "notes": "Corrected from 100 to 80"
}
```

| Field | Required | Notes |
|-------|----------|--------|
| `amount` | No | New total; must be > 0 and ≤ balance after reversal |
| `payment_method` | No | `cash`, `bank_transfer`, `check` |
| `notes` | No | |

### Response (200)

```json
{
  "id": "payment-uuid",
  "customer_id": "uuid",
  "amount": 80,
  "payment_method": "cash",
  "notes": "Corrected from 100 to 80",
  "created_at": "2026-06-13T12:00:00.000000Z"
}
```

### Rules

- **Credit customers only** (same as collect).
- **Only the latest payment** for that customer — older rows → **422** `Only the most recent payment can be edited.`
- Server reverses old amount on invoices + balance, then applies new amount (FIFO on unpaid credit invoices).
- Example: balance was 500, paid 100 → balance 400. Edit to 80 → balance **420**.

### Screen flow

1. Customer → **Payments** tab/history.
2. Show **Edit** icon on **first row only** (most recent) if admin.
3. Dialog/screen: amount, method, notes.
4. **Save** → `PATCH .../payments/{id}`.
5. Refresh `GET /customers/{id}/balance` and unpaid invoices.

---

## 4. Errors (422)

| Message | UI action |
|---------|-----------|
| `Only pending transfers can be edited.` | Hide edit; show read-only completed transfer |
| `Only the most recent payment can be edited.` | Disable edit on older payment rows |
| `Payment amount exceeds customer balance (...)` | Show max allowed amount |
| `items.*.quantity` validation | Fractional qty on piece unit — fix input |

---

## 5. Flutter checklist

### Transfers
- [ ] `Edit` visible when `status == pending` && `role == admin`
- [ ] Load current lines into edit form
- [ ] Save sends **complete** `items` array (all lines)
- [ ] Hide edit after complete/cancel
- [ ] Do not show branch change on edit screen

### Customer payments
- [ ] `Edit` on latest payment row only (admin)
- [ ] PATCH amount / method / notes
- [ ] Refresh balance + invoice list after save

### Permissions
- [ ] Manager / salesperson / warehouse — no edit buttons

---

## 6. Endpoints

| Action | Method | Path | Role |
|--------|--------|------|------|
| Edit pending transfer | `PATCH` or `PUT` | `/transfers/{id}` | admin |
| Edit latest payment | `PATCH` or `PUT` | `/customers/{id}/payments/{paymentId}` | admin |
| Complete transfer (unchanged) | `PATCH` | `/transfers/{id}/complete` | admin, manager, warehouse |
| Collect payment (unchanged) | `POST` | `/customers/{id}/payments` | admin, manager |
