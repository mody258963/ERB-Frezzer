# Flutter — branch transaction edits (reverse transfer + branch finance)

**Audience:** `erd_rezzer` Flutter team  
**Backend:** ERB-Frezzer `/api/v1`  
**Languages:** Arabic summary + English API reference

---

## ملخص (Arabic)

| الإجراء | من يستطيع | متى | تأثير لوحة التحكم الرئيسية |
|---------|-----------|-----|---------------------------|
| **عكس تحويل بضائع** (`PATCH /transfers/{id}/reverse`) | admin فقط | بعد `completed` فقط | يعيد المخزون؛ **لا يغيّر** النقد المحقق |
| **تعديل قيد مالي بين الفروع** | admin | شحنة `open` أو دفعة | دفعة → يحدّث صناديق النقد للفرعين؛ شحنة → لا |
| **إلغاء (void) قيد مالي** | admin | أي قيد غير مرتبط بتحويل | دفعة → يعكس حركة النقد؛ شحنة → أرصدة الفروع فقط |

> **مهم:** مدفوعات الفروع (`/branch-finance/payments`) تسجّل ديناً بين الفروع **وتحرّك صندوق النقد** لكل فرع (خصم من الفرع الدافع `debtor_branch_id`، إضافة للفرع المستلم `creditor_branch_id`). لا تُحسب ضمن `must_collect_customers` / `must_pay_suppliers`. راجع [flutter-dashboard-real-cash-boxes.md](./flutter-dashboard-real-cash-boxes.md).

---

## Reverse completed goods transfer

| Method | Path | Role | Body |
|--------|------|------|------|
| `PATCH` | `/transfers/{id}/reverse` | **admin** | _(empty)_ |

**Preconditions**

- Transfer `status` must be `completed`.
- Destination branch must still hold enough quantity for every line (fails with **422** if goods were sold/adjusted away).

**Response**

- Transfer `status` → `reversed`.
- Stock moves back to source branch (same unit cost as original complete).
- Linked inter-branch **charge** is soft-voided automatically.

**UI**

- Show **Reverse** only for admin on completed transfers.
- On success: refresh inventory for **both** branches + transfer list.
- On 422: show server `message` (includes part code and quantities).

**Dashboard refresh**

| Endpoint | Refresh? |
|----------|----------|
| `GET /dashboard/summary` | Yes — `total_stock_value_cost` |
| `GET /dashboard/inventory` | Yes — per branch |
| `GET /branch-finance/balances` | Yes — charge voided |
| `GET /dashboard/summary` cash fields | **No change** |

---

## Edit / void branch finance entries

| Method | Path | Role | Body |
|--------|------|------|------|
| `PATCH` / `PUT` | `/branch-finance/entries/{id}` | **admin** | `amount?`, `description?`, `notes?` |
| `DELETE` | `/branch-finance/entries/{id}` | **admin** | _(soft void)_ |

**Rules**

| Entry type | Edit | Void (`DELETE`) |
|------------|------|-----------------|
| Open **charge** | Change `amount`, `description`, `notes` | Allowed — balance drops |
| Settled **charge** | **422** — void payment or reverse transfer first | Allowed if not transfer-linked |
| **Payment** | Change `amount` → FIFO re-applied on branch pair | Reopens affected charges |
| Transfer-linked **charge** | Same as charge | **422** — use transfer reverse |

**Response fields (new)**

- `voided_at`, `voided_by` on voided entries.

**Dashboard refresh**

| Endpoint | When |
|----------|------|
| `GET /branch-finance/balances` | After edit/void/payment |
| `GET /branch-finance/entries` | List/detail |
| `GET /dashboard/summary` or `GET /dashboard/cash` | After **payment** edit/void (per-branch cash boxes) |

---

## What does NOT change org-wide dashboard cash

These actions do **not** change **org-wide** `cash_on_hand_realized` (no `branch_id`):

- `PATCH /transfers/{id}/complete`
- `PATCH /transfers/{id}/reverse`
- `POST /branch-finance/charges`
- `PATCH|DELETE /branch-finance/entries/{id}` when entry is a **charge** (not a payment)

**Inter-branch payments** (`POST /branch-finance/payments`, payment edit/void) move cash **between branches** — org total unchanged, but **per-branch** `GET /dashboard/summary?branch_id=` and `GET /dashboard/cash?branch_id=` must refresh.

Realized cash also includes: cash sales, customer payments, supplier installment payments, refunds, owner cash-out, and inter-branch payments (per branch).

See also: [flutter-dashboard-real-cash-boxes.md](./flutter-dashboard-real-cash-boxes.md).

---

## Suggested app flows

### Reverse transfer

1. Open completed transfer detail (admin).
2. Confirm dialog: stock returns to source; inter-branch charge voided.
3. `PATCH /transfers/{id}/reverse`.
4. Refresh transfer status, both branch stock screens, branch finance balances.

### Edit branch payment

1. Admin opens branch finance entry.
2. `PATCH /branch-finance/entries/{id}` with new `amount`.
3. Refresh `GET /branch-finance/balances` for the creditor/debtor pair.

---

## QA checklist

- [ ] Complete transfer A→B → reverse → quantities restored on A and B
- [ ] Reverse fails (422) if B sold transferred stock
- [ ] Non-admin cannot reverse or edit branch finance entries
- [ ] Edit payment amount → open charges / balance_owed recalculated
- [ ] Void manual charge → disappears from balances matrix
- [ ] Void transfer charge directly → 422; reverse transfer instead
- [ ] After branch payment, `cash_on_hand_realized` unchanged on dashboard

---

## Related docs

- [flutter-admin-transaction-edits.md](./flutter-admin-transaction-edits.md) — pending transfer edit
- [flutter-decimal-quantities-and-transfers.md](./flutter-decimal-quantities-and-transfers.md) — transfer lines + `unit_cost`
- [flutter-dashboard-real-cash-boxes.md](./flutter-dashboard-real-cash-boxes.md) — realized cash KPIs
