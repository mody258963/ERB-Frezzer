<?php

namespace App\Services;

use App\Models\Part;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PartImageService
{
    private const DISK = 'public';

    public function url(?string $imagePath): ?string
    {
        if ($imagePath === null || $imagePath === '') {
            return null;
        }

        return Storage::disk(self::DISK)->url($imagePath);
    }

    public function store(Part $part, UploadedFile $file): Part
    {
        $this->deleteFile($part->image_path);

        $path = $file->storeAs(
            'parts',
            $part->id.'.'.$file->getClientOriginalExtension(),
            self::DISK
        );

        $part->update(['image_path' => $path]);

        return $part->fresh(['category']);
    }

    public function delete(Part $part): Part
    {
        $this->deleteFile($part->image_path);
        $part->update(['image_path' => null]);

        return $part->fresh(['category']);
    }

    private function deleteFile(?string $path): void
    {
        if ($path !== null && $path !== '' && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
