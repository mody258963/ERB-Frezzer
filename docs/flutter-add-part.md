# Flutter — How to add a part

Guide for the **FrostParts Windows** app: create a new product (part) in the ERP via `/api/v1`.

**Requires:** internet, valid login, role **admin** or **manager**.

---

## 1. Flow overview

```mermaid
sequenceDiagram
  participant UI as Add Part screen
  participant API as ERB-Frezzer API

  UI->>API: GET /part-categories
  API-->>UI: categories list
  UI->>API: GET /part-units
  API-->>UI: unit enum options
  UI->>API: POST /parts?branch_id={branch}
  API-->>UI: 201 + part id + branch_id
  opt Opening quantity
    Note over API: stock row created automatically
  end
```

1. Open **Parts** → **Add part** (hide screen for `salesperson` / `warehouse`).
2. Ensure a **branch is selected** (admin dropdown or user’s assigned branch).
3. Load dropdown data (categories + units).
4. User fills the form and taps **Save**.
5. Call `POST /parts` **with the same `branch_id` you use on GET** (query or JSON body).
6. Server creates the part **and** a warehouse (`stock`) row for that branch.
7. Optional: set **`initial_quantity`** on POST for opening stock (or `POST /inventory/adjust` later).
8. Optional: `POST /parts/{id}/image` for photo upload.

---

## 2. Prerequisites

| Item | Detail |
|------|--------|
| Base URL | e.g. `https://your-server.com/api/v1` |
| Token | From `POST /auth/login` → store in secure storage |
| Header | `Authorization: Bearer <token>` on every request |
| Role | `admin` or `manager` (others get **403**) |

Check role after login (`GET /auth/me` → `user.role`).

---

## 3. Load dropdowns (before showing the form)

### Categories

```http
GET /api/v1/part-categories
Authorization: Bearer <token>
```

Example response (array of resources, no wrapper):

```json
[
  {
    "id": "uuid",
    "key": "compressor",
    "name": "Compressor",
    "sort_order": 1,
    "is_active": true
  }
]
```

**Flutter:** Show `name` in the dropdown; keep `key` (or `id`) for the submit payload. Prefer sending **`category_key`** (stable).

### Units (enum)

```http
GET /api/v1/part-units
```

```json
{
  "units": [
    { "value": "pc", "label": "Piece" },
    { "value": "kg", "label": "Kilogram" }
  ]
}
```

**Flutter:** Show `label`; submit **`value`** as `unit` (`pc`, `kg`, `box`, `set`, `m`, `l`, `roll`, `pack`).

Allowed values are fixed in the API enum — do not hardcode only in the app; always load from `GET /part-units` so new enum values work after server updates.

---

## 4. Form fields

| Field | API key | Required | Notes |
|-------|---------|----------|--------|
| Code | `code` | Yes | Unique **per branch**, max 64 chars |
| Name | `name` | Yes | Display name |
| Category | `category_key` | Yes* | From dropdown (`compressor`, `seals`, …) |
| Category (alt) | `category_id` | Yes* | UUID instead of `category_key` |
| Unit | `unit` | Yes | Enum value: `pc`, `kg`, … |
| Sell price | `sell_price` | Yes | Number ≥ 0 |
| Cost price | `cost_price` | Yes | Number ≥ 0 |
| Min stock | `min_stock` | Yes | Integer ≥ 0 |
| Opening qty | `initial_quantity` | No | Opening warehouse stock (default 0) |
| Active | `is_active` | No | Default `true` |
| Branch | `branch_id` | **Yes** | Query param, JSON body, or `X-Branch-Id` header |

\* One of `category_key` or `category_id` is required.

### Suggested UI layout

```
┌─────────────────────────────────────────┐
│  Add part                          [X]  │
├─────────────────────────────────────────┤
│  Code *        [ BRK-001____________ ]  │
│  Name *        [ Brake pad__________ ]  │
│  Category *    [ Compressor        ▼]  │
│  Unit *        [ Piece (pc)        ▼]  │
│  Sell price *  [ 150.00____________ ]  │
│  Cost price *  [  80.00____________ ]  │
│  Min stock *   [ 5_________________ ]  │
│  Opening qty   [ 0________________ ]  │
│  Active        [x]                      │
│  Branch        Main Branch (from filter)│
├─────────────────────────────────────────┤
│  [ Cancel ]              [ Save part ]  │
└─────────────────────────────────────────┘
```

After save → refresh **Parts** and **Inventory** for the active branch.

---

## 5. Create the part (API)

> **Critical:** `GET /parts?branch_id=…` alone is not enough. **POST must include the same branch** or you get HTTP **422** `branch_id is required`.

### Correct request

```http
POST /api/v1/parts?branch_id=019ebecf-cc58-71e9-a7ff-f262147240b6
Content-Type: application/json
Accept: application/json
Authorization: Bearer <token>

{
  "code": "BRK-001",
  "name": "Brake pad",
  "category_key": "compressor",
  "unit": "pc",
  "sell_price": 150,
  "cost_price": 80,
  "min_stock": 5,
  "is_active": true,
  "initial_quantity": 10
}
```

Alternative: put `"branch_id": "019ebecf-…"` in the JSON body instead of the query string.

### Flutter fix (`part_repository.dart`)

Your logs show the bug — branch on GET only:

```
GET  /parts?branch_id=019ebecf-…     ✅
POST /parts  (no branch_id)          ❌ 422
```

**Before (wrong):**

```dart
Future<Part> create(Map<String, dynamic> data) async {
  final res = await _dio.post('/parts', data: data);
  return Part.fromJson(res.data as Map<String, dynamic>);
}
```

**After (correct):**

```dart
Future<Part> create(
  Map<String, dynamic> data, {
  Map<String, dynamic>? branchQuery,
}) async {
  final res = await _dio.post(
    '/parts',
    queryParameters: branchQuery,
    data: {
      ...data,
      if (branchQuery?['branch_id'] != null)
        'branch_id': branchQuery!['branch_id'],
    },
  );
  return Part.fromJson(res.data as Map<String, dynamic>);
}
```

Call from `parts_screen.dart`:

```dart
await partRepo.create(
  {
    'code': code,
    'name': name,
    'category_key': categoryKey,
    'unit': unit,
    'sell_price': sellPrice,
    'cost_price': costPrice,
    'min_stock': minStock,
    'is_active': true,
    if (openingQty > 0) 'initial_quantity': openingQty,
  },
  branchQuery: branchQuery(), // same helper as GET /parts and GET /customers
);
```

Reuse the same `branchQuery()` helper from [flutter-admin-branch-filter.md](./flutter-admin-branch-filter.md):

```dart
Map<String, dynamic> branchQuery() => {
  if (selectedBranchId != null) 'branch_id': selectedBranchId,
};
```

For **non-admin** users, `selectedBranchId` should be `user.branch_id` from login — parts still require a branch on POST.

### Wrong request (causes 422)

```http
POST /api/v1/parts
{ "code": "1351", "name": "…", … }
```

No `branch_id` in query, body, or header → **422**.

### Success — `201 Created`

```json
{
  "id": "019e30c6-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
  "code": "BRK-001",
  "name": "Brake pad",
  "category_id": "uuid",
  "category_key": "compressor",
  "category_name": "Compressor",
  "unit": "pc",
  "unit_label": "Piece",
  "sell_price": 150,
  "cost_price": 80,
  "min_stock": 5,
  "is_active": true,
  "branch_id": "019ebecf-cc58-71e9-a7ff-f262147240b6",
  "image_url": null,
  "created_at": "2026-05-20T10:00:00.000000Z",
  "updated_at": "2026-05-20T10:00:00.000000Z"
}
```

Save `id` for stock adjust, optional image upload, and navigation to part detail / analysis.

### Errors

| Status | Meaning | Flutter action |
|--------|---------|----------------|
| `401` | Token invalid/expired | Go to login |
| `403` | Not admin/manager | Show “No permission” |
| `422` | Validation failed | Show field errors from `errors` object |
| `422` | Missing `branch_id` | Pass branch on POST (see above) |
| `422` / `409` | Duplicate `code` in **this branch** | Highlight code field |

Example `422` (invalid unit):

```json
{
  "message": "The unit field is invalid.",
  "errors": {
    "unit": ["The selected unit is invalid."]
  }
}
```

Example `422` (duplicate code):

```json
{
  "message": "The code has already been taken.",
  "errors": {
    "code": ["The code has already been taken."]
  }
}
```

Example `422` (missing branch — matches production logs):

```json
{
  "message": "branch_id is required. Send ?branch_id= on POST, include branch_id in JSON, or use the X-Branch-Id header.",
  "errors": {
    "branch_id": ["branch_id is required. Send ?branch_id= on POST, include branch_id in JSON, or use the X-Branch-Id header."]
  }
}
```

---

## 6. Upload part image (optional)

Requires **internet** (not queued offline). Max **2 MB**. JPEG, PNG, or WebP. See [part-image-api.md](./part-image-api.md).

```http
POST /api/v1/parts/{id}/image
Authorization: Bearer <token>
Content-Type: multipart/form-data

image: <file>
```

### Dio example (Windows file picker)

```dart
import 'package:dio/dio.dart';

Future<Map<String, dynamic>> uploadPartImage(String partId, String filePath) async {
  final fileName = filePath.split(RegExp(r'[\\/]')).last;
  final formData = FormData.fromMap({
    'image': await MultipartFile.fromFile(
      filePath,
      filename: fileName,
    ),
  });

  final response = await dio.post(
    '/parts/$partId/image',
    data: formData,
    options: Options(contentType: 'multipart/form-data'),
  );
  return response.data as Map<String, dynamic>;
}
```

**Client checks before upload:**

- File size ≤ 2 MB (`File(path).lengthSync()`).
- Extension `.jpg`, `.jpeg`, `.png`, `.webp`.

**Display in UI:**

```dart
if (part['image_url'] != null) {
  Image.network(part['image_url'] as String, height: 120, fit: BoxFit.cover);
} else {
  // placeholder icon
}
```

**Edit part:** same endpoint to replace; `DELETE /parts/{id}/image` to remove.

---

## 7. Flutter / Dio example (create part)

```dart
class PartFormData {
  final String code;
  final String name;
  final String categoryKey;
  final String unit; // enum value: pc, kg, ...
  final double sellPrice;
  final double costPrice;
  final int minStock;
  final bool isActive;

  Map<String, dynamic> toJson() => {
        'code': code,
        'name': name,
        'category_key': categoryKey,
        'unit': unit,
        'sell_price': sellPrice,
        'cost_price': costPrice,
        'min_stock': minStock,
        'is_active': isActive,
      };
}

Future<Map<String, dynamic>> createPart(PartFormData form) async {
  final response = await dio.post('/parts', data: form.toJson());
  return response.data as Map<String, dynamic>;
}

Future<List<CategoryOption>> loadCategories() async {
  final response = await dio.get('/part-categories');
  final list = response.data as List<dynamic>;
  return list
      .map((e) => CategoryOption(
            id: e['id'] as String,
            key: e['key'] as String,
            name: e['name'] as String,
          ))
      .toList();
}

Future<List<UnitOption>> loadUnits() async {
  final response = await dio.get('/part-units');
  final list = response.data['units'] as List<dynamic>;
  return list
      .map((e) => UnitOption(
            value: e['value'] as String,
            label: e['label'] as String,
          ))
      .toList();
}
```

Use one shared `Dio` instance with interceptors:

```dart
dio.options.baseUrl = '$apiHost/api/v1';
dio.options.headers['Accept'] = 'application/json';
dio.interceptors.add(InterceptorsWrapper(
  onRequest: (options, handler) {
    final token = await secureStorage.read(key: 'access_token');
    if (token != null) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  },
));
```

---

## 8. Add opening stock (recommended)

New parts have **no stock** until you adjust inventory.

```http
POST /api/v1/inventory/adjust
Authorization: Bearer <token>

{
  "part_id": "<id from create response>",
  "branch_id": "<user.branch_id or selected branch>",
  "quantity_delta": 100,
  "reason": "Opening stock"
}
```

| Role | Can adjust stock? |
|------|-------------------|
| `admin` | Yes |
| `warehouse` | Yes |
| `manager` | No (use admin/warehouse user or separate flow) |

After adjust, refresh POS catalog sync (`GET /inventory/{branchId}`) so offline selling sees the new part.

---

## 9. Permissions & navigation

| `user.role` | Show “Add part”? |
|-------------|------------------|
| `admin` | Yes |
| `manager` | Yes |
| `salesperson` | No (sell only) |
| `warehouse` | No (stock adjust only) |

Route example: `/parts/new` guarded by role check.

---

## 10. Offline

**You cannot add a part offline.** Only **sales** are queued locally when there is no internet.

If the user is offline, disable **Add part** and show: *Connect to the internet to add products.*

---

## 11. Optional: new category first

If the category does not exist in the dropdown:

```http
POST /api/v1/part-categories
{
  "key": "filters",
  "name": "Filters",
  "sort_order": 10,
  "is_active": true
}
```

`key` must be lowercase letters, numbers, underscores only (`filters`, `fan_motor`). Then create the part with `"category_key": "filters"`.

---

## 12. Checklist for developers

- [ ] Login and store Bearer token  
- [ ] `GET /part-categories` on screen open  
- [ ] `GET /part-units` on screen open  
- [ ] Validate required fields before submit  
- [ ] `POST /parts` with `category_key` + `unit` (enum value)  
- [ ] Optional `POST /parts/{id}/image` (≤ 2 MB, online only)  
- [ ] Handle `422` / `403` / `401`  
- [ ] Prompt to add stock via `POST /inventory/adjust`  
- [ ] Sync catalog if POS uses offline cache  

---

## 13. Related API docs

| Topic | Document |
|-------|----------|
| **Mobile intake (camera → part → stock)** | [flutter-mobile-inventory-intake.md](./flutter-mobile-inventory-intake.md) |
| Categories & units | [part-categories-units-api.md](./part-categories-units-api.md) |
| Full Windows ERP app | [flutter-windows-pos-offline-guide.md](./flutter-windows-pos-offline-guide.md) |
| Recent updates (POS price, images, Settings categories) | [flutter-windows-recent-updates.md](./flutter-windows-recent-updates.md) |
| Part images | [part-image-api.md](./part-image-api.md) |
| Part analysis screen | [part-analysis-api.md](./part-analysis-api.md) |
| Postman | `postman/ERB-Frezzer-API.postman_collection.json` → **Parts** |
