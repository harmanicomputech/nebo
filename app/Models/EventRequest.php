<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Models\Concerns\HasNotesAndDocuments;
use App\Models\Concerns\HasStatusHistory;
use App\Support\Audit\Auditable;
use App\Support\Lookups;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An Event Production Request from the public form (or entered by staff).
 * Status changes only through RequestWorkflow.
 */
#[Fillable(['reference', 'public_token', 'submission_key', 'customer_id', 'event_name', 'event_type', 'event_type_other', 'event_date', 'venue', 'venue_meta', 'contact_person', 'company', 'email', 'phone', 'duration_days', 'starts_at', 'ends_at', 'setup_at', 'has_existing_design', 'budget_range', 'requirements', 'additional_info', 'services_other', 'status', 'assigned_to', 'submitted_ip', 'user_agent', 'converted_event_id'])]
class EventRequest extends Model
{
    use Auditable, HasNotesAndDocuments, HasStatusHistory;

    /** @var list<string> */
    protected array $auditExclude = ['public_token', 'submission_key', 'user_agent'];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'event_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'setup_at' => 'datetime',
            'has_existing_design' => 'boolean',
            'venue_meta' => 'array',
            'duration_days' => 'integer',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    /** @return BelongsToMany<Service, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->orderBy('sort_order');
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'converted_event_id')->withTrashed();
    }

    public function eventTypeLabel(): string
    {
        return $this->event_type === 'other' && $this->event_type_other
            ? $this->event_type_other
            : app(Lookups::class)->label('event_type', $this->event_type);
    }

    public function budgetLabel(): string
    {
        return app(Lookups::class)->label('budget_range', $this->budget_range);
    }

    /** @param Builder<EventRequest> $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';
        $query->where(fn (Builder $q) => $q->where('reference', 'like', $like)->orWhere('event_name', 'like', $like)
            ->orWhere('contact_person', 'like', $like)->orWhere('company', 'like', $like)
            ->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('venue', 'like', $like));
    }

    /** @param Builder<EventRequest> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', RequestStatus::openValues());
    }
}
