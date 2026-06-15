# Flutter Windows app — June 2026 API updates (index)

**Audience:** `erd_rezzer` Flutter team  
**Backend:** ERB-Frezzer `/api/v1`

Read this page first, then open the detailed doc for each topic.

---

## Documents in this release

| Topic | Document | Build in app |
|-------|----------|--------------|
| **Decimal quantities** (0.5 m, 0.25 m), stock as numbers, transfer `unit_cost` | [flutter-decimal-quantities-and-transfers.md](./flutter-decimal-quantities-and-transfers.md) | POS qty input by `part.unit`; parse `double` everywhere |
| **Admin edit** pending transfers & latest customer payment | [flutter-admin-transaction-edits.md](./flutter-admin-transaction-edits.md) | Edit screens; admin-only |
| Branch isolation & branch picker | [flutter-per-branch-isolation.md](./flutter-per-branch-isolation.md) | Already in app — keep aligned |
| Branch on GET vs POST | [flutter-branch-switching-guide.md](./flutter-branch-switching-guide.md) | Dio interceptor |
| Weighted average cost | [flutter-weighted-average-cost.md](./flutter-weighted-average-cost.md) | Read-only cost on inventory |

---

## Quick priority list

1. **Parse quantities as `double`** — invoices, stock, transfers, returns, 422 errors.
2. **POS:** if `part.unit` is `m`, `kg`, or `l` → decimal keyboard; else integer only.
3. **Transfers:** multi-line POST; optional `unit_cost`; complete with `valuation`.
4. **Admin:** edit **pending** transfers (`PATCH /transfers/{id}`); edit **latest** customer payment (`PATCH /customers/{id}/payments/{paymentId}`).
5. **Production backend:** `php artisan migrate --force` once (decimal columns). **Never** `migrate:refresh` on production.

---

## New / changed endpoints

| Method | Path | Who | Purpose |
|--------|------|-----|---------|
| `POST` | `/invoices` | sales | Decimal qty on `m`/`kg`/`l` lines |
| `POST` | `/transfers` | admin/manager/warehouse | Multi-item; optional `items[].unit_cost` |
| `PATCH` | `/transfers/{id}` | **admin** | Edit pending transfer lines |
| `PATCH` | `/transfers/{id}/complete` | admin/manager/warehouse | Move stock; `valuation: cost\|sell` |
| `PATCH` | `/customers/{id}/payments/{paymentId}` | **admin** | Fix latest payment amount |

---

## QA smoke tests (app)

- [ ] Sell 0.5 m + 0.25 m on meter part; stock deducts 0.75
- [ ] Block 0.5 qty on piece part in UI (or handle 422)
- [ ] Create transfer 2× regulator → edit to 1 → complete
- [ ] Record customer payment 100 → admin edits to 80 → balance +20
- [ ] Non-admin does not see Edit on transfer or payment

---

## Older docs (still valid)

- [flutter-windows-recent-updates.md](./flutter-windows-recent-updates.md) — master changelog table
- [flutter-dev-updates-may-2026.md](./flutter-dev-updates-may-2026.md) — dashboard & financial reports
