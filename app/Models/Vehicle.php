<?php

namespace App\Models;

use App\Enums\TripStatus;
use App\Enums\VehicleStatus;
use App\Models\Concerns\HasNotesAndDocuments;
use App\Support\Audit\Auditable;
use App\Support\Lookups;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'registration', 'type', 'capacity', 'payload_kg', 'status', 'default_driver_id', 'base_location_id', 'insurance_expires_on', 'roadworthiness_expires_on', 'remarks'])]
class Vehicle extends Model
{
    use Auditable, HasNotesAndDocuments, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => VehicleStatus::class,
            'payload_kg' => 'integer',
            'insurance_expires_on' => 'date',
            'roadworthiness_expires_on' => 'date',
        ];
    }

    /** @return BelongsTo<Staff, $this> */
    public function defaultDriver(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'default_driver_id')->withTrashed();
    }

    /** @return BelongsTo<Location, $this> */
    public function baseLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'base_location_id')->withTrashed();
    }

    /** @return HasMany<LogisticsTrip, $this> */
    public function trips(): HasMany
    {
        return $this->hasMany(LogisticsTrip::class);
    }

    public function typeLabel(): string
    {
        return app(Lookups::class)->label('vehicle_type', $this->type);
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('registration', 'like', $like));
        }
    }

    /** Papers expiring within $days (or already expired). */
    public function expiringPapers(int $days = 30): array
    {
        $limit = now(config('nebo.display_timezone'))->addDays($days)->startOfDay();

        return array_keys(array_filter([
            'Insurance' => $this->insurance_expires_on?->lte($limit),
            'Roadworthiness' => $this->roadworthiness_expires_on?->lte($limit),
        ]));
    }

    public function isOnTripNow(): bool
    {
        return $this->trips()->where('status', TripStatus::InTransit)->exists();
    }

    /**
     * @return array<int, string>
     */
    public static function options(?int $keep = null): array
    {
        return self::query()->where(fn ($q) => $q->where('status', VehicleStatus::Active)->when($keep, fn ($q) => $q->orWhere('id', $keep)))
            ->orderBy('name')->get()->mapWithKeys(fn (self $v) => [$v->id => "{$v->name} · {$v->registration}"])->all();
    }
}
