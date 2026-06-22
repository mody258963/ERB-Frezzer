# Server: backup & restore catalog data (after migrate:fresh)

Use this on the **production/staging server** when you need to run migrations from scratch but keep your **catalog** data — not invoices or finance history.

---

## What gets exported / restored

| Data | Included | Notes |
|------|----------|--------|
| Parts (catalog) | ✅ | code, name, prices, category, etc. |
| Warehouse stock | ✅ | quantities + average cost per branch |
| Customers | ✅ | **name, type (cash/credit), contact** — balances reset to **0** |
| Suppliers | ✅ | **name, contact** — debt reset to **0** |
| Users + passwords | ❌ | From `migrate:fresh --seed` (admin@example.com) |
| Branches | ❌ | From seed — matched **by name** for stock/part branch links |
| Part categories | ❌ | From `PartCategorySeeder` |
| Invoices | ❌ | |
| Purchases / installments / payments | ❌ | |
| Returns | ❌ | |
| Customer payments / settlements | ❌ | |
| Capital / owner cash-outs | ❌ | |
| Stock transfers | ❌ | |
| Audit logs | ❌ | |

**Purpose:** Clean migration with a fresh financial slate while keeping your product catalog, warehouse quantities, customer/supplier directory, and credit vs cash customer type.

---

## Step-by-step on server (SSH)

### 1) Backup first (recommended)

```bash
cd /path/to/ERB-Frezzer

php artisan business-data:export

# Optional: full MySQL dump (only way to keep invoices/history)
mysqldump -u YOUR_USER -p YOUR_DATABASE > backup-$(date +%Y%m%d-%H%M).sql
```

Snapshot file: `database/snapshots/business-data.json`

**Download a copy** before migrate:fresh:

```bash
scp user@server:/path/to/ERB-Frezzer/database/snapshots/business-data.json .
```

---

### 2) Maintenance mode (optional)

```bash
php artisan down
```

---

### 3) Fresh migrations + base seed

```bash
php artisan migrate:fresh --seed --force
```

Recreates tables and seeds:

- Main Branch (keep the **same branch name** as before)
- Admin user
- Part categories
- Passport client

---

### 4) Restore catalog data

```bash
php artisan business-data:import --force
```

---

### 5) Cache + back online

```bash
php artisan cache:clear
php artisan config:clear
php artisan up
```

---

## One-liner (only if you already exported)

```bash
cd /path/to/ERB-Frezzer && \
php artisan migrate:fresh --seed --force && \
php artisan business-data:import --force && \
php artisan cache:clear
```

---

## Custom snapshot path

```bash
php artisan business-data:export --path=storage/app/my-backup.json
php artisan business-data:import --path=storage/app/my-backup.json --force
```

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| `Snapshot not found` | Run `business-data:export` first |
| `Admin user not found` | Run `migrate:fresh --seed` before import |
| `old full-data format` | Re-export with current app (`catalog-only-v2` schema) |
| `Part category not found in seed` | Add category to seeder or fix part category keys |
| Branch stock wrong branch | Branch names in DB must match snapshot mapping — keep names stable |

---

## When NOT to use migrate:fresh on production

- Normal deploys: use `php artisan migrate --force` only.
- `migrate:fresh` **drops all tables** — always export + mysqldump first if you need invoice history.

---

## Verify after import

```bash
php artisan tinker
>>> \App\Models\Part::count()
>>> \App\Models\Stock::sum('quantity')
>>> \App\Models\Customer::count()
>>> \App\Models\Supplier::count()
>>> \App\Models\Invoice::count()   // should be 0
```

Or check dashboard — profit, invoices, and debts should start clean.
