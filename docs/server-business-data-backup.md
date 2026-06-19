# Server: backup & restore business data (after migrate:fresh)

Use this on the **production/staging server** when you need to run migrations from scratch but keep your real data (parts, stock, customers, suppliers, invoices, finance).

---

## What gets exported / restored

| Data | Included |
|------|----------|
| Branches + capital | ✅ |
| Parts (catalog) | ✅ |
| Warehouse stock (quantities) | ✅ |
| Customers + balances | ✅ |
| Suppliers + debt | ✅ |
| Invoices + items | ✅ |
| Purchases + installments + payments | ✅ |
| Returns | ✅ |
| Stock transfers | ✅ |
| Branch finance entries | ❌ excluded |
| Capital adjustments + owner cash-outs | ✅ |
| Customer payments + settlements | ✅ |

**Not included:** users, passwords, OAuth tokens, audit logs, stock movement history.

After import, login still works with **admin@example.com** / **password** (from seeder) unless you changed users manually.

---

## Step-by-step on server (SSH)

### 1) Backup first (recommended)

```bash
cd /path/to/ERB-Frezzer

# JSON snapshot (app export)
php artisan business-data:export

# Optional: full MySQL dump
mysqldump -u YOUR_USER -p YOUR_DATABASE > backup-$(date +%Y%m%d-%H%M).sql
```

Snapshot file: `database/snapshots/business-data.json`

**Download a copy** to your PC before step 3:

```bash
# from your local machine
scp user@server:/path/to/ERB-Frezzer/database/snapshots/business-data.json .
```

---

### 2) Put app in maintenance mode (optional)

```bash
php artisan down
```

---

### 3) Fresh migrations + base seed

```bash
php artisan migrate:fresh --seed --force
```

This recreates tables and seeds:
- Main Branch
- Admin user
- Part categories
- Passport client

---

### 4) Restore your business data

```bash
php artisan business-data:import --force
```

You will see a table of row counts from the snapshot, then data is restored.

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
| Import fails on FK | Ensure MySQL user can run migrations; retry import |
| Branch names changed | Branches are matched **by name**; keep names stable |

---

## When NOT to use migrate:fresh on production

- Normal deploys: use `php artisan migrate --force` only.
- `migrate:fresh` **drops all tables** — always export + mysqldump first.

---

## Verify after import

```bash
php artisan tinker
>>> \App\Models\Part::count()
>>> \App\Models\Stock::sum('quantity')
>>> \App\Models\Customer::sum('outstanding_balance')
>>> \App\Models\Supplier::sum('total_debt')
```

Or check dashboard in the app.
