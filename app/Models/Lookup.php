<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use App\Support\Lookups;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One option in a configurable list. Records store `key`, so keys never
 * change once created; labels, order and active state can.
 */
#[Fillable(['group', 'key', 'label', 'sort_order', 'is_active', 'is_system', 'meta'])]
class Lookup extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        static::saved(fn () => app(Lookups::class)->flush());
        static::deleted(fn () => app(Lookups::class)->flush());
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_system' => 'boolean', 'meta' => 'array'];
    }

    /** @param Builder<Lookup> $query */
    public function scopeGroup(Builder $query, string $group): void
    {
        $query->where('group', $group)->orderBy('sort_order')->orderBy('label');
    }

    public function auditLabel(): string
    {
        return "{$this->group}:{$this->key}";
    }
}
