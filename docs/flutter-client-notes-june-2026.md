# Flutter Windows — client notes (June 2026)

Updates from the shop owner covering **customer partial payments**, **return profit accounting**, and **linked customer/supplier net balance (contra settlement)**. Backend is done; this doc is for **Flutter Windows (`erd_rezzer`)** UI changes.

**Base URL:** `https://api.tppower.shop/api/v1`  
**Fresh DB:** `php artisan migrate:fresh --seed` (schema includes `invoices.amount_paid`, `customer_payments`, `customers.linked_supplier_id`, `contra_settlements`).

---

## Summary (Arabic — what the client asked)

| # | Request | Backend | Flutter action |
|---|---------|---------|----------------|
| 1 | **دفعات متغيرة للعملاء** — collect any amount from credit customers, like supplier installments | ✅ `POST /customers/{id}/payments` | Add “collect payment” screen (mirror supplier partial pay) |
| 2 | **المرتجعات** — deduct return from net sales **once**, and from profit **only the margin** (not full sale price) | ✅ Fixed in `FinancialMetricsService` | Stop subtracting refunds from profit in the app; use API fields only |
| 3 | **عميل ومورد معاً** — same person (e.g. Abu) buys from you **and** you buy from him; show **net** who owes whom and offset balances | ✅ Link + net balance + offset API | Link UI + net balance card + “offset / مقاصة” action |

**Bug the client reported:** Returning a 75 EGP item was reducing profit by **75** (full price) **and** net sales by **75** → looked like **150** total impact. Correct behavior: net sales −75, profit −25 (if cost was 50).

---

## Part A — Customer partial payments

Same UX pattern as [flutter-supplier-installment-partial-pay.md](./flutter-supplier-installment-partial-pay.md), but for **credit customers**.

### When to show

- Customer `type === 'credit'`
- `outstanding_balance > 0`
- Roles: **admin**, **manager**

Saturday settlement (`POST /settlements`) still pays **all** open credit in one shot. Partial pay is for **any amount, any time**.

### API

#### Collect payment

```http
POST /api/v1/customers/{customerId}/payments
Authorization: Bearer <token>
Content-Type: application/json

{
  "payment_method": "cash",
  "amount": 300,
  "notes": "Partial collection"
}
```

| Field | Required | Notes |
|-------|----------|--------|
| `payment_method` | Yes | `cash`, `bank_transfer`, `check` |
| `amount` | No | Omit or null = pay **full** `outstanding_balance` |
| `notes` | No | Optional memo |

**Success `201`:**

```json
{
  "id": "uuid",
  "customer_id": "uuid",
  "amount": 300,
  "payment_method": "cash",
  "notes": "Partial collection",
  "created_by": { "id": "uuid", "name": "Admin" },
  "created_at": "2026-06-04T12:00:00+00:00"
}
```

**Errors:** `422` if amount > balance, amount ≤ 0, or customer is not `credit`.

#### Payment history

```http
GET /api/v1/customers/{customerId}/payments?per_page=25
```

Paginated list of `CustomerPayment` objects (same shape as POST response).

#### Balance + unpaid invoices

```http
GET /api/v1/customers/{customerId}/balance
```

Each unpaid credit invoice now includes:

| Field | Meaning |
|-------|---------|
| `total` | Invoice total |
| `amount_paid` | Sum collected so far (partial or settlement) |
| `balance_due` | `total - amount_paid` |
| `is_paid` | `true` when fully paid |

### Flutter UI checklist

- [ ] On credit customer detail / balance screen: show `outstanding_balance` and list unpaid invoices with `amount_paid` + `balance_due`
- [ ] **Collect payment** button → dialog: amount (default = full balance), payment method, notes
- [ ] After success: refresh balance + dashboard summary
- [ ] Optional: **Payment history** tab via `GET .../payments`
- [ ] Do **not** only rely on Saturday settlement for collections

### Example flow

Customer owes **1,000 EGP** on one invoice:

1. Pay **300** → `outstanding_balance` = 700, invoice `amount_paid` = 300, `is_paid` = false  
2. Pay **700** → balance 0, invoice `is_paid` = true  

Payments apply to **oldest unpaid credit invoices first** (FIFO).

---

## Part B — Returns and dashboard profit (fix double-count)

### What changed on the API

Returns still increase `weekly_customer_refunds` by the **refund amount** (sale price).

**Net sales** (unchanged formula):

```
weekly_net_sales = weekly_revenue - weekly_customer_refunds
```

**Net profit** (fixed):

```
weekly_profit = weekly_gross_profit - weekly_discount - weekly_customer_refund_profit_impact
```

Where `weekly_customer_refund_profit_impact` = sum of **margin lost** on returned lines:

```
(unit_price - cost_price) × quantity_returned
```

For a 75 EGP sale with 50 EGP cost, full return:

| Field | Value |
|-------|------:|
| `weekly_revenue` | 75 |
| `weekly_customer_refunds` | 75 |
| `weekly_net_sales` | **0** |
| `weekly_gross_profit` | 25 |
| `weekly_customer_refund_profit_impact` | 25 |
| `weekly_profit` | **0** |

### Where these fields appear

**Dashboard summary** — `GET /api/v1/dashboard/summary`:

- `weekly_revenue`
- `weekly_customer_refunds`
- `weekly_net_sales`
- `weekly_gross_profit`
- `weekly_customer_refund_profit_impact` *(new)*
- `weekly_profit`

**Financial report** — `GET /api/v1/reports/financial?from=&to=` → `totals`:

- `customer_refunds`
- `customer_refund_profit_impact` *(new)*
- `net_sales`
- `gross_profit`
- `profit`

### Flutter — what to change

**Do not** compute profit locally as:

```dart
// WRONG — double-counts refunds
profit = weeklyGrossProfit - weeklyDiscount - weeklyCustomerRefunds;
```

**Do** display `weekly_profit` from the API as **net profit after returns**.

Suggested dashboard cards:

| Card label (AR) | API field |
|-----------------|-----------|
| إجمالي المبيعات | `weekly_revenue` |
| مرتجعات عملاء | `weekly_customer_refunds` |
| صافي المبيعات | `weekly_net_sales` |
| صافي الربح | `weekly_profit` |

Optional detail row: `weekly_customer_refund_profit_impact` = “خصم هامش المرتجعات”.

Partial returns + reprint: see [flutter-invoice-partial-return-reprint.md](./flutter-invoice-partial-return-reprint.md).

---

## Part D — Linked customer + supplier (net balance / contra settlement)

When **the same person** is both a **credit customer** (buys from you) and a **supplier** (you buy from them), link the two records and use **net balance** + **offset** instead of treating them as unrelated debts.

**Arabic:** *مورد وعميل في نفس الوقت — مقاصة الرصيد*

### Business example (Abu)

| Side | Amount |
|------|-------:|
| Abu bought from you (credit) | You are owed **500** |
| You bought from Abu (purchase on credit) | You owe **300** |
| **Net** | Abu owes you **200** |
| **Offset 300** | Clears your payable to him; his balance becomes **200** |

### 1. Link customer ↔ supplier

Set when creating or editing a **customer**:

```http
PUT /api/v1/customers/{customerId}
Authorization: Bearer <token>
Content-Type: application/json

{
  "linked_supplier_id": "{supplierUuid}"
}
```

| Rule | Detail |
|------|--------|
| One link | Each supplier can link to **at most one** customer |
| Clear link | `"linked_supplier_id": null` |
| Customer JSON | `GET /customers/{id}` returns `linked_supplier_id` + nested `linked_supplier` |
| Supplier JSON | `GET /suppliers/{id}` returns `linked_customer_id` when linked |

**Workflow for Abu:**

1. Create supplier **Abu**  
2. Create credit customer **Abu**  
3. Edit customer → set `linked_supplier_id` to supplier id  

Sales still use **invoices** (customer). Purchases still use **purchase orders** (supplier). The link only affects **balance view** and **settlement**.

### 2. Net balance (read)

From customer:

```http
GET /api/v1/customers/{customerId}/linked-balance
```

From supplier (same data when linked):

```http
GET /api/v1/suppliers/{supplierId}/linked-balance
```

**Response:**

```json
{
  "is_linked": true,
  "customer": {
    "id": "uuid",
    "name": "Abu Customer",
    "type": "credit",
    "outstanding_balance": 500
  },
  "supplier": {
    "id": "uuid",
    "name": "Abu Supplier",
    "total_debt": 300
  },
  "customer_balance": 500,
  "supplier_debt": 300,
  "net_amount": 200,
  "net_direction": "they_owe_us",
  "max_offset_amount": 300
}
```

| Field | Meaning |
|-------|---------|
| `customer_balance` | They owe you (receivable) |
| `supplier_debt` | You owe them (payable) |
| `net_amount` | Absolute difference after netting |
| `net_direction` | `they_owe_us` \| `we_owe_them` \| `balanced` |
| `max_offset_amount` | `min(customer_balance, supplier_debt)` — max you can offset in one action |
| `is_linked` | `false` if no `linked_supplier_id` |

**UI labels (AR):**

| `net_direction` | Show |
|-----------------|------|
| `they_owe_us` | **لنا** {net_amount} (they owe us) |
| `we_owe_them` | **علينا** {net_amount} (we owe them) |
| `balanced` | **متساوي** (balanced) |

When `is_linked === false`, show customer balance only and a **Link supplier** action.

### 3. Offset / contra settlement (write)

Admin/manager only. Reduces **both** balances by the same amount (no cash movement).

```http
POST /api/v1/customers/{customerId}/offset-supplier
Authorization: Bearer <token>
Content-Type: application/json

{
  "amount": 300,
  "notes": "مقاصة مع المورد"
}
```

| Field | Required | Notes |
|-------|----------|--------|
| `amount` | No | Default = full `max_offset_amount` |
| `notes` | No | Optional memo |

**Success `201`:**

```json
{
  "id": "uuid",
  "customer_id": "uuid",
  "supplier_id": "uuid",
  "amount": 300,
  "notes": "مقاصة مع المورد",
  "created_by": { "id": "uuid", "name": "Admin" },
  "created_at": "2026-06-04T15:00:00+00:00"
}
```

**Errors:** `422` if not linked, amount > `max_offset_amount`, or both balances zero.

**History:**

```http
GET /api/v1/customers/{customerId}/contra-settlements?per_page=25
```

### Flutter UI checklist

- [ ] Customer edit screen: **Link to supplier** dropdown (list suppliers; show `linked_supplier` on detail)
- [ ] Supplier detail: show `linked_customer_id` + shortcut to customer if linked
- [ ] When linked: **Net balance card** on customer **and** supplier screens (`GET .../linked-balance`)
- [ ] Button **مقاصة / Offset** when `max_offset_amount > 0` → amount (default max), notes → `POST .../offset-supplier`
- [ ] After offset: refresh linked balance, customer balance, supplier debt, dashboard summary
- [ ] Optional: contra history tab (`GET .../contra-settlements`)
- [ ] Remaining net balance: collect with **Part A** partial payment or pay supplier installment as usual

### Accounting notes

- Offset uses internal payment method `offset` — **not** counted in `weekly_supplier_payments` (no cash out).
- Dashboard `total_receivables` / `total_supplier_debt` stay **gross** per party; net is on the linked-balance screen only.

---

## Part E — Related docs (already shipped)

| Topic | Doc |
|-------|-----|
| Supplier partial pay (UI reference) | [flutter-supplier-installment-partial-pay.md](./flutter-supplier-installment-partial-pay.md) |
| Owner cash out | [flutter-owner-cash-out.md](./flutter-owner-cash-out.md) |
| Dashboard fields | [flutter-dashboard-transactions-guide.md](./flutter-dashboard-transactions-guide.md) |
| Returns workflow (AR) | [customer-returns-ar.md](./customer-returns-ar.md) |

---

## Deploy note

After backend deploy, Flutter should work without app-side accounting changes **except**:

1. New customer collect-payment UI  
2. Remove any client-side `profit - refunds` logic  
3. Show `amount_paid` / `balance_due` on unpaid customer invoices  
4. Link customer ↔ supplier + net balance card + offset (Part D)  

No API version bump — same `/api/v1` paths.
