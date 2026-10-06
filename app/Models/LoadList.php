<?php

namespace App\Models;

use App\Enums\LoadStatus;
use App\Models\Concerns\HasStatusHistory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'reference', 'status', 'notes', 'prepared_by', 'dispatched_at', 'dispatched_by'])]
class LoadList extends Model
{
    use HasStatusHistory;

    protected function casts(): array
    {
        return ['status' => LoadStatus::class, 'dispatched_at' => 'datetime'];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    /** @return HasMany<LoadListItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(LoadListItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by')->withTrashed();
    }
}
