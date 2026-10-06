<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** An internal note. Never shown on the public portal. */
#[Fillable(['notable_type', 'notable_id', 'body', 'user_id', 'user_name'])]
class Note extends Model
{
    /** @return MorphTo<Model, $this> */
    public function notable(): MorphTo
    {
        return $this->morphTo();
    }
}
