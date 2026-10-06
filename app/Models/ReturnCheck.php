<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'user_id', 'user_name', 'notes', 'returned_count', 'missing_count', 'damaged_count'])]
class ReturnCheck extends Model
{
    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    /** @return HasMany<ReturnCheckItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnCheckItem::class);
    }
}
