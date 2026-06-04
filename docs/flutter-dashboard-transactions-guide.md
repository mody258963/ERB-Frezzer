# Flutter — all transactions & dashboard fields

Guide for **erd_rezzer** / FrostParts Windows app developers.  
API base: `https://api.tppower.shop/api/v1` (or your server).

**Related:** [flutter-dev-updates-may-2026.md](./flutter-dev-updates-may-2026.md), [flutter-mobile-inventory-intake.md](./flutter-mobile-inventory-intake.md) (mobile intake + admin dashboard tab), [flutter-app-fixes.md](./flutter-app-fixes.md), [customer-returns-ar.md](./customer-returns-ar.md)

---

## 1. Rule: refresh dashboard after every money movement

Call **`GET /dashboard/summary`** again after any action that changes money, stock, or debt.

| Action | Refresh summary? |
|--------|------------------|
| Set business capital | Yes |
| Create / receive purchase | Yes |
| Pay supplier installment | Yes |
| Create invoice (cash or credit) | Yes |
| Saturday settlement (collect credit) | Yes |
| Approve / reject customer return | Yes |

Optional detail screens:

- `GET /dashboard/payables` — supplier installments due / overdue  
- `GET /dashboard/receivables` — credit customers with balance  
- `GET /dashboard/sales` — weekly breakdown + `totals`  
- `GET /reports/financial?from=&to=` — full P&amp;L for a date range  

---

## 2. Dashboard summary — every field

`GET /api/v1/dashboard/summary`  
Optional: `?branch_id={uuid}` (admin only).

### Stock & financing

| JSON field | Meaning | Goes up when | Goes down when |
|------------|---------|--------------|----------------|
| `total_stock_value_cost` | Inventory at **cost** | Receive purchase, return restock | Sale, supplier return out |
| `business_capital` | Owner capital (Settings) | Admin sets capital | Admin lowers capital |
| `capital_currency` | e.g. `EGP` | — | — |
| `capital_estimated_available` | Rough cash left (see Settings API) | — | Purchases / receivables |

### Customers (money they owe you)

| JSON field | Meaning | Goes up when | Goes down when |
|------------|---------|--------------|----------------|
| `total_receivables` | Sum of credit customers’ `outstanding_balance` | Credit invoice | Settlement, return (credit note / refund) |

### Suppliers (money you owe them)

| JSON field | Meaning | Goes up when | Goes down when |
|------------|---------|--------------|----------------|
| `total_supplier_debt` | Total supplier balance | Create purchase PO | Pay installment, cancel unpaid PO |
| `weekly_purchases_ordered` | PO **created** this week | `POST /purchases` | — |
| `weekly_purchases_received` | PO **received** this week | `PATCH /purchases/{id}/receive` | — |
| `weekly_supplier_payments` | Installments **paid** this week | `POST /installments/{id}/pay` | — |
| `unpaid_installments_total` | Sum of unpaid installment amounts | Create PO (installments) | Pay installment |
| `unpaid_installments_count` | Count of unpaid installments | Create PO | Pay installment |
| `overdue_installments_total` | Unpaid past `due_date` | — | Pay overdue installment |

### Sales & profit (this week)

| JSON field | Meaning |
|------------|---------|
| `weekly_revenue` | Invoice **subtotal** (before discount) |
| `weekly_discount` | Sum of invoice discounts |
| `weekly_customer_refunds` | Approved customer refunds (cash / writeoff / credit note) |
| `weekly_net_sales` | Invoice totals − refunds |
| `weekly_gross_profit` | Σ `(sell − cost) × qty` on invoice lines |
| `weekly_profit` | Gross profit − discount − refunds |

**Do not** show only `weekly_revenue` as “profit”.

---

## 3. Transaction recipes (API order)

### A. Business capital (Settings — admin)

```http
PUT /settings/capital
{ "capital_amount": 500000, "reason": "Opening capital", "notes": "EGP" }
```

Read: `GET /settings/capital` (includes `financing_snapshot`).

---

### B. Supplier purchase with installments (e.g. 100,000 EGP × 4)

**1. Create PO**

```http
POST /purchases
{
  "supplier_id": "...",
  "branch_id": "...",
  "payment_type": "installments",
  "installment_count": 4,
  "installment_start_date": "2026-05-01",
  "items": [{ "part_id": "...", "quantity": 10, "unit_cost": 10000 }]
}
```

**Dashboard after create:**

- `total_supplier_debt` → 100000  
- `weekly_purchases_ordered` → 100000  
- `unpaid_installments_total` → 100000  
- `unpaid_installments_count` → 4  

**2. Receive goods** (stock in)

```http
PATCH /purchases/{id}/receive
{ "branch_id": "..." }
```

**Dashboard after receive:**

- `weekly_purchases_received` → PO total  
- `total_stock_value_cost` → increases  

**3. Pay one installment** (25,000 full or partial)

```http
POST /installments/{id}/pay
{ "payment_method": "cash" }
```

Pay a **custom amount** (partial):

```http
POST /installments/{id}/pay
{ "payment_method": "cash", "amount": 10000 }
```

Omit `amount` to pay the full remaining `balance_due`. See [flutter-supplier-installment-partial-pay.md](./flutter-supplier-installment-partial-pay.md).

Only if `balance_due > 0` (installment not fully paid).

**Dashboard after pay:**

- `weekly_supplier_payments` → +25000  
- `total_supplier_debt` → 75000  
- `unpaid_installments_total` → 75000  

List due dates: `GET /dashboard/payables` or `GET /installments?is_paid=false`.

---

### C. Cash customer sale

```http
POST /invoices
{
  "customer_id": "...",
  "branch_id": "...",
  "payment_type": "cash",
  "discount": 0,
  "items": [{ "part_id": "...", "quantity": 2, "unit_price": 200 }]
}
```

**Dashboard:** `weekly_revenue`, `weekly_profit`, `weekly_net_sales` increase.

---

### D. Credit customer (they owe you later)

Customer must have `type: "credit"` and enough `credit_limit`.

```http
POST /invoices
{
  "customer_id": "...",
  "branch_id": "...",
  "payment_type": "credit",
  "items": [{ "part_id": "...", "quantity": 1 }]
}
```

**Dashboard:**

- `weekly_revenue` / profit — same as cash  
- `total_receivables` — increases by invoice `total`  

---

### E. Collect credit (Saturday settlement)

```http
POST /settlements
{
  "customer_id": "...",
  "settlement_date": "2026-05-10",
  "payment_method": "cash"
}
```

Settles unpaid credit invoices for that customer.

**Dashboard:** `total_receivables` → 0 (for that customer if fully settled).

---

### F. Customer return

**Critical:** `reference_id` = **invoice UUID**, not customer id.

```http
POST /returns
{
  "return_type": "customer_return",
  "reference_id": "<invoice.id>",
  "reference_type": "invoice",
  "customer_id": "...",
  "branch_id": "...",
  "items": [{
    "part_id": "...",
    "quantity": 1,
    "unit_price": 200,
    "condition": "sellable"
  }]
}
```

Approve:

```http
PATCH /returns/{id}/approve
{ "resolution": "refund_cash" }
```

Resolutions: `refund_cash`, `credit_note`, `writeoff`, `restock`, `replace`.

**Dashboard after approve:**

- `weekly_customer_refunds` increases  
- `weekly_net_sales` and `weekly_profit` decrease  
- Stock may increase if sellable + restock-type resolution  

**UI:** Hide “Return” when `invoice.return_status == 'returned'`.

---

## 4. Verified scenario (automated test)

The backend test `DashboardFullBusinessFlowTest` runs this sequence and checks 57 dashboard assertions:

| Step | Action | Example dashboard check |
|------|--------|-------------------------|
| 1 | Capital 500,000 | `business_capital` = 500000 |
| 2 | PO 100k, 4 installments | `total_supplier_debt` = 100000 |
| 3 | Receive PO | `weekly_purchases_received` = 100000 |
| 4 | Pay 25k installment | `weekly_supplier_payments` = 25000, debt = 75000 |
| 5 | Cash sale 400 | `weekly_revenue` = 400, `weekly_profit` = 200 |
| 6 | Credit sale 200 | `total_receivables` = 200 |
| 7 | Settlement | `total_receivables` = 0 |
| 8 | Return refund 200 | `weekly_customer_refunds` = 200, `weekly_profit` = 100 |

Run locally: `php artisan test tests/Feature/DashboardFullBusinessFlowTest.php`

---

## 5. Flutter UI checklist

### Dashboard screen

- [ ] Cards for **supplier debt**, **receivables**, **capital**, **weekly profit**  
- [ ] Row for **weekly supplier payments** and **purchases ordered**  
- [ ] Row for **unpaid installments** (total + count)  
- [ ] Pull-to-refresh → `GET /dashboard/summary`  

### Purchases

- [ ] After create PO → refresh dashboard  
- [ ] After receive → refresh dashboard  
- [ ] Installments list: hide Pay if `is_paid`  

### Sales / POS

- [ ] Cash vs credit from customer `type`  
- [ ] After invoice → refresh dashboard  

### Credit

- [ ] Settlement screen → `POST /settlements` → refresh dashboard  
- [ ] Receivables widget → `GET /dashboard/receivables`  

### Returns

- [ ] `reference_id` = invoice id  
- [ ] After approve → refresh dashboard  
- [ ] Respect `return_status` on invoice list  

### Settings (admin)

- [ ] Business capital form → `PUT /settings/capital`  
- [ ] Users / branches per [flutter-dev-updates-may-2026.md](./flutter-dev-updates-may-2026.md)  

### Reports

- [ ] Use `GET /reports/financial` for period report (sales + refunds + profit + suppliers + capital)  
- [ ] Use `GET /reports/sales` only for invoice list drill-down  

---

## 6. Arabic quick reference

| الحقل | المعنى |
|-------|--------|
| `total_receivables` | مستحقات العملاء (آجل) |
| `total_supplier_debt` | ديون الموردين |
| `weekly_supplier_payments` | مدفوعات أقساط الموردين هذا الأسبوع |
| `weekly_purchases_ordered` | مشتريات مسجلة هذا الأسبوع |
| `weekly_customer_refunds` | مرتجعات عملاء |
| `weekly_profit` | ربح الأسبوع بعد الخصم والمرتجعات |
| `business_capital` | رأس المال من الإعدادات |

---

## 7. Deploy / cache

After API deploy:

```bash
php artisan migrate
php artisan cache:clear
```

If dashboard looks stale for up to 5 minutes, force refresh in the app or clear server cache.
