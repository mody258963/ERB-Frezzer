# Part image API

Single product image per part (catalog only). Max **2 MB**. Formats: JPEG, PNG, WebP.

| Method | Path | Roles |
|--------|------|-------|
| `POST` | `/api/v1/parts/{id}/image` | admin, manager |
| `DELETE` | `/api/v1/parts/{id}/image` | admin, manager |

## Upload

```http
POST /api/v1/parts/{id}/image
Authorization: Bearer <token>
Content-Type: multipart/form-data

image: <file>
```

**Validation:** required file, `image`, `mimes:jpeg,jpg,png,webp`, `max:2048` (kilobytes).

**Response (200):** full part resource including `image_url` (public URL under `/storage/parts/...`).

Re-upload replaces the previous file.

## Delete

```http
DELETE /api/v1/parts/{id}/image
```

Sets `image_url` to `null` and removes the file from disk.

## Part JSON field

```json
{
  "id": "uuid",
  "image_url": "https://your-server.com/storage/parts/uuid.jpg"
}
```

`image_url` is `null` when no image is set.

## Deploy

Docker entrypoint runs `php artisan storage:link` and ensures `storage/app/public/parts` is writable.

Flutter: see [flutter-add-part.md](./flutter-add-part.md).
