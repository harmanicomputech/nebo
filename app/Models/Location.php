<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use App\Support\Lookups;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['parent_id', 'name', 'code', 'type', 'address', 'notes', 'is_active'])]
class Location extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<Location, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<EquipmentAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(EquipmentAsset::class);
    }

    /** @return HasMany<StockLevel, $this> */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /** @param Builder<Location> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function typeLabel(): string
    {
        return app(Lookups::class)->label('location_type', $this->type);
    }

    /**
     * @return array<int, string>
     */
    public static function options(?int $keep = null): array
    {
        return self::query()->where(fn ($q) => $q->where('is_active', true)->when($keep, fn ($q) => $q->orWhere('id', $keep)))
            ->orderBy('name')->get()
            ->mapWithKeys(fn (self $l) => [$l->id => "{$l->name} ({$l->code})"])->all();
    }
}
