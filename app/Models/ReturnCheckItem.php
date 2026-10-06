<?php

namespace App\Models;

use App\Enums\ReturnOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['return_check_id', 'allocation_id', 'outcome', 'quantity', 'location_id', 'note'])]
class ReturnCheckItem extends Model
{
    protected function casts(): array
    {
        return ['outcome' => ReturnOutcome::class, 'quantity' => 'integer'];
    }

    /** @return BelongsTo<EquipmentAllocation, $this> */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(EquipmentAllocation::class, 'allocation_id');
    }
}
