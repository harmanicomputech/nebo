<?php

namespace App\Models\Concerns;

use App\Models\StatusChange;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasStatusHistory
{
    /** @return MorphMany<StatusChange, $this> */
    public function statusChanges(): MorphMany
    {
        return $this->morphMany(StatusChange::class, 'statusable')->latest('created_at')->latest('id');
    }
}
