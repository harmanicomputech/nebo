<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/** One workflow transition. Append-only. */
#[Fillable(['statusable_type', 'statusable_id', 'from_status', 'to_status', 'user_id', 'user_name', 'note', 'created_at'])]
class StatusChange extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Status history cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Status history cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return MorphTo<Model, $this> */
    public function statusable(): MorphTo
    {
        return $this->morphTo();
    }
}
