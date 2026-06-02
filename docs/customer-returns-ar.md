# مرتجعات العملاء — مخزون واسترداد مالي

## السلوك بعد التحديث

### 1. إرجاع للمخزون (زيادة الكمية)

| حالة المنتج | القرار (resolution) | مخزون |
|-------------|---------------------|--------|
| **سليم (sellable)** | `restock`, `refund_cash`, `credit_note`, `replace` | ✅ يُضاف للمخزون |
| **معيب (defective)** | أي قرار | ❌ لا يُعاد للبيع (تالف) |

### 2. استرداد المال للعميل (يُخصم من لوحة التحكم)

| القرار | معنى | خصم مالي |
|--------|------|----------|
| `refund_cash` | إرجاع نقدي كامل | ✅ `total_value` |
| `writeoff` | منتج معيب + **إرجاع المال للعميل** | ✅ `total_value` |
| `credit_note` | تخفيض مديونية عميل آجل | ✅ `total_value` |
| `restock` | إرجاع للمخزون فقط (بدون استرداد نقدي في التقرير) | ❌ |
| `replace` | استبدال | ❌ |

**مهم:** للمنتج المعيب استخدم `writeoff` أو `refund_cash` — العميل يسترد المبلغ (`unit_price × quantity` على البند).

### 3. لوحة التحكم `GET /api/v1/dashboard/summary`

| الحقل | المعنى |
|--------|--------|
| `weekly_revenue` | مبيعات الفواتير (subtotal) قبل خصم الفاتورة |
| `weekly_customer_refunds` | مرتجعات عملاء مكتملة (نقد / معيب / إشعار دائن) |
| `weekly_net_sales` | تحصيل صافي ≈ فواتير − خصومات − مرتجعات |
| `weekly_profit` | ربح البنود − خصم الفاتورة − مرتجعات العملاء |

### 4. الموافقة على المرتجع

```http
PATCH /api/v1/returns/{id}/approve
{ "resolution": "refund_cash" }
```

أو للمعيب مع استرداد المال:

```json
{ "resolution": "writeoff" }
```

مع بند:

```json
{ "condition": "defective", "unit_price": 120, "quantity": 1 }
```

### 5. تطبيق Flutter

- عند **موافقة** المرتجع: استدعِ الـ API ثم **حدّث المخزون** (`GET /inventory/{branchId}`) و**لوحة التحكم**.
- اختر `resolution` حسب الحالة: نقدي → `refund_cash`، معيب + فلوس → `writeoff`، آجل → `credit_note`.
- لا تعتمد على المخزون المحلي فقط بعد المرتجع.

انظر أيضاً: [flutter-app-fixes.md](./flutter-app-fixes.md) §4.
