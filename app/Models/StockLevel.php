<?php

namespace App\Models;

use App\Enums\StockBucket;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quantity of a bulk item at a location. Changed only through StockService,
 * which writes the ledger (so this model is not separately audited).
 */
#[Fillable(['equipment_id', 'location_id', 'bucket', 'quantity'])]
class StockLevel extends Model
{
    protected function casts(): array
    {
        return ['bucket' => StockBucket::class, 'quantity' => 'integer'];
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }
}
