# Parts sales chart API (multi-part graph)

One endpoint for a **yearly chart**: which parts sell the most and **when** each month. Use in Flutter with `fl_chart` (line or grouped bar).

---

## Endpoint

```http
GET /api/v1/reports/parts-sales-chart
Authorization: Bearer <token>
```

### Query parameters

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `year` | int | current year | Calendar year |
| `limit` | int | `10` | Top N parts (max 50) |
| `rank_by` | string | `units` | Rank top parts by `units` or `revenue` |
| `branch_id` | UUID | — | Filter; ignored if user is branch-scoped |

### Example

```http
GET /api/v1/reports/parts-sales-chart?year=2026&limit=10&rank_by=units
```

---

## Response (for graphs)

```json
{
  "year": 2026,
  "period": {
    "from": "2026-01-01",
    "to": "2026-12-31",
    "branch_id": null
  },
  "rank_by": "units",
  "limit": 10,
  "months": [
    "2026-01", "2026-02", "2026-03", "2026-04", "2026-05", "2026-06",
    "2026-07", "2026-08", "2026-09", "2026-10", "2026-11", "2026-12"
  ],
  "series": [
    {
      "part_id": "uuid",
      "code": "BRK-001",
      "name": "Brake pad",
      "total_units_sold": 320,
      "total_revenue": 48000,
      "by_month": [
        { "month": "2026-01", "units_sold": 20, "revenue": 3000 },
        { "month": "2026-02", "units_sold": 35, "revenue": 5250 }
      ]
    }
  ]
}
```

- **`months`** — X-axis labels (always 12 months for the year).  
- **`series`** — one line/bar series per top part (`code` + `name` for legend).  
- **`by_month`** — aligned with `months`; zeros where no sales.

---

## Chart types

### Line chart (trend per part)

- X: `months`  
- Y: `series[i].by_month[j].units_sold` (or `revenue` if `rank_by=revenue`)  
- One line color per part in `series`

### Stacked bar (compare parts per month)

- X: `months`  
- Y: stacked `units_sold` per part for that month

### Horizontal bar (top parts for the year)

- Use `total_units_sold` or `total_revenue` from each series row (single bar chart, not time-based)

---

## Flutter (`fl_chart`) sketch

```dart
final data = await api.getPartsSalesChart(year: 2026, limit: 10);
final months = data.months;

LineChartData(
  lineBarsData: data.series.map((s) => LineChartBarData(
    spots: List.generate(months.length, (i) => FlSpot(
      i.toDouble(),
      s.byMonth[i].unitsSold.toDouble(),
    )),
  )).toList(),
);
```

---

## Related

| Need | Endpoint |
|------|----------|
| Single part deep-dive | `GET /parts/{id}/analysis` — see [part-analysis-api.md](./part-analysis-api.md) |
| Sales invoice list | `GET /reports/sales` |

---

## Backend

| File | Role |
|------|------|
| `app/Services/PartSalesChartService.php` | Aggregations |
| `app/Http/Controllers/Api/V1/ReportController.php` | `partsSalesChart()` |

Tests: `php artisan test tests/Feature/PartSalesChartTest.php`
