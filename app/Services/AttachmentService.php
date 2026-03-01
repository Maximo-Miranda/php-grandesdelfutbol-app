<?php

namespace App\Services;

use App\Enums\AttachmentCollection;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AttachmentService
{
    public function upload(Model $attachable, UploadedFile $file, AttachmentCollection $collection, string $disk = 'public'): Attachment
    {
        // Remove existing attachment for this collection (single-file collections like logo/photo)
        $existing = $attachable->attachments()->where('collection', $collection)->first();
        if ($existing) {
            $this->delete($existing);
        }

        $path = $file->store(
            $this->getStoragePath($attachable, $collection),
            $disk
        );

        return $attachable->attachments()->create([
            'collection' => $collection,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    public function delete(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }

    private function getStoragePath(Model $attachable, AttachmentCollection $collection): string
    {
        $type = strtolower(class_basename($attachable));

        return "{$type}/{$attachable->getKey()}/{$collection->value}";
    }
}
