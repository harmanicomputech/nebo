<?php

namespace App\Models;

use App\Support\Lookups;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A staff member on an event's team. */
#[Fillable(['event_id', 'staff_id', 'role', 'notes'])]
class EventStaff extends Model
{
    protected $table = 'event_staff';

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    /** @return BelongsTo<Staff, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withTrashed();
    }

    public function roleLabel(): string
    {
        return app(Lookups::class)->label('staff_role', $this->role);
    }
}
