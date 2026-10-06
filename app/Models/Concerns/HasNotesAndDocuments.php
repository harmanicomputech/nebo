<?php

namespace App\Models\Concerns;

use App\Models\Document;
use App\Models\Note;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasNotesAndDocuments
{
    /** @return MorphMany<Note, $this> */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->latest()->latest('id');
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->latest();
    }
}
