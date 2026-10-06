<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A priced bundle that prefills quotations. Changed through PackageService. */
#[Fillable(['name', 'slug', 'description', 'event_type', 'is_active', 'sort_order'])]
class ProductionPackage extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return HasMany<PackageItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PackageItem::class, 'package_id')->orderBy('sort_order')->orderBy('id');
    }

    /** Total of its lines at list price. */
    public function totalKobo(): int
    {
        return (int) $this->items->sum(fn (PackageItem $i) => $i->quantity * $i->days * $i->unit_price_kobo);
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return self::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all();
    }
}
