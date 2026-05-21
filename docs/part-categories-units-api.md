# Part categories & units

**Categories** — lookup table (`part_categories`) with `key` and `name`.  
**Units** — PHP enum on `parts.unit` (no database table).

---

## Units enum (`App\Enums\PartUnit`)

| Value | Label |
|-------|--------|
| `pc` | Piece |
| `box` | Box |
| `set` | Set |
| `kg` | Kilogram |
| `m` | Meter |
| `l` | Liter |
| `roll` | Roll |
| `pack` | Pack |

### List allowed units (Flutter dropdown)

```http
GET /api/v1/part-units
Authorization: Bearer <token>
```

```json
{
  "units": [
    { "value": "pc", "label": "Piece" },
    { "value": "kg", "label": "Kilogram" }
  ]
}
```

No create/update for units — add new cases in `app/Enums/PartUnit.php` and deploy.

---

## Categories

| Method | Path |
|--------|------|
| `GET` | `/part-categories` |
| `POST` | `/part-categories` (admin, manager) |
| `PUT` | `/part-categories/{id}` |
| `DELETE` | `/part-categories/{id}` (admin) |

---

## Create / update part

```json
POST /api/v1/parts
{
  "code": "P-001",
  "name": "Compressor Unit",
  "category_key": "compressor",
  "unit": "pc",
  "sell_price": 150,
  "cost_price": 80,
  "min_stock": 5,
  "is_active": true
}
```

`unit` must be one of the enum values above (`422` if invalid).

**Response:**

```json
{
  "unit": "pc",
  "unit_label": "Piece",
  "category_key": "compressor",
  ...
}
```

### Flutter

See **[flutter-add-part.md](./flutter-add-part.md)** for the full add-part screen flow, Dio examples, errors, and stock step.

---

## Database (migrate:fresh)

| Migration | Creates |
|-----------|---------|
| `2025_05_17_100000_create_part_categories_table` | `part_categories` |
| `2025_05_17_100001_create_parts_table` | `parts` (`category_id` FK, `unit` string) |

Default categories: `database/seeders/PartCategorySeeder.php` (runs with `php artisan db:seed`).
