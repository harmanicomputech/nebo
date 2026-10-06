<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['parent_id', 'name', 'slug', 'description', 'icon', 'sort_order', 'is_active'])]
class EquipmentCategory extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<EquipmentCategory, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<EquipmentCategory, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /** @return HasMany<Equipment, $this> */
    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'category_id');
    }

    /** @param Builder<EquipmentCategory> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Subcategories use their parent's icon unless they have their own. */
    public function iconName(): string
    {
        return $this->icon ?: ($this->parent?->icon ?: 'package');
    }

    public function fullName(): string
    {
        return $this->parent ? $this->parent->name.' › '.$this->name : $this->name;
    }

    /**
     * Grouped options for selects: top-level categories with their
     * subcategories indented beneath them.
     *
     * @return array<int, string>
     */
    public static function options(?int $keep = null): array
    {
        $all = self::query()->orderBy('sort_order')->orderBy('name')->get()
            ->filter(fn (self $c) => $c->is_active || $c->id === $keep);
        $options = [];

        foreach ($all->whereNull('parent_id') as $top) {
            $options[$top->id] = $top->name;
            foreach ($all->where('parent_id', $top->id) as $child) {
                $options[$child->id] = '— '.$child->name;
            }
        }

        return $options;
    }

    /**
     * This category and its subcategories (one level deep, as the UI allows).
     *
     * @return list<int>
     */
    public function selfAndChildIds(): array
    {
        return array_merge([$this->id], self::where('parent_id', $this->id)->pluck('id')->all());
    }
}
