<?php

namespace App\Models;

use App\Enums\LoadStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['load_list_id', 'allocation_id', 'status', 'case_label', 'note', 'checked_by', 'checked_at'])]
class LoadListItem extends Model
{
    protected function casts(): array
    {
        return ['status' => LoadStatus::class, 'checked_at' => 'datetime'];
    }

    /** @return BelongsTo<LoadList, $this> */
    public function loadList(): BelongsTo
    {
        return $this->belongsTo(LoadList::class);
    }

    /** @return BelongsTo<EquipmentAllocation, $this> */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(EquipmentAllocation::class, 'allocation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by')->withTrashed();
    }
}
