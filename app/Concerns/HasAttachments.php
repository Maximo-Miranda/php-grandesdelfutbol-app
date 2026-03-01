<?php

namespace App\Concerns;

use App\Enums\AttachmentCollection;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function getAttachment(AttachmentCollection $collection): ?Attachment
    {
        return $this->attachments()->where('collection', $collection)->latest()->first();
    }
}
