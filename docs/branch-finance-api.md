# Inter-branch money (branch finance) API

Track money owed between branches when stock moves or when you record manual charges and payments.

**Concept:** When branch **A** sends stock to branch **B**, **B owes A** the transfer value (default: sum of `quantity × cost_price`). Payments reduce the balance.

---

## Endpoints

Base: `/api/v1` · Auth: `Bearer <token>`

| Method | Path | Role | Description |
|--------|------|------|-------------|
| `GET` | `/branch-finance/balances` | any | Who owes whom (net balances) |
| `GET` | `/branch-finance/entries` | any | Ledger list (paginated) |
| `GET` | `/branch-finance/entries/{id}` | any | Single entry |
| `POST` | `/branch-finance/charges` | admin, manager | Manual charge |
| `POST` | `/branch-finance/payments` | admin, manager | Record payment |
| `PATCH` | `/branch-finance/entries/{id}/settle` | admin, manager | Mark charge settled |

### List entries query

`creditor_branch_id`, `debtor_branch_id`, `status` (`open`|`settled`), `entry_type` (`charge`|`payment`), `per_page`

---

## Automatic charge on stock transfer

When a transfer is **completed**, a **charge** is created unless disabled:

```http
PATCH /api/v1/transfers/{id}/complete
Content-Type: application/json

{
  "valuation": "cost",
  "record_branch_charge": true
}
```

| Field | Values | Default |
|-------|--------|---------|
| `valuation` | `cost`, `sell` | `cost` — unit value for the charge |
| `record_branch_charge` | boolean | `true` |

- **Creditor** = `from_branch` (sent stock)  
- **Debtor** = `to_branch` (received stock)  
- **Amount** = Σ (`quantity ×` unit price)

---

## Manual charge

```http
POST /api/v1/branch-finance/charges

{
  "creditor_branch_id": "uuid",
  "debtor_branch_id": "uuid",
  "amount": 1500.00,
  "description": "Shared marketing costs",
  "notes": "optional"
}
```

---

## Record payment

```http
POST /api/v1/branch-finance/payments

{
  "creditor_branch_id": "uuid",
  "debtor_branch_id": "uuid",
  "amount": 500.00,
  "notes": "Bank transfer ref 123"
}
```

Applies FIFO to **open** charges between the same branch pair (oldest first).

---

## Balance matrix

```http
GET /api/v1/branch-finance/balances
```

```json
{
  "balances": [
    {
      "creditor_branch_id": "uuid",
      "creditor_branch_name": "Main",
      "debtor_branch_id": "uuid",
      "debtor_branch_name": "Warehouse",
      "total_charges": 2000,
      "total_payments": 500,
      "balance_owed": 1500,
      "open_charges_count": 2
    }
  ]
}
```

`balance_owed` = `total_charges − total_payments` (debtor still owes creditor).

---

## Entry object

| Field | Description |
|-------|-------------|
| `entry_number` | e.g. `BFE-20260519-0001` |
| `entry_type` | `charge` or `payment` |
| `status` | `open` or `settled` |
| `reference_type` | `stock_transfer`, `manual`, `manual_payment` |
| `reference_id` | Linked transfer UUID when applicable |

---

## Flutter UI

- **Branch finance** screen: matrix table + ledger list  
- On transfer complete: show created charge amount in snackbar  
- Chart not required here; use balances for KPI cards  

---

## Backend

| File | Role |
|------|------|
| `app/Services/BranchFinanceService.php` | Logic |
| `app/Services/StockTransferService.php` | Auto-charge on complete |
| `database/migrations/2026_05_19_100000_create_branch_financial_entries_table.php` | Schema |

Run migration: `php artisan migrate`

### Server recovery (table exists but migrate failed)

If the first run failed with **index name too long** and `branch_financial_entries` already exists:

```bash
php artisan migrate --force
```

Deploy the latest migrations (short index names `bfe_branch_pair_status_idx`, `bfe_reference_idx`). The create migration skips when the table exists; `2026_05_19_100001_add_branch_financial_entries_indexes` adds any missing indexes.

If migrate still fails, mark the create migration as done then migrate again:

```sql
INSERT INTO migrations (migration, batch)
SELECT '2026_05_19_100000_create_branch_financial_entries_table', COALESCE(MAX(batch), 0) + 1 FROM migrations;
```

Then: `php artisan migrate --force`
