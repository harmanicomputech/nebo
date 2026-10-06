<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use App\Models\Concerns\HasNotesAndDocuments;
use App\Models\Concerns\HasStatusHistory;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A quotation. Created and changed only through QuotationService (D60–D62). */
#[Fillable(['reference', 'public_token', 'revision', 'customer_id', 'event_request_id', 'event_id', 'title', 'status', 'issued_on', 'valid_until', 'subtotal_kobo', 'discount_kobo', 'tax_rate_bp', 'tax_kobo', 'total_kobo', 'intro', 'terms', 'prepared_by', 'sent_by', 'sent_at', 'viewed_at', 'responded_at', 'responded_by_name', 'response_note'])]
#[Hidden(['public_token'])]
class Quotation extends Model
{
    use Auditable, HasNotesAndDocuments, HasStatusHistory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'issued_on' => 'date',
            'valid_until' => 'date',
            'revision' => 'integer',
            'subtotal_kobo' => 'integer',
            'discount_kobo' => 'integer',
            'tax_rate_bp' => 'integer',
            'tax_kobo' => 'integer',
            'total_kobo' => 'integer',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** @return BelongsTo<EventRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(EventRequest::class, 'event_request_id');
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by')->withTrashed();
    }

    /** @return HasMany<QuotationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Reference with the revision, e.g. NEBO-QUO-2026-00004 (rev 2). */
    public function label(): string
    {
        return $this->reference.($this->revision > 1 ? " (rev {$this->revision})" : '');
    }

    /** The discount actually applied: never more than the subtotal. */
    public function effectiveDiscountKobo(?int $subtotal = null): int
    {
        return min((int) $this->discount_kobo, $subtotal ?? (int) $this->subtotal_kobo);
    }

    public function taxPercent(): string
    {
        return rtrim(rtrim(number_format($this->tax_rate_bp / 100, 2), '0'), '.');
    }

    public function isExpired(): bool
    {
        return $this->status === QuotationStatus::Sent && $this->valid_until->lt(now(config('nebo.display_timezone'))->startOfDay());
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $query->where(fn ($q) => $q->where('reference', 'like', $like)->orWhere('title', 'like', $like)
            ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('company', 'like', $like)));
    }
}
